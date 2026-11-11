<?php
/**
 * Sending QR invoices by email.
 *
 * Shared by the one-invoice screen and the bulk action, so that a bulk send
 * produces exactly the same message as a single send: the message template
 * `swissqrinvoice_send`, with the QR invoice attached.
 */
class CRM_SwissQRInvoice_Sender {

  const DOMAIN = 'ch.ipik.swissQRinvoice';

  /**
   * Subject and HTML body for an invoice, tokens resolved.
   *
   * @return array ['subject' => string, 'html' => string]
   */
  public static function render(array $invoice): array {
    [$subject, $bodyHtml] = self::loadTemplate();

    $vars = [
      '{contact_name}'      => $invoice['contact_name'] ?? '',
      '{invoice_number}'    => $invoice['invoice_number'],
      '{invoice_date}'      => $invoice['invoice_date'] ? date('d.m.Y', strtotime($invoice['invoice_date'])) : '',
      '{amount_due}'        => number_format((float) $invoice['amount_due'], 2, '.', "'"),
      '{organization_name}' => Civi::settings()->get('swissqr_signatory_name') ?? '',
    ];
    $subject  = strtr($subject, $vars);
    $bodyHtml = strtr($bodyHtml, $vars);

    try {
      $contactId = (int) $invoice['contact_id'];
      $cd = civicrm_api3('Contact', 'getsingle', [
        'id'     => $contactId,
        'return' => 'display_name,first_name,last_name,email,email_greeting_display,postal_greeting_display',
      ]);
      // Greeting fields are not always returned by the API — read them directly.
      foreach (['email_greeting_display', 'postal_greeting_display'] as $field) {
        if (empty($cd[$field])) {
          $cd[$field] = (string) CRM_Core_DAO::singleValueQuery(
            "SELECT {$field} FROM civicrm_contact WHERE id = %1",
            [1 => [$contactId, 'Integer']]
          );
        }
      }
      $tokenList = CRM_Utils_Token::getTokens($subject . $bodyHtml);
      $subject   = CRM_Utils_Token::replaceContactTokens($subject, $cd, FALSE, $tokenList);
      $bodyHtml  = CRM_Utils_Token::replaceContactTokens($bodyHtml, $cd, FALSE, $tokenList);
      // Greetings are not handled by replaceContactTokens.
      $greetings = [
        '{contact.email_greeting_display}'  => $cd['email_greeting_display'] ?: ($cd['display_name'] ?? ''),
        '{contact.postal_greeting_display}' => $cd['postal_greeting_display'] ?: ($cd['display_name'] ?? ''),
      ];
      $subject  = strtr($subject, $greetings);
      $bodyHtml = strtr($bodyHtml, $greetings);
    }
    catch (Exception $e) {
      CRM_Core_Error::debug_log_message('SwissQRInvoice token error: ' . $e->getMessage());
    }

    return ['subject' => $subject, 'html' => $bodyHtml];
  }

