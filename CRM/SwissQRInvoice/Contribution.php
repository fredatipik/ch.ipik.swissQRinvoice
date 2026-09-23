<?php
/**
 * Bridge between QR invoices and native CiviCRM contributions.
 *
 * Model (the same one CiviCRM uses for pay-later and online payments):
 *   - when an invoice is issued, a *Pending* contribution is created for its total;
 *   - payments are recorded against that contribution (Payment API), by hand or
 *     by a bank-file import such as CiviBanking;
 *   - CiviCRM moves the contribution to Partially paid / Completed, and the
 *     invoice follows.
 *
 * The contribution is the accounting truth: it is what appears in contribution
 * searches, in the native "Email invoice" action and in accounting batches.
 * The invoice table keeps what CiviCRM has no place for: line layout,
 * discounts, the PDF, the QR slip.
 *
 * The contribution's invoice_id holds the QR reference printed on the slip,
 * so a bank-file import can find it by that single field.
 */
class CRM_SwissQRInvoice_Contribution {

  /**
   * Make sure the invoice has a pending contribution; returns its id.
   * Returns 0 for invoices with nothing to collect (total ≤ 0).
   */
  public static function ensurePending(array $invoice): int {
    if (!empty($invoice['payment_contribution_id'])) {
      return (int) $invoice['payment_contribution_id'];
    }
    $total = round((float) ($invoice['total'] ?? 0), 2);
    if ($total <= 0) {
      return 0;
    }

    $reference = self::referenceFor($invoice);
    $contributionId = 0;

    // Adopt a contribution the invoice was created from (e.g. by a booking), if still open.
    if (!empty($invoice['contribution_id'])) {
      $existing = self::getContribution((int) $invoice['contribution_id']);
      if ($existing && $existing['status'] === 'Pending' && (int) $existing['contact_id'] === (int) $invoice['contact_id']) {
        $contributionId = (int) $existing['id'];
        if (empty($existing['invoice_id'])) {
          civicrm_api3('Contribution', 'create', [
            'id'             => $contributionId,
            'invoice_id'     => $reference,
            'invoice_number' => $invoice['invoice_number'],
          ]);
        }
      }
    }

    if (!$contributionId) {
      $result = civicrm_api3('Contribution', 'create', [
        'contact_id'             => (int) $invoice['contact_id'],
        'financial_type_id'      => CRM_SwissQRInvoice_BAO_Invoice::getInvoiceFinancialTypeId(),
        'total_amount'           => $total,
        'receive_date'           => $invoice['invoice_date'],
        'contribution_status_id' => 'Pending',
        'is_pay_later'           => 1,
        'payment_instrument_id'  => 'EFT',
        'invoice_id'             => $reference,
        'invoice_number'         => $invoice['invoice_number'],
        'source'                 => ts('QR invoice %1', [1 => $invoice['invoice_number'], 'domain' => CRM_SwissQRInvoice_Utils::DOMAIN]),
      ]);
      $contributionId = (int) $result['id'];
    }

    CRM_Core_DAO::executeQuery(
      'UPDATE civicrm_swissqr_invoice SET payment_contribution_id = %1 WHERE id = %2',
      [1 => [$contributionId, 'Integer'], 2 => [(int) $invoice['id'], 'Integer']]
    );
    return $contributionId;
  }

  /**
   * Record a payment against the invoice's contribution.
   * CiviCRM decides the new status (Partially paid / Completed).
   */
  public static function recordPayment(array $invoice, float $amount, string $date): void {
    $contributionId = self::ensurePending($invoice);
    if (!$contributionId) {
      throw new CRM_Core_Exception(ts('This invoice has nothing to collect.', ['domain' => CRM_SwissQRInvoice_Utils::DOMAIN]));
    }
    civicrm_api3('Payment', 'create', [
      'contribution_id'       => $contributionId,
      'total_amount'          => round($amount, 2),
      'trxn_date'             => $date,
      'payment_instrument_id' => 'EFT',
    ]);
    self::syncInvoice($contributionId);
  }

  /**
   * Cancel the pending contribution when the invoice is cancelled.
   * A contribution that already received money is left alone.
   */
  public static function cancel(array $invoice): void {
    if (empty($invoice['payment_contribution_id'])) {
      return;
    }
    $c = self::getContribution((int) $invoice['payment_contribution_id']);
    if ($c && $c['status'] === 'Pending') {
      civicrm_api3('Contribution', 'create', [
        'id'                     => (int) $c['id'],
        'contribution_status_id' => 'Cancelled',
        'cancel_date'            => date('Y-m-d H:i:s'),
      ]);
    }
  }

