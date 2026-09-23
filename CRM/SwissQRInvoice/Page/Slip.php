<?php
/**
 * Public download of a donation slip: civicrm/swissqr/slip?cid=…&cs=…&m=…
 *
 * Reached from the {swissqr.donation_slip_url} token in a mailing, by people
 * who are not logged in. Access is granted by the contact checksum only.
 */
class CRM_SwissQRInvoice_Page_Slip extends CRM_Core_Page {

  public function run() {
    $contactId = (int) CRM_Utils_Request::retrieve('cid', 'Positive');
    $checksum  = (string) CRM_Utils_Request::retrieve('cs', 'String');
    $mailingId = (int) CRM_Utils_Request::retrieve('m', 'Integer');

    if (!$contactId || !$checksum || !CRM_Contact_BAO_Contact_Utils::validChecksum($contactId, $checksum)) {
      CRM_Utils_System::sendInvalidRequestResponse(
        ts('This link is not valid.', ['domain' => CRM_SwissQRInvoice_Utils::DOMAIN])
      );
      return;
    }

    try {
      $pdf = CRM_SwissQRInvoice_PDF_Generator::generateDonationSlip($contactId, max(0, $mailingId));
    }
    catch (Throwable $e) {
      CRM_Core_Error::debug_log_message('SwissQRInvoice donation slip error: ' . $e->getMessage());
      CRM_Utils_System::sendInvalidRequestResponse(
        ts('The donation slip could not be generated.', ['domain' => CRM_SwissQRInvoice_Utils::DOMAIN])
      );
      return;
    }

    $filename = preg_replace('/[^A-Za-z0-9_.-]/', '_', ts('Donation-slip', ['domain' => CRM_SwissQRInvoice_Utils::DOMAIN])) . '.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($pdf));
    header('Cache-Control: private, no-store');
    echo $pdf;
    CRM_Utils_System::civiExit();
  }

}