  /**
   * Send one invoice. Marks it as sent on success.
   *
   * @param string|null $error  Filled with a human-readable reason on failure.
   */
  /**
   * @param string|null $cc  Explicit copies. NULL falls back to the standing
   *   copies from the settings; an empty string means "no copy at all".
   * @param string|null $bcc Same, for blind copies.
   */
  public static function send(array $invoice, string $toEmail, string $subject, string $bodyHtml, ?string &$error = NULL, ?string $cc = NULL, ?string $bcc = NULL): bool {
    if (!$toEmail) {
      $error = ts('No email address for this contact.', ['domain' => self::DOMAIN]);
      return FALSE;
    }
    $fileName = preg_replace('/[^A-Za-z0-9_.-]/', '_',
      ts('Invoice', ['domain' => self::DOMAIN]) . '-' . $invoice['invoice_number']) . '.pdf';

    try {
      $pdf     = (new CRM_SwissQRInvoice_PDF_Generator($invoice))->generate();
      $tmpFile = CRM_SwissQRInvoice_Utils::writeTempPdf($pdf, $fileName);
    }
    catch (Throwable $e) {
      $error = $e->getMessage();
      CRM_Core_Error::debug_log_message('SwissQRInvoice: PDF for invoice ' . $invoice['invoice_number'] . ' failed: ' . $error);
      return FALSE;
    }

    $domain     = CRM_Core_BAO_Domain::getNameAndEmail();
    $mailParams = [
      'from'        => '"' . addslashes($domain[0]) . '" <' . $domain[1] . '>',
      'toEmail'     => $toEmail,
      'subject'     => $subject,
      'html'        => $bodyHtml,
      'text'        => strip_tags($bodyHtml),
      'attachments' => [['fullPath' => $tmpFile, 'mime_type' => 'application/pdf', 'cleanName' => $fileName]],
    ];

    // Standing copies, from the settings. CiviCRM strips the Bcc header before
    // delivery and keeps the address in the envelope only, so a blind copy
    // stays invisible to the other recipients.
    $standing = self::standingCopies();
    foreach (['cc' => $cc, 'bcc' => $bcc] as $key => $explicit) {
      $list = $explicit === NULL ? $standing[$key] : self::cleanAddressList($explicit);
      if ($list !== '') {
        $mailParams[$key] = $list;
      }
    }
    $pdfSize = strlen($pdf);
    $sent    = CRM_Utils_Mail::send($mailParams);
    @unlink($tmpFile);

    if ($sent) {
      CRM_SwissQRInvoice_BAO_Invoice::recordSent((int) $invoice['id'], $toEmail);
      return TRUE;
    }

    // CRM_Utils_Mail::send() returns FALSE without an explanation. These are the
    // three reasons it can do so, so log enough to tell them apart.
    $aborted = !empty($mailParams['abortMailSend']);
    $mailer  = 'none';
    try {
      $service = Civi::service('pear_mail');
      $mailer  = is_object($service) ? get_class($service) : 'none';
    }
    catch (Throwable $e) {
    }
    CRM_Core_Error::debug_log_message(sprintf(
      'SwissQRInvoice: sending invoice %s failed - from=[%s] to=[%s] pdf=%d bytes mailer=%s aborted_by_hook=%s',
      $invoice['invoice_number'], $mailParams['from'], $toEmail, $pdfSize, $mailer, $aborted ? 'yes' : 'no'
    ));
    $error = $aborted
      ? ts('Another extension blocked this email (hook_civicrm_alterMailParams). Check the SMTP routing configuration for the sender address.', ['domain' => self::DOMAIN])
      : ts('The mail server refused the message. See the CiviCRM log.', ['domain' => self::DOMAIN]);
    return FALSE;
  }

  /**
   * Comma-separated list of valid addresses, from a free-text setting.
   *
   * Separators may be commas, semicolons or newlines. Invalid entries are
   * dropped and logged rather than failing the send: a typo in the settings
   * must not stop every invoice from going out.
   */
  public static function cleanAddressList(string $raw): string {
    $kept = [];
    foreach (preg_split('/[,;\r\n]+/', $raw) as $candidate) {
      $candidate = trim($candidate);
      if ($candidate === '') {
        continue;
      }
      if (filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
        $kept[] = $candidate;
      }
      else {
        CRM_Core_Error::debug_log_message('SwissQRInvoice: ignoring invalid copy address "' . $candidate . '" from the settings.');
      }
    }
    return implode(', ', array_unique($kept));
  }

  /**
   * The standing copies from the settings, ready to use.
   *
   * Each one merges the selected contacts' addresses with the free-text
   * addresses, without duplicates.
   *
   * @return array ['cc' => string, 'bcc' => string]
   */
  public static function standingCopies(): array {
    $out = [];
    foreach (['cc' => 'swissqr_cc_contact_ids', 'bcc' => 'swissqr_bcc_contact_ids'] as $key => $setting) {
      $addresses = self::contactAddresses((string) Civi::settings()->get($setting));
      $out[$key] = implode(', ', array_unique($addresses));
    }
    return $out;
  }

