<?php
/**
 * Helpers partagés pour com.ipik.swissQRinvoice.
 */
class CRM_SwissQRInvoice_Utils {

  /**
   * Génère le prochain numéro de facture selon le format configuré.
   * Format par défaut : {YEAR}-{SEQ:4}  →  2025-0091
   */
  public static function nextInvoiceNumber(): string {
    $format = Civi::settings()->get('swissqr_invoice_number_format') ?? '{YEAR}-{SEQ:4}';
    $seq    = (int) (Civi::settings()->get('swissqr_invoice_seq') ?? 0) + 1;
    Civi::settings()->set('swissqr_invoice_seq', $seq);

    $number = $format;
    $number = str_replace('{YEAR}', date('Y'), $number);

    // {SEQ:N} → zero-padded sequence
    if (preg_match('/\{SEQ:(\d+)\}/', $number, $m)) {
      $number = str_replace($m[0], str_pad($seq, (int)$m[1], '0', STR_PAD_LEFT), $number);
    } elseif (strpos($number, '{SEQ}') !== false) {
      $number = str_replace('{SEQ}', $seq, $number);
    }

    return $number;
  }

  /**
   * Retourne les settings de l'extension.
   */
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
    ];
    $settings = [];
    foreach ($keys as $key) {
      $settings[$key] = Civi::settings()->get($key);
    }
    return $settings;
  }

  /**
   * Retourne les données du contact organisation expéditeur.
   */
  public static function getOrgContact(?int $orgContactId = null): array {
    if (!$orgContactId) {
      $orgContactId = (int) Civi::settings()->get('swissqr_org_contact_id');
    }
    if (!$orgContactId) {
      return [];
    }
    $result = civicrm_api3('Contact', 'getsingle', [
      'id'     => $orgContactId,
      'return' => 'display_name,organization_name,phone,email',
    ]);
    // Récupérer l'adresse primaire séparément
    try {
      $address = civicrm_api3('Address', 'getsingle', [
        'contact_id' => $orgContactId,
        'is_primary' => 1,
        'return'     => 'street_address,city,postal_code',
      ]);
      $result['street_address'] = $address['street_address'] ?? '';
      $result['city']           = $address['city'] ?? '';
      $result['postal_code']    = $address['postal_code'] ?? '';
    } catch (Exception $e) {}
    return $result ?? [];
  }

  /**
   * Formate un montant CHF : 180.00 → "CHF 180.00"
   */
  public static function formatAmount(float $amount, bool $withCurrency = true): string {
    $formatted = number_format($amount, 2, '.', "'");
    return $withCurrency ? "CHF {$formatted}" : $formatted;
  }

  /**
   * Retourne le template d'email par défaut (français).
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
}
