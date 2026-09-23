<?php
/**
 * Hooks into CiviCRM's native "Email invoice" action on contributions.
 *
 * When the contribution belongs to a QR invoice, the QR invoice PDF is attached
 * (and, by default, replaces CiviCRM's own HTML-rendered invoice PDF). This works
 * the same whether the email is sent directly or through a queue such as the
 * one Invoice Helper provides, since both end in CRM_Utils_Mail::send().
 */
class CRM_SwissQRInvoice_Mail {

  /**
   * Called from hook_civicrm_alterMailParams, context 'singleEmail'.
   * At that point CiviCRM's own attachments are already in $params.
   */
  public static function alterNativeInvoiceEmail(array &$params): void {
    $workflow = $params['workflow'] ?? $params['valueName'] ?? '';
    if ($workflow !== 'contribution_invoice_receipt') {
      return;
    }
    $contributionId = (int) ($params['tokenContext']['contributionId'] ?? 0);
    if (!$contributionId || empty($params['toEmail'])) {
      return;
    }
    $invoiceId = (int) CRM_Core_DAO::singleValueQuery(
      'SELECT id FROM civicrm_swissqr_invoice WHERE payment_contribution_id = %1',
      [1 => [$contributionId, 'Integer']]
    );
    if (!$invoiceId) {
      return;
    }

    try {
      $invoice = CRM_SwissQRInvoice_BAO_Invoice::getById($invoiceId);
      $pdf     = (new CRM_SwissQRInvoice_PDF_Generator($invoice))->generate();
    }
    catch (Throwable $e) {
      // Never block the email: CiviCRM's own invoice still goes out.
      CRM_Core_Error::debug_log_message('SwissQRInvoice: could not attach QR invoice to contribution '
        . $contributionId . ': ' . $e->getMessage());
      return;
    }

    $cleanName = preg_replace('/[^A-Za-z0-9_.-]/', '_',
      ts('Invoice', ['domain' => CRM_SwissQRInvoice_Utils::DOMAIN]) . '-' . $invoice['invoice_number']) . '.pdf';
    $path = CRM_SwissQRInvoice_Utils::writeTempPdf($pdf, $cleanName);
    register_shutdown_function(static function () use ($path) {
      @unlink($path);
    });

    $attachments = $params['attachments'] ?? [];
    if (Civi::settings()->get('swissqr_replace_native_invoice') ?? TRUE) {
      $native = array_filter([$params['PDFFilename'] ?? NULL, 'Invoice.pdf']);
      $attachments = array_values(array_filter($attachments, static function ($a) use ($native) {
        return !in_array($a['cleanName'] ?? '', $native, TRUE);
      }));
    }
    $attachments[] = ['fullPath' => $path, 'mime_type' => 'application/pdf', 'cleanName' => $cleanName];
    $params['attachments'] = $attachments;

    CRM_SwissQRInvoice_BAO_Invoice::recordSent($invoiceId, (string) $params['toEmail']);
  }

}
