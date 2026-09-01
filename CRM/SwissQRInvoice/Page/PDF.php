<?php
class CRM_SwissQRInvoice_Page_PDF extends CRM_Core_Page {
  public function run() {
    $id = (int) CRM_Utils_Request::retrieve('id','Integer');
    if (!$id) CRM_Core_Error::fatal('ID manquant.');
    $invoice = CRM_SwissQRInvoice_BAO_Invoice::getById($id);
    if (!$invoice) CRM_Core_Error::fatal('Facture introuvable.');

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
