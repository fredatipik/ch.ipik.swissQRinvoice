<?php
class CRM_SwissQRInvoice_Page_PDF extends CRM_Core_Page {
  public function run() {
    $id = (int) CRM_Utils_Request::retrieve('id','Integer');
    if (!$id) throw new CRM_Core_Exception(ts('Missing ID.', ['domain' => 'ch.ipik.swissQRinvoice']));
    $invoice = CRM_SwissQRInvoice_BAO_Invoice::getById($id);
    if (!$invoice) throw new CRM_Core_Exception(ts('Invoice not found.', ['domain' => 'ch.ipik.swissQRinvoice']));

    $generator = new CRM_SwissQRInvoice_PDF_Generator($invoice);
    $pdf = $generator->generate();

    $filename = "Facture-{$invoice['invoice_number']}-{$invoice['contact_name']}.pdf";
    $filename = preg_replace('/[^a-zA-Z0-9_\-.]/', '_', $filename);

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    header('Content-Length: '.strlen($pdf));
    echo $pdf;
    CRM_Utils_System::civiExit();
  }
}
