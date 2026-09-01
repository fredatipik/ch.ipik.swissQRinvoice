<?php
class CRM_SwissQRInvoice_Page_InvoiceList extends CRM_Core_Page {
  public function run() {
    $filters = [
      'status'     => CRM_Utils_Request::retrieve('status',    'String')  ?: '',
      'contact_id' => CRM_Utils_Request::retrieve('cid',       'Integer') ?: '',
      'date_from'  => CRM_Utils_Request::retrieve('date_from', 'String')  ?: '',
      'date_to'    => CRM_Utils_Request::retrieve('date_to',   'String')  ?: '',
    ];
    $page   = max(1, (int)(CRM_Utils_Request::retrieve('p', 'Integer') ?: 1));
    $result = CRM_SwissQRInvoice_BAO_Invoice::getList($filters, $page);

    // Résoudre le nom du contact filtré pour l'afficher dans le champ
    $filterContactName = '';
    if (!empty($filters['contact_id'])) {
      try {
        $fc = civicrm_api3('Contact', 'getsingle', [
          'id'     => $filters['contact_id'],
          'return' => 'display_name',
        ]);
        $filterContactName = $fc['display_name'] ?? '';
      } catch (Exception $e) {}
    }

    $this->assign('invoices',           $result['rows']);
    $this->assign('total',              $result['total']);
    $this->assign('pages',              $result['pages']);
    $this->assign('page',               $page);
    $this->assign('filters',            $filters);
    $this->assign('filterContactName',  $filterContactName);
    $this->assign('canEdit',            CRM_Core_Permission::check('edit swissqr invoices'));

    CRM_Utils_System::setTitle(ts('Facturation — Toutes les factures'));
    return parent::run();
  }
}
