<?php
/**
 * Shared helpers for ch.ipik.swissQRinvoice.
 */
class CRM_SwissQRInvoice_Utils {

  const DOMAIN = 'ch.ipik.swissQRinvoice';

  /**
   * Next invoice number according to the configured format.
   * Default format: {YEAR}-{SEQ:4}  →  2026-0091
   */
  public static function nextInvoiceNumber(): string {
    $format = Civi::settings()->get('swissqr_invoice_number_format') ?: '{YEAR}-{SEQ:4}';
    $seq    = (int) (Civi::settings()->get('swissqr_invoice_seq') ?? 0) + 1;
    Civi::settings()->set('swissqr_invoice_seq', $seq);

    $number = str_replace('{YEAR}', date('Y'), $format);
    if (preg_match('/\{SEQ:(\d+)\}/', $number, $m)) {
      $number = str_replace($m[0], str_pad((string) $seq, (int) $m[1], '0', STR_PAD_LEFT), $number);
    }
    elseif (strpos($number, '{SEQ}') !== FALSE) {
      $number = str_replace('{SEQ}', (string) $seq, $number);
    }
    return $number;
  }

  public static function getSettings(): array {
    $keys = [
      'swissqr_org_contact_id',
      'swissqr_iban',
      'swissqr_logo_path',
      'swissqr_logo_offset_x',
      'swissqr_logo_margin_bottom',
      'swissqr_signature_path',
      'swissqr_signatory_name',
      'swissqr_vat_note',
      'swissqr_invoice_number_format',
      'swissqr_invoice_seq',
      'swissqr_allowed_roles',
      'swissqr_qr_reference_template',
      'swissqr_email_subject_template',
      'swissqr_email_body_template',
      'swissqr_default_lang',
      'swissqr_single_page_pdf',
      'swissqr_signature_offset_y',
      'swissqr_replace_native_invoice',
    ];
    $settings = [];
    foreach ($keys as $key) {
      $settings[$key] = Civi::settings()->get($key);
    }
    return $settings;
  }

  /**
   * Sender organisation: name, contact details and structured address.
   */
  public static function getOrgContact(?int $orgContactId = NULL): array {
    if (!$orgContactId) {
      $orgContactId = (int) Civi::settings()->get('swissqr_org_contact_id');
    }
    if (!$orgContactId) {
      return [];
    }
    try {
      $result = civicrm_api3('Contact', 'getsingle', [
        'id'     => $orgContactId,
        'return' => 'display_name,organization_name,phone,email',
      ]);
    }
    catch (Exception $e) {
      return [];
    }
    return array_merge($result, self::getPrimaryAddress($orgContactId));
  }

  /**
   * Primary address of a contact, split the way the QR-bill standard wants it.
   *
   * Since v2.3 of the Swiss QR-bill specification (21 November 2025) only
   * structured addresses are valid: street and building number in separate
   * fields, postcode and town separate, ISO country code.
   *
   * Always returns every key; values are empty strings when unknown.
   */
  public static function getPrimaryAddress(int $contactId): array {
    $out = [
      'street_address'  => '',
      'street'          => '',
      'building_number' => '',
      'postal_code'     => '',
      'city'            => '',
      'country'         => 'CH',
    ];
    if (!$contactId) {
      return $out;
    }
    try {
      $a = civicrm_api3('Address', 'getsingle', [
        'contact_id' => $contactId,
        'is_primary' => 1,
        'return'     => 'street_address,street_name,street_number,street_number_suffix,postal_code,city,country_id',
      ]);
    }
    catch (Exception $e) {
      return $out;
    }

    $out['street_address'] = trim((string) ($a['street_address'] ?? ''));
    $out['postal_code']    = trim((string) ($a['postal_code'] ?? ''));
    $out['city']           = trim((string) ($a['city'] ?? ''));

    if (!empty($a['country_id'])) {
      $iso = (string) CRM_Core_DAO::singleValueQuery(
        'SELECT iso_code FROM civicrm_country WHERE id = %1',
        [1 => [(int) $a['country_id'], 'Integer']]
      );
      if ($iso) {
        $out['country'] = strtoupper($iso);
      }
    }

    // Prefer CiviCRM's own parsed fields when street address parsing is enabled.
    if (!empty($a['street_name'])) {
      $out['street'] = trim($a['street_name']);
      $out['building_number'] = trim(($a['street_number'] ?? '') . ($a['street_number_suffix'] ?? ''));
    }
    else {
      [$out['street'], $out['building_number']] = self::splitStreet($out['street_address']);
    }
    return $out;
  }

