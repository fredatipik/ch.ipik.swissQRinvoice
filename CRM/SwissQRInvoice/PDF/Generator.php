<?php
/**
 * PDF rendering: invoices (with QR payment part) and stand-alone donation slips.
 */
class CRM_SwissQRInvoice_PDF_Generator {

  const MARGIN_L  = 20;
  const MARGIN_R  = 15;
  const MARGIN_T  = 15;
  const PAGE_W    = 210;
  const CONTENT_W = 175;

  /**
   * Top edge of the QR payment part, in mm from the top of an A4 page.
   * The payment part always sits in the bottom 105 mm of the page it is drawn on.
   */
  const SLIP_TOP = 193;

  private array $invoice;
  private array $settings;
  private array $orgContact;

  /**
   * Tighter vertical spacing, so that short invoices actually fit above the
   * payment part when the single-page option is on.
   */
  private bool $compact = FALSE;

  public function __construct(array $invoice) {
    $this->invoice    = $invoice;
    $this->settings   = CRM_SwissQRInvoice_Utils::getSettings();
    $this->orgContact = CRM_SwissQRInvoice_Utils::getOrgContact((int) ($this->settings['swissqr_org_contact_id'] ?? 0));
  }

  // ── Invoice ────────────────────────────────────────────────────────────────

  public function generate(): string {
    CRM_SwissQRInvoice_Utils::loadLibraries();
    $this->compact = !empty($this->settings['swissqr_single_page_pdf']);
    $pdf = self::newPdf(self::t('Invoice %1', [1 => $this->invoice['invoice_number']]), $this->orgContact['display_name'] ?? '');
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 9);

    $this->renderHeader($pdf);
    $this->renderRecipient($pdf);
    $this->renderInvoiceMeta($pdf);
    $this->renderLines($pdf);
    $this->renderSummary($pdf);
    $this->renderFooterNote($pdf);

    $amount = (float) $this->invoice['amount_due'];
    $debtor = CRM_SwissQRInvoice_Utils::qrAddress(
      $this->invoice['contact_name'] ?? '',
      CRM_SwissQRInvoice_Utils::getPrimaryAddress((int) $this->invoice['contact_id'])
    );
    $qr = $this->buildQrBill(
      $amount > 0 ? $amount : NULL,
      $debtor,
      CRM_SwissQRInvoice_Reference::KIND_INVOICE,
      ['invoice_id' => (int) $this->invoice['id']],
      (string) ($this->invoice['reference'] ?? '')
    );

    // Single page when the option is on and the invoice body leaves room.
    // The payment part starts at SLIP_TOP, but its "detach before payment"
    // notice is printed 5 mm above that line, so the body must stop earlier.
    $singlePage = !empty($this->settings['swissqr_single_page_pdf'])
      && $pdf->GetY() <= self::SLIP_TOP - 9;
    $this->drawPaymentPart($pdf, $qr, !$singlePage);

