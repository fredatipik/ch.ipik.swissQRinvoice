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
  public static function send(array $invoice, string $toEmail, string $subject, string $bodyHtml, ?string &$error = NULL): bool {
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
