<?php
/**
 * Structured payment references for Swiss QR-bills.
 *
 * Every bill carries a reference that identifies what it is for, so that a
 * payment found in a bank file (camt.053 / camt.054) can be matched back to
 * CiviCRM without human intervention.
 *
 * Two kinds of bill exist:
 *   - an invoice      → identifies the invoice
 *   - a donation slip → identifies the donor contact (and the mailing it came from)
 *
 * The reference format depends on the IBAN:
 *   - a regular IBAN uses an ISO 11649 creditor reference (SCOR), e.g. RF29I0000000123
 *   - a QR-IBAN must use a 27-digit QR reference (QRR), e.g. 00 00000 00000 10000 00012 3xxxx
 *
 * Payload layout — do not change once invoices are in circulation, bank files
 * will keep arriving with the old references for months:
 *
 *   SCOR invoice   I + invoice id (10 digits)                         → RFxxI0000000123
 *   SCOR donation  D + contact id (10 digits) + M + mailing id (6)    → RFxxD0000004567M000012
 *   QRR  invoice   1 + invoice id (10 digits)
 *   QRR  donation  2 + contact id (10 digits) + mailing id (6 digits)
 */
class CRM_SwissQRInvoice_Reference {

  const KIND_INVOICE  = 'invoice';
  const KIND_DONATION = 'donation';

  /**
   * Build the sprain PaymentReference object for a bill.
   *
   * @param string $iban   Creditor IBAN (decides between SCOR and QRR).
   * @param string $kind   self::KIND_INVOICE or self::KIND_DONATION.
   * @param array  $ids    ['invoice_id' => n] or ['contact_id' => n, 'mailing_id' => n].
   */
  public static function build(string $iban, string $kind, array $ids): \Sprain\SwissQrBill\DataGroup\Element\PaymentReference {
    $ref = self::generate($iban, $kind, $ids);
    $type = self::isQrIban($iban)
      ? \Sprain\SwissQrBill\DataGroup\Element\PaymentReference::TYPE_QR
      : \Sprain\SwissQrBill\DataGroup\Element\PaymentReference::TYPE_SCOR;
    return \Sprain\SwissQrBill\DataGroup\Element\PaymentReference::create($type, $ref);
  }

  /**
   * The reference string, as printed on the bill and as it will appear in bank files.
   */
  public static function generate(string $iban, string $kind, array $ids): string {
    CRM_SwissQRInvoice_Utils::loadLibraries();
    if (self::isQrIban($iban)) {
      return \Sprain\SwissQrBill\Reference\QrPaymentReferenceGenerator::generate(
        NULL,
        self::qrrPayload($kind, $ids)
      );
    }
    return \Sprain\SwissQrBill\Reference\RfCreditorReferenceGenerator::generate(
      self::scorPayload($kind, $ids)
    );
  }

  /**
   * Decode a reference found in a bank file.
   *
   * Tolerant of spaces and lower case, as banks and e-banking UIs vary.
   *
   * @return array|null
   *   ['kind' => 'invoice',  'invoice_id' => 123]
   *   ['kind' => 'donation', 'contact_id' => 4567, 'mailing_id' => 12]
   *   NULL if the string is not one of our references.
   */
  public static function parse(string $reference): ?array {
    $r = strtoupper(preg_replace('/\s+/', '', $reference));

    // SCOR: RF + 2 check digits + payload
    if (preg_match('/^RF\d{2}I(\d{10})$/', $r, $m)) {
      return ['kind' => self::KIND_INVOICE, 'invoice_id' => (int) $m[1]];
    }
    if (preg_match('/^RF\d{2}D(\d{10})M(\d{6})$/', $r, $m)) {
      return ['kind' => self::KIND_DONATION, 'contact_id' => (int) $m[1], 'mailing_id' => (int) $m[2]];
    }

    // QRR: 27 digits, zero-padded payload + 1 check digit
    if (preg_match('/^\d{27}$/', $r)) {
      $payload = ltrim(substr($r, 0, 26), '0');
      if (preg_match('/^1(\d{10})$/', $payload, $m)) {
        return ['kind' => self::KIND_INVOICE, 'invoice_id' => (int) $m[1]];
      }
      if (preg_match('/^2(\d{10})(\d{6})$/', $payload, $m)) {
        return ['kind' => self::KIND_DONATION, 'contact_id' => (int) $m[1], 'mailing_id' => (int) $m[2]];
      }
    }
    return NULL;
  }

  public static function isQrIban(string $iban): bool {
    $clean = strtoupper(preg_replace('/\s+/', '', $iban));
    if (!preg_match('/^(CH|LI)\d{2}(\d{5})/', $clean, $m)) {
      return FALSE;
    }
    $iid = (int) $m[2];
    return $iid >= 30000 && $iid <= 31999;
  }

  private static function scorPayload(string $kind, array $ids): string {
    if ($kind === self::KIND_INVOICE) {
      return 'I' . self::pad($ids['invoice_id'] ?? 0, 10);
    }
    return 'D' . self::pad($ids['contact_id'] ?? 0, 10) . 'M' . self::pad($ids['mailing_id'] ?? 0, 6);
  }

  private static function qrrPayload(string $kind, array $ids): string {
    if ($kind === self::KIND_INVOICE) {
      return '1' . self::pad($ids['invoice_id'] ?? 0, 10);
    }
    return '2' . self::pad($ids['contact_id'] ?? 0, 10) . self::pad($ids['mailing_id'] ?? 0, 6);
  }

  private static function pad($n, int $len): string {
    $n = (int) $n;
    if ($n < 0 || strlen((string) $n) > $len) {
      throw new CRM_Core_Exception("Reference component out of range: {$n}");
    }
    return str_pad((string) $n, $len, '0', STR_PAD_LEFT);
  }

}