  /**
   * Primary email addresses of the contacts selected in the settings.
   *
   * Resolved at send time rather than stored, so a contact who changes address
   * keeps receiving the copies. Contacts that must not be emailed — no address,
   * address on hold, "do not email", deleted contact — are skipped and logged:
   * a missing copy must not stop an invoice from going out.
   *
   * @param string $contactIds Comma-separated contact ids, as the picker stores them.
   */
  public static function contactAddresses(string $contactIds): array {
    $ids = array_filter(array_map('intval', preg_split('/[^0-9]+/', $contactIds)));
    if (!$ids) {
      return [];
    }
    try {
      $result = civicrm_api3('Contact', 'get', [
        'id'           => ['IN' => $ids],
        'is_deleted'   => 0,
        'do_not_email' => 0,
        'return'       => ['id', 'display_name'],
        'options'      => ['limit' => 0],
      ]);
    }
    catch (Exception $e) {
      CRM_Core_Error::debug_log_message('SwissQRInvoice: could not resolve copy contacts: ' . $e->getMessage());
      return [];
    }

    $names = [];
    foreach ($result['values'] as $contact) {
      $names[(int) $contact['id']] = $contact['display_name'] ?? '?';
    }
    foreach (array_diff($ids, array_keys($names)) as $missing) {
      CRM_Core_Error::debug_log_message(
        'SwissQRInvoice: copy contact id ' . $missing . ' is deleted or marked "do not email" — skipped.'
      );
    }
    if (!$names) {
      return [];
    }

    // Primary address only, and never one on hold.
    try {
      $emails = civicrm_api3('Email', 'get', [
        'contact_id' => ['IN' => array_keys($names)],
        'is_primary' => 1,
        'on_hold'    => 0,
        'return'     => ['contact_id', 'email'],
        'options'    => ['limit' => 0],
      ]);
    }
    catch (Exception $e) {
      CRM_Core_Error::debug_log_message('SwissQRInvoice: could not resolve copy addresses: ' . $e->getMessage());
      return [];
    }

    $addresses = [];
    foreach ($emails['values'] as $row) {
      if (!empty($row['email'])) {
        $addresses[(int) $row['contact_id']] = $row['email'];
      }
    }
    foreach (array_diff(array_keys($names), array_keys($addresses)) as $without) {
      CRM_Core_Error::debug_log_message(sprintf(
        'SwissQRInvoice: copy contact %s (id %d) has no usable primary email — skipped.',
        $names[$without], $without
      ));
    }
    return array_values($addresses);
  }

  /**
   * Primary email of a contact, or an empty string.
   */
  public static function primaryEmail(int $contactId): string {
    try {
      $email = civicrm_api3('Email', 'getsingle', [
        'contact_id' => $contactId,
        'is_primary' => 1,
        'return'     => 'email',
      ]);
      return (string) ($email['email'] ?? '');
    }
    catch (Exception $e) {
      return '';
    }
  }

  /**
   * Subject and body of the `swissqrinvoice_send` message template,
   * falling back to the legacy settings if it has been deleted.
   */
  public static function loadTemplate(): array {
    $tplId = (int) CRM_Core_DAO::singleValueQuery(
      "SELECT id FROM civicrm_msg_template WHERE msg_title = 'swissqrinvoice_send' AND is_active = 1 LIMIT 1"
    );
    if ($tplId) {
      try {
        $tpl = civicrm_api3('MessageTemplate', 'getsingle', ['id' => $tplId]);
        return [$tpl['msg_subject'] ?? '', $tpl['msg_html'] ?? ''];
      }
      catch (Exception $e) {
      }
    }
    return [
      Civi::settings()->get('swissqr_email_subject_template') ?: 'Facture N° {invoice_number}',
      Civi::settings()->get('swissqr_email_body_template') ?: nl2br(CRM_SwissQRInvoice_Utils::defaultEmailBody()),
    ];
  }

}