  /**
   * "Rue du Lac 12b" → ["Rue du Lac", "12b"]; "12, rue du Lac" → ["rue du Lac", "12"].
   * Anything else is kept whole as the street, with no building number.
   */
  public static function splitStreet(string $streetAddress): array {
    $s = trim($streetAddress);
    if ($s === '') {
      return ['', ''];
    }
    // Number last (German / Swiss usage): "Bahnhofstrasse 12a", "Rue du Lac 12-14"
    if (preg_match('/^(.+?)[\s,]+(\d+\s?[a-zA-Z]?(?:\s?[-\/]\s?\d+\s?[a-zA-Z]?)?)$/u', $s, $m)) {
      return [trim($m[1], " ,"), str_replace(' ', '', $m[2])];
    }
    // Number first (French usage): "12, rue du Lac", "12bis rue du Lac"
    if (preg_match('/^(\d+\s?(?:[a-zA-Z]|bis|ter)?)[\s,]+(.+)$/u', $s, $m)) {
      return [trim($m[2], " ,"), str_replace(' ', '', $m[1])];
    }
    return [$s, ''];
  }

  /**
   * Build a structured QR-bill address, truncated to the standard's field limits.
   * Returns NULL when there is not enough data for a valid address.
   */
  public static function qrAddress(string $name, array $addr): ?\Sprain\SwissQrBill\DataGroup\Element\StructuredAddress {
    $name   = self::cut($name, 70);
    $postal = self::cut($addr['postal_code'] ?? '', 16);
    $city   = self::cut($addr['city'] ?? '', 35);
    $cty    = strtoupper(substr((string) ($addr['country'] ?? 'CH'), 0, 2)) ?: 'CH';
    if ($name === '' || $postal === '' || $city === '') {
      return NULL;
    }
    $street = self::cut($addr['street'] ?? '', 70);
    if ($street === '') {
      return \Sprain\SwissQrBill\DataGroup\Element\StructuredAddress::createWithoutStreet($name, $postal, $city, $cty);
    }
    $number = self::cut($addr['building_number'] ?? '', 16);
    return \Sprain\SwissQrBill\DataGroup\Element\StructuredAddress::createWithStreet(
      $name, $street, $number !== '' ? $number : NULL, $postal, $city, $cty
    );
  }

  /**
   * Language of the QR payment part, from the current CiviCRM locale.
   * The standard defines de, fr, it, rm and en.
   */
  public static function qrLanguage(): string {
    $locale = '';
    try {
      $locale = (string) CRM_Core_I18n::getLocale();
    }
    catch (Throwable $e) {
    }
    $lang = strtolower(substr($locale, 0, 2));
    return in_array($lang, ['de', 'fr', 'it', 'rm', 'en'], TRUE) ? $lang : 'fr';
  }

  /**
   * Load the bundled libraries (sprain/swiss-qr-bill, TCPDF).
   * Needed by anything touching references or PDFs, not just the generator.
   */
  public static function loadLibraries(): void {
    $autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
    if (file_exists($autoload)) {
      require_once $autoload;
    }
    if (!class_exists('TCPDF', FALSE)) {
      foreach ([
        dirname(__DIR__, 2) . '/vendor/tecnickcom/tcpdf/tcpdf.php',
        dirname(__DIR__, 6) . '/vendor/tecnickcom/tcpdf/tcpdf.php',
      ] as $p) {
        if (file_exists($p)) {
          require_once $p;
          break;
        }
      }
    }
  }

  /**
   * Write a PDF to a temporary file for mail attachment.
   *
   * Prefers CiviCRM's own upload directory: on shared hosting the system temp
   * directory is often outside open_basedir, and a silently failed write means
   * an email that cannot be sent.
   *
   * @return string Full path of the file written.
   * @throws CRM_Core_Exception
   */
  public static function writeTempPdf(string $content, string $baseName): string {
    $baseName = preg_replace('/[^A-Za-z0-9_.-]/', '_', $baseName);
    $dirs = array_filter([
      rtrim((string) (CRM_Core_Config::singleton()->uploadDir ?? ''), '/'),
      rtrim((string) sys_get_temp_dir(), '/'),
    ]);
    $tried = [];
    foreach ($dirs as $dir) {
      if (!is_dir($dir) || !is_writable($dir)) {
        $tried[] = "{$dir} (not writable)";
        continue;
      }
      $path = $dir . '/swissqr_' . uniqid() . '_' . $baseName;
      $written = @file_put_contents($path, $content);
      if ($written !== FALSE && $written === strlen($content)) {
        return $path;
      }
      $tried[] = "{$dir} (write failed)";
    }
    throw new CRM_Core_Exception('Could not write the invoice PDF: ' . implode(', ', $tried));
  }

  public static function formatAmount(float $amount, bool $withCurrency = TRUE): string {
    $formatted = number_format($amount, 2, '.', "'");
    return $withCurrency ? "CHF {$formatted}" : $formatted;
  }

  /**
   * Legacy fallback email body, only used when the message template is missing.
   */
  public static function defaultEmailBody(): string {
    return <<<TXT
Madame, Monsieur {contact_name},

Veuillez trouver ci-joint la facture n° {invoice_number} du {invoice_date} pour un montant de {amount_due} CHF.

Nous vous remercions de votre règlement dans les délais.

Avec nos meilleures salutations,
{organization_name}
TXT;
  }

  private static function cut(string $s, int $len): string {
    $s = trim($s);
    return function_exists('mb_substr') ? mb_substr($s, 0, $len) : substr($s, 0, $len);
  }

}
