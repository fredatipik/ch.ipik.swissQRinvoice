<?php
class CRM_SwissQRInvoice_Page_Services extends CRM_Core_Page {
  public function run() {
    CRM_Utils_System::setTitle(ts('Prestations', ['domain' => 'ch.ipik.swissQRinvoice']));

    // Traitement POST direct
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      if (!CRM_Core_Permission::check('edit swissqr invoices')) {
        CRM_Core_Error::fatal(ts('Permission refusée.', ['domain' => 'ch.ipik.swissQRinvoice']));
      }
      $action = $_POST['action'] ?? 'save';
      if ($action === 'save') {
        $sid = (int)($_POST['sid'] ?? 0);
        CRM_SwissQRInvoice_BAO_Invoice::saveService([
          'id'          => $sid ?: null,
          'name'        => CRM_Utils_Type::validate($_POST['name'] ?? '', 'String'),
          'description' => CRM_Utils_Type::validate($_POST['description'] ?? '', 'String'),
          'unit_price'  => (float)($_POST['unit_price'] ?? 0),
          'is_active'   => 1,
        ]);
        CRM_Core_Session::setStatus(ts('Prestation enregistrée.', ['domain' => 'ch.ipik.swissQRinvoice']), ts('Succès', ['domain' => 'ch.ipik.swissQRinvoice']), 'success');
      }
      if ($action === 'delete') {
        $sid = (int)($_POST['sid'] ?? 0);
        if ($sid) CRM_SwissQRInvoice_BAO_Invoice::deleteService($sid);
        CRM_Core_Session::setStatus(ts('Prestation supprimée.', ['domain' => 'ch.ipik.swissQRinvoice']), ts('Succès', ['domain' => 'ch.ipik.swissQRinvoice']), 'success');
      }
      // Redirect POST → GET pour éviter le double submit
      CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/swissqr/services', 'reset=1'));
      return;
    }

    $editService = null;
    $editId = (int)(CRM_Utils_Request::retrieve('sid', 'Integer') ?: 0);
    $doEdit = CRM_Utils_Request::retrieve('action', 'String') === 'edit';
    if ($doEdit && $editId) {
      $dao = CRM_Core_DAO::executeQuery("SELECT * FROM civicrm_swissqr_service WHERE id=%1", [1=>[$editId,'Integer']]);
      if ($dao->fetch()) $editService = $dao->toArray();
    }

    $deleteId = (int)(CRM_Utils_Request::retrieve('sid', 'Integer') ?: 0);
    $doDelete = CRM_Utils_Request::retrieve('action', 'String') === 'delete';
    if ($doDelete && $deleteId) {
      CRM_SwissQRInvoice_BAO_Invoice::deleteService($deleteId);
      CRM_Core_Session::setStatus(ts('Prestation supprimée.', ['domain' => 'ch.ipik.swissQRinvoice']), ts('Succès', ['domain' => 'ch.ipik.swissQRinvoice']), 'success');
      CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/swissqr/services', 'reset=1'));
      return;
    }

    $this->assign('services',    CRM_SwissQRInvoice_BAO_Invoice::getServices());
    $this->assign('editService', $editService);
    $this->assign('canEdit',     CRM_Core_Permission::check('edit swissqr invoices'));
    return parent::run();
  }
}
