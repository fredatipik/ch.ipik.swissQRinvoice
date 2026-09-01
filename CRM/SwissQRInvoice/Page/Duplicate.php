<?php
class CRM_SwissQRInvoice_Page_Duplicate extends CRM_Core_Page {
  public function run() {
    if (!CRM_Core_Permission::check('edit swissqr invoices')) {
      CRM_Core_Error::fatal(ts('Permission refusée.'));
    }
    $id = (int) CRM_Utils_Request::retrieve('id', 'Integer');
    if (!$id) CRM_Core_Error::fatal(ts('ID manquant.'));
    $new = CRM_SwissQRInvoice_BAO_Invoice::duplicate($id);
    CRM_Core_Session::setStatus(
      ts('Facture dupliquée : %1', [1 => $new['invoice_number']]),
      ts('Succès'), 'success'
    );
    $url = CRM_Utils_System::url('civicrm/swissqr/invoice/edit', 'id=' . $new['id'] . '&reset=1&duplicate=1');
    CRM_Utils_System::redirect($url);
    CRM_Utils_System::civiExit();
  }
}