  /**
   * Bring one invoice in line with its contribution. Called from hook_civicrm_post.
   */
  public static function syncInvoice(int $contributionId): void {
    $invoiceId = (int) CRM_Core_DAO::singleValueQuery(
      'SELECT id FROM civicrm_swissqr_invoice WHERE payment_contribution_id = %1',
      [1 => [$contributionId, 'Integer']]
    );
    if (!$invoiceId) {
      return;
    }
    $c = self::getContribution($contributionId);
    if (!$c) {
      return;
    }
    $paid = CRM_Core_BAO_FinancialTrxn::getTotalPayments($contributionId);

    switch ($c['status']) {
      case 'Completed':
        CRM_Core_DAO::executeQuery(
          "UPDATE civicrm_swissqr_invoice
             SET status = 'paid', amount_paid = %1, paid_date = %2
           WHERE id = %3 AND status <> 'paid'",
          [
            1 => [$paid > 0 ? $paid : (float) $c['total_amount'], 'Float'],
            2 => [substr((string) ($c['receive_date'] ?: date('Y-m-d')), 0, 10), 'String'],
            3 => [$invoiceId, 'Integer'],
          ]
        );
        break;

      case 'Partially paid':
        CRM_Core_DAO::executeQuery(
          'UPDATE civicrm_swissqr_invoice SET amount_paid = %1 WHERE id = %2',
          [1 => [$paid, 'Float'], 2 => [$invoiceId, 'Integer']]
        );
        break;

      case 'Cancelled':
        CRM_Core_DAO::executeQuery(
          "UPDATE civicrm_swissqr_invoice SET status = 'cancelled' WHERE id = %1 AND status <> 'paid'",
          [1 => [$invoiceId, 'Integer']]
        );
        break;
    }
  }

  /**
   * Safety net: catch up on any status change the post hook might have missed
   * (bulk imports, direct SQL, another extension). Cheap — one query per status.
   */
  public static function syncAll(): void {
    $completed = self::statusId('Completed');
    $cancelled = self::statusId('Cancelled');
    if ($completed) {
      CRM_Core_DAO::executeQuery(
        "UPDATE civicrm_swissqr_invoice i
           JOIN civicrm_contribution c ON c.id = i.payment_contribution_id
            SET i.status = 'paid',
                i.amount_paid = c.total_amount,
                i.paid_date = DATE(c.receive_date)
          WHERE i.status IN ('draft','sent') AND c.contribution_status_id = %1",
        [1 => [$completed, 'Integer']]
      );
    }
    if ($cancelled) {
      CRM_Core_DAO::executeQuery(
        "UPDATE civicrm_swissqr_invoice i
           JOIN civicrm_contribution c ON c.id = i.payment_contribution_id
            SET i.status = 'cancelled'
          WHERE i.status IN ('draft','sent') AND c.contribution_status_id = %1",
        [1 => [$cancelled, 'Integer']]
      );
    }
  }

  /**
   * One-off catch-up for invoices sent before v0.30: give each unpaid sent
   * invoice its pending contribution, so bank imports can match it.
   *
   *   cv ev 'print_r(CRM_SwissQRInvoice_Contribution::backfill());'          (dry run)
   *   cv ev 'print_r(CRM_SwissQRInvoice_Contribution::backfill(FALSE));'     (apply)
   */
  public static function backfill(bool $dryRun = TRUE): array {
    $ids = CRM_Core_DAO::executeQuery(
      "SELECT id FROM civicrm_swissqr_invoice
        WHERE status = 'sent' AND payment_contribution_id IS NULL ORDER BY id"
    )->fetchMap('id', 'id');
    $report = [];
    foreach ($ids as $id) {
      $invoice = CRM_SwissQRInvoice_BAO_Invoice::getById((int) $id);
      $report[$invoice['invoice_number']] = $dryRun ? 'would create' : 'contribution ' . self::ensurePending($invoice);
    }
    return $report;
  }

  /**
   * The reference printed on this invoice's slip, as stored in contribution.invoice_id.
   */
  public static function referenceFor(array $invoice): string {
    $iban = (string) Civi::settings()->get('swissqr_iban');
    return CRM_SwissQRInvoice_Reference::generate($iban, CRM_SwissQRInvoice_Reference::KIND_INVOICE, ['invoice_id' => (int) $invoice['id']]);
  }

  private static function getContribution(int $id): ?array {
    try {
      $c = civicrm_api3('Contribution', 'getsingle', [
        'id'     => $id,
        'return' => ['id', 'contact_id', 'total_amount', 'receive_date', 'contribution_status_id', 'invoice_id'],
      ]);
    }
    catch (Exception $e) {
      return NULL;
    }
    $c['status'] = CRM_Core_PseudoConstant::getName(
      'CRM_Contribute_BAO_Contribution', 'contribution_status_id', $c['contribution_status_id']
    );
    return $c;
  }

  private static function statusId(string $name): int {
    return (int) CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', $name);
  }

}