    return $pdf->Output('', 'S');
  }

  // ── Donation slip ──────────────────────────────────────────────────────────

  /**
   * One-page donation slip: sender header, short text, QR payment part.
   *
   * No amount (the donor chooses it) and no debtor address: the link to this
   * PDF may be forwarded, so it carries no personal data. The reference alone
   * identifies the donor when the payment comes back.
   */
  public static function generateDonationSlip(int $contactId, int $mailingId = 0): string {
    CRM_SwissQRInvoice_Utils::loadLibraries();
    $gen = new self(['id' => 0, 'invoice_number' => '', 'contact_id' => $contactId, 'amount_due' => 0, 'lines' => []]);

    $pdf = self::newPdf(self::t('Donation slip'), $gen->orgContact['display_name'] ?? '');
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 9);
    $gen->renderHeader($pdf);

    $pdf->Ln(20);
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->SetX(self::MARGIN_L);
    $pdf->Cell(0, 9, self::t('Donation slip'), 0, 1, 'L');
    $pdf->Ln(4);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetX(self::MARGIN_L);
    $pdf->MultiCell(self::CONTENT_W, 5,
      self::t('Thank you for your support. Use the payment part below to make a donation of the amount of your choice, by scanning the QR code with your banking app or by entering the details in your e-banking.'),
      0, 'L');

    $qr = $gen->buildQrBill(
      NULL,
      NULL,
      CRM_SwissQRInvoice_Reference::KIND_DONATION,
      ['contact_id' => $contactId, 'mailing_id' => $mailingId],
      self::t('Donation')
    );
    $gen->drawPaymentPart($pdf, $qr, FALSE);

    return $pdf->Output('', 'S');
  }

  // ── QR payment part ────────────────────────────────────────────────────────

  private function buildQrBill(?float $amount, $debtor, string $kind, array $ids, string $message): \Sprain\SwissQrBill\QrBill {
    $iban = preg_replace('/\s+/', '', (string) ($this->settings['swissqr_iban'] ?? ''));
    $org  = $this->orgContact;

    $creditor = CRM_SwissQRInvoice_Utils::qrAddress($org['display_name'] ?? '', $org);
    if (!$creditor) {
      throw new CRM_Core_Exception('The sender organisation needs a name, postcode and city for the QR-bill.');
    }

    $qr = \Sprain\SwissQrBill\QrBill::create();
    $qr->setCreditor($creditor);
    $qr->setCreditorInformation(\Sprain\SwissQrBill\DataGroup\Element\CreditorInformation::create($iban));
    $qr->setPaymentAmountInformation($amount !== NULL
      ? \Sprain\SwissQrBill\DataGroup\Element\PaymentAmountInformation::create('CHF', $amount)
      : \Sprain\SwissQrBill\DataGroup\Element\PaymentAmountInformation::create('CHF'));
    if ($debtor) {
      $qr->setUltimateDebtor($debtor);
    }
    $qr->setPaymentReference(CRM_SwissQRInvoice_Reference::build($iban, $kind, $ids));

    // Free text shown to the payer ("Additional information"), max 140 characters.
    $message = trim($message);
    if ($message !== '') {
      $message = function_exists('mb_substr') ? mb_substr($message, 0, 140) : substr($message, 0, 140);
      $qr->setAdditionalInformation(\Sprain\SwissQrBill\DataGroup\Element\AdditionalInformation::create($message));
    }
    return $qr;
  }

  /**
   * Draw the payment part at the bottom of the current page, or of a new page.
   * Errors are logged rather than thrown, so the invoice still renders.
   */
  private function drawPaymentPart(TCPDF $pdf, \Sprain\SwissQrBill\QrBill $qr, bool $newPage): void {
    try {
      if (!$qr->isValid()) {
        $errors = [];
        foreach ($qr->getViolations() as $v) {
          $errors[] = $v->getPropertyPath() . ': ' . $v->getMessage();
        }
        throw new CRM_Core_Exception('Invalid QR-bill data — ' . implode('; ', $errors));
      }
      if ($newPage) {
        $pdf->AddPage();
      }
      // Coordinates inside TcPdfOutput are absolute (bottom of an A4 page), so no offset.
      $output = new \Sprain\SwissQrBill\PaymentPart\Output\TcPdfOutput\TcPdfOutput(
        $qr, CRM_SwissQRInvoice_Utils::qrLanguage(), $pdf, 0, 0
      );
      $output->getPaymentPart();
    }
    catch (\Throwable $e) {
      CRM_Core_Error::debug_log_message('SwissQRInvoice payment part error: ' . $e->getMessage());
    }
  }

  // ── Invoice body ───────────────────────────────────────────────────────────

  private function renderHeader(TCPDF $pdf): void {
    $org      = $this->orgContact;
    $logoPath = $this->settings['swissqr_logo_path'] ?? '';
    $offsetX  = (float) ($this->settings['swissqr_logo_offset_x'] ?? 0);
    if ($logoPath && file_exists($logoPath)) {
      $pdf->Image($logoPath, self::MARGIN_L + $offsetX, self::MARGIN_T, 70, 22, '', '', 'T', FALSE, 300, '', FALSE, FALSE, 0, TRUE);
    }
    $marginBottom = (float) ($this->settings['swissqr_logo_margin_bottom'] ?? 0);
    $pdf->SetY(self::MARGIN_T + 24 + $marginBottom);
    $pdf->SetFont('helvetica', '', 8.5);
    $pdf->SetTextColor(60, 60, 60);
    $lines = array_filter([
      $org['street_address'] ?? '',
      trim(($org['postal_code'] ?? '') . ' ' . ($org['city'] ?? '')),
      $org['phone'] ?? '',
      $org['email'] ?? '',
    ]);
    foreach ($lines as $l) {
      $pdf->SetX(self::MARGIN_L);
      $pdf->Cell(90, 4.5, $l, 0, 1, 'L');
    }
    $pdf->SetTextColor(0, 0, 0);
  }

  private function renderRecipient(TCPDF $pdf): void {
    $addr = CRM_SwissQRInvoice_Utils::getPrimaryAddress((int) $this->invoice['contact_id']);
    $y = 55;
    $pdf->SetFont('helvetica', '', 8.5);
    foreach (array_filter([
      $this->invoice['contact_name'] ?? '',
      $addr['street_address'],
      trim($addr['postal_code'] . ' ' . $addr['city']),
    ]) as $l) {
      $pdf->SetXY(120, $y);
      $pdf->Cell(75, 4.5, $l, 0, 0, 'L');
      $y += 4.5;
    }
    $pdf->SetY(max($y + 5, 72));
  }

  private function renderInvoiceMeta(TCPDF $pdf): void {
    $date = date('d.m.Y', strtotime($this->invoice['invoice_date']));
    $city = $this->orgContact['city'] ?? '';
    $due  = !empty($this->invoice['due_date']) ? date('d.m.Y', strtotime($this->invoice['due_date'])) : '';

    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetXY(120, $pdf->GetY());
    $pdf->Cell(75, 5, $city ? self::t('%1, %2', [1 => $city, 2 => $date]) : $date, 0, 1, 'L');
    $pdf->Ln($this->compact ? 5 : 12);

    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->SetX(self::MARGIN_L);
    $pdf->Cell(50, 9, self::t('Invoice'), 0, 0, 'L');
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 9, $this->invoice['invoice_number'], 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 8.5);
    $pdf->SetX(self::MARGIN_L);
    $pdf->Cell(0, 4.5, $date, 0, 1, 'L');
    if ($due) {
      $pdf->SetTextColor(100, 100, 100);
      $pdf->SetX(self::MARGIN_L);
      $pdf->Cell(0, 4, self::t('Due date: %1', [1 => $due]), 0, 1, 'L');
      $pdf->SetTextColor(0, 0, 0);
    }
    $pdf->Ln($this->compact ? 2 : 5);
  }

  private function renderLines(TCPDF $pdf): void {
    $cw    = [45, 70, 24, 12, 24];
    $align = ['L', 'L', 'R', 'R', 'R'];
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetFillColor(235, 235, 235);
    $headers = [self::t('Item'), self::t('Description'), self::t('Unit cost'), self::t('Qty'), self::t('Total excl. VAT')];
    foreach ($headers as $k => $h) {
      $pdf->Cell($cw[$k], 6, $h, 'B', 0, $align[$k], TRUE);
    }
    $pdf->Ln();
    $pdf->SetFillColor(255, 255, 255);
    $pdf->SetFont('helvetica', '', 8.5);
    foreach ($this->invoice['lines'] as $i => $line) {
      $bg = ($i % 2 === 1);
      if ($bg) {
        $pdf->SetFillColor(248, 248, 248);
      }
      $pdf->Cell($cw[0], 5.5, $line['article'], 0, 0, 'L', $bg);
      $pdf->Cell($cw[1], 5.5, (string) $line['description'], 0, 0, 'L', $bg);
      $pdf->Cell($cw[2], 5.5, 'CHF ' . self::num($line['unit_price']), 0, 0, 'R', $bg);
      $pdf->Cell($cw[3], 5.5, (string) (0 + $line['quantity']), 0, 0, 'R', $bg);
      $pdf->Cell($cw[4], 5.5, self::num($line['line_total']), 0, 1, 'R', $bg);
      if ($bg) {
        $pdf->SetFillColor(255, 255, 255);
      }
    }
    $pdf->SetDrawColor(180, 180, 180);
    $pdf->SetLineWidth(0.3);
    $pdf->Line(self::MARGIN_L, $pdf->GetY(), self::PAGE_W - self::MARGIN_R, $pdf->GetY());
    $pdf->SetDrawColor(0, 0, 0);
    $pdf->Ln(2);
  }

  private function renderSummary(TCPDF $pdf): void {
    $lX = 145;
    $vW = 35;
    $lH = 5.5;
    if (($this->invoice['discount'] ?? 0) > 0) {
      $pdf->SetFont('helvetica', '', 8.5);
      $pdf->SetXY($lX, $pdf->GetY() + 2);
      $pdf->Cell($vW, $lH, self::t('Subtotal'), 0, 0, 'L');
      $pdf->Cell(15, $lH, self::num($this->invoice['subtotal']), 0, 1, 'R');
      $pdf->SetTextColor(100, 100, 100);
      $pdf->SetX($lX);
      $pdf->Cell($vW, $lH, self::t('Discount'), 0, 0, 'L');
      $pdf->Cell(15, $lH, '- ' . self::num($this->invoice['discount']), 0, 1, 'R');
      $pdf->SetTextColor(0, 0, 0);
    }
    $rows = [
      [self::t('Total'),        self::num($this->invoice['total']),       TRUE],
      [self::t('Paid to date'), self::num($this->invoice['amount_paid']), FALSE],
      [self::t('Balance due'),  self::num($this->invoice['amount_due']),  TRUE],
    ];
    foreach ($rows as [$label, $value, $bold]) {
      $pdf->SetFont('helvetica', $bold ? 'B' : '', 9);
      $pdf->SetX($lX);
      $pdf->Cell($vW, $lH, $label, 0, 0, 'L');
      $pdf->Cell(15, $lH, 'CHF ' . $value, 0, 1, 'R');
    }
    $pdf->Ln($this->compact ? 3 : 8);
  }

  private function renderFooterNote(TCPDF $pdf): void {
    $vat = trim((string) ($this->settings['swissqr_vat_note'] ?? ''));
    if ($vat) {
      $pdf->SetFont('helvetica', 'I', 8);
      $pdf->SetTextColor(80, 80, 80);
      $pdf->SetX(self::MARGIN_L);
      $pdf->Cell(0, 4.5, $vat, 0, 1, 'L');
      $pdf->SetTextColor(0, 0, 0);
      $pdf->Ln($this->compact ? 2 : 5);
    }
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetX(self::MARGIN_L);
    $pdf->Cell(0, 5, self::t('Kind regards,'), 0, 1, 'L');
    $pdf->Ln($this->compact ? 3 : 8);

    $sig        = $this->settings['swissqr_signature_path'] ?? '';
    $sigOffsetY = (float) ($this->settings['swissqr_signature_offset_y'] ?? 0);
    if ($sig && file_exists($sig)) {
      $sigW = 35;
      $info = @getimagesize($sig);
      $sigH = ($info && $info[1] > 0) ? round($sigW * $info[1] / $info[0], 1) : 15;
      $sigY = $pdf->GetY() + $sigOffsetY;
      $pdf->Image($sig, self::MARGIN_L, $sigY, $sigW, 0, '', '', 'T', FALSE, 300);
      $pdf->SetY(max($pdf->GetY(), $sigY + $sigH) + 3);
    }
    $sn = $this->settings['swissqr_signatory_name'] ?? '';
    if ($sn) {
      $pdf->SetFont('helvetica', '', 9);
      $pdf->SetX(self::MARGIN_L);
      $pdf->Cell(0, 5, $sn, 0, 1, 'L');
    }
  }

  // ── Helpers ────────────────────────────────────────────────────────────────

  private static function newPdf(string $title, string $author): TCPDF {
    $pdf = new TCPDF('P', 'mm', 'A4', TRUE, 'UTF-8', FALSE);
    $pdf->SetCreator('CiviCRM Swiss QR Invoice');
    $pdf->SetAuthor($author);
    $pdf->SetTitle($title);
    $pdf->setPrintHeader(FALSE);
    $pdf->setPrintFooter(FALSE);
    $pdf->SetMargins(self::MARGIN_L, self::MARGIN_T, self::MARGIN_R);
    $pdf->SetAutoPageBreak(FALSE);
    return $pdf;
  }

  private static function num($n): string {
    return number_format((float) $n, 2, '.', "'");
  }

  private static function t(string $text, array $params = []): string {
    return ts($text, $params + ['domain' => CRM_SwissQRInvoice_Utils::DOMAIN]);
  }

}
