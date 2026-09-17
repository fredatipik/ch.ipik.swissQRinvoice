<?php
class CRM_SwissQRInvoice_Page_Duplicate extends CRM_Core_Page {
  public function run() {
    if (!CRM_Core_Permission::check('edit swissqr invoices')) {
      CRM_Core_Error::fatal(ts('Permission denied.', ['domain' => 'ch.ipik.swissQRinvoice']));
    }
    $id = (int) CRM_Utils_Request::retrieve('id', 'Integer');
    if (!$id) CRM_Core_Error::fatal(ts('Missing ID.', ['domain' => 'ch.ipik.swissQRinvoice']));
    $new = CRM_SwissQRInvoice_BAO_Invoice::duplicate($id);
    CRM_Core_Session::setStatus(
      ts('Invoice duplicated: %1', ['domain' => 'ch.ipik.swissQRinvoice', 1 => $new['invoice_number']]),
      ts('Success', ['domain' => 'ch.ipik.swissQRinvoice']), 'success'
    );
    $url = CRM_Utils_System::url('civicrm/swissqr/invoice/edit', 'id=' . $new['id'] . '&reset=1&duplicate=1');
    CRM_Utils_System::redirect($url);
    CRM_Utils_System::civiExit();
  }
}
