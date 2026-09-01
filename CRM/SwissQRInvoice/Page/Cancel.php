<?php
/**
 * Page d'annulation d'une facture (confirmation + action).
 */
class CRM_SwissQRInvoice_Page_Cancel extends CRM_Core_Page {
  public function run() {
    if (!CRM_Core_Permission::check('edit swissqr invoices')) {
      CRM_Utils_System::permissionDenied();
      return;
    }
    $id = (int) CRM_Utils_Request::retrieve('id', 'Integer');
    if (!$id) CRM_Core_Error::fatal(ts('Facture introuvable.'));

    $invoice = CRM_SwissQRInvoice_BAO_Invoice::getById($id);
    if (!$invoice) CRM_Core_Error::fatal(ts('Facture introuvable.'));

    if ($invoice['status'] === 'paid') {
      CRM_Core_Session::setStatus(ts('Une facture payée ne peut pas être annulée.'), ts('Erreur'), 'error');
      CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/swissqr/invoice/list'));
      return;
    }
    if ($invoice['status'] === 'cancelled') {
      CRM_Core_Session::setStatus(ts('Cette facture est déjà annulée.'), ts('Info'), 'info');
      CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/swissqr/invoice/list'));
      return;
    }

    // Confirmation via GET ?confirm=1
    $confirm = CRM_Utils_Request::retrieve('confirm', 'Integer');
    if ($confirm) {
      CRM_SwissQRInvoice_BAO_Invoice::cancel($id);
      CRM_Core_Session::setStatus(
        ts('Facture %1 annulée.', [1 => $invoice['invoice_number']]),
        ts('Facture annulée'), 'success'
      );
      CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/swissqr/invoice/list'));
      return;
    }

    $this->assign('invoice', $invoice);
    $this->assign('cancelUrl', CRM_Utils_System::url('civicrm/swissqr/invoice/cancel', "id={$id}&confirm=1&reset=1"));
    $this->assign('backUrl',   CRM_Utils_System::url('civicrm/swissqr/invoice/list'));
    CRM_Utils_System::setTitle(ts('Annuler la facture %1', [1 => $invoice['invoice_number']]));
    return parent::run();
  }
}
