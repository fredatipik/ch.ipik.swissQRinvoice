<?php
class CRM_SwissQRInvoice_Page_CancelDuplicate extends CRM_Core_Page {
  public function run() {
    if (!CRM_Core_Permission::check('edit swissqr invoices')) {
      CRM_Core_Error::fatal(ts('Permission refusée.', ['domain' => 'ch.ipik.swissQRinvoice']));
    }
    $id = (int) CRM_Utils_Request::retrieve('id', 'Integer');
    if ($id) {
      CRM_Core_DAO::executeQuery(
        "DELETE FROM civicrm_swissqr_invoice WHERE id = %1 AND status = 'draft'",
        [1 => [$id, 'Integer']]
      );
    }
    CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/swissqr/invoice/list', 'reset=1'));
  }
}
