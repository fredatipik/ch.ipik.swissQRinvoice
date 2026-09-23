<?php
/**
 * Tokens for CiviMail and message templates.
 *
 *   {swissqr.donation_slip_url}   personal link to a one-page QR donation slip (PDF)
 *   {swissqr.donation_reference}  the reference printed on that slip
 *
 * The slip carries a reference built from the contact and the mailing, so a
 * donation arriving in a bank file can be credited to the right donor and
 * attributed to the right appeal.
 *
 * Registered in swissQRinvoice_civicrm_config().
 */
class CRM_SwissQRInvoice_Tokens {

  const ENTITY = 'swissqr';

  public static function register(\Civi\Token\Event\TokenRegisterEvent $e): void {
    $e->entity(self::ENTITY)
      ->register('donation_slip_url', ts('QR donation slip (link)', ['domain' => CRM_SwissQRInvoice_Utils::DOMAIN]))
      ->register('donation_reference', ts('QR donation slip (reference)', ['domain' => CRM_SwissQRInvoice_Utils::DOMAIN]));
  }

  public static function evaluate(\Civi\Token\Event\TokenValueEvent $e): void {
    $used = $e->getTokenProcessor()->getMessageTokens()[self::ENTITY] ?? [];
    if (!$used) {
      return;
    }
    $iban = (string) Civi::settings()->get('swissqr_iban');

    foreach ($e->getRows() as $row) {
      $contactId = (int) ($row->context['contactId'] ?? 0);
      if (!$contactId) {
        continue;
      }
      $mailingId = (int) ($row->context['mailingId'] ?? 0);

      if (in_array('donation_slip_url', $used, TRUE)) {
        $url = self::slipUrl($contactId, $mailingId);
        $row->format('text/plain')->tokens(self::ENTITY, 'donation_slip_url', $url);
        $row->format('text/html')->tokens(self::ENTITY, 'donation_slip_url', htmlspecialchars($url));
      }
      if (in_array('donation_reference', $used, TRUE)) {
        $ref = '';
        try {
          $ref = CRM_SwissQRInvoice_Reference::generate($iban, CRM_SwissQRInvoice_Reference::KIND_DONATION, [
            'contact_id' => $contactId,
            'mailing_id' => $mailingId,
          ]);
        }
        catch (Throwable $ex) {
          CRM_Core_Error::debug_log_message('SwissQRInvoice token error: ' . $ex->getMessage());
        }
        $row->format('text/plain')->tokens(self::ENTITY, 'donation_reference', $ref);
        $row->format('text/html')->tokens(self::ENTITY, 'donation_reference', htmlspecialchars($ref));
      }
    }
  }

  /**
   * Public, non-expiring link: an appeal may be opened weeks after it was sent.
   * The checksum proves the link was issued for this contact; the PDF itself
   * contains no personal data.
   */
  public static function slipUrl(int $contactId, int $mailingId = 0): string {
    $cs = CRM_Contact_BAO_Contact_Utils::generateChecksum($contactId, NULL, 'inf');
    return CRM_Utils_System::url(
      'civicrm/swissqr/slip',
      http_build_query(['cid' => $contactId, 'cs' => $cs, 'm' => $mailingId]),
      TRUE, NULL, FALSE, TRUE
    );
  }

}
