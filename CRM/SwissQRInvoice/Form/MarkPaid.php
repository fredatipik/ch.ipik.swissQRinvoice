<?php
class CRM_SwissQRInvoice_Form_MarkPaid extends CRM_Core_Form {
  private array $_invoice;

  public function preProcess() {
    $id = (int) CRM_Utils_Request::retrieve('id','Integer');
    if ($id) {
      $this->_invoice = CRM_SwissQRInvoice_BAO_Invoice::getById($id);
      CRM_Core_Session::singleton()->set('swissqr_markpaid_invoice_id', $id);
    } else {
      $id = (int) CRM_Core_Session::singleton()->get('swissqr_markpaid_invoice_id');
      $this->_invoice = $id ? CRM_SwissQRInvoice_BAO_Invoice::getById($id) : [];
    }
    if (!$this->_invoice) CRM_Core_Error::fatal(ts('Facture introuvable.'));
  }

  public function buildQuickForm() {
    $this->add('hidden','invoice_id', $this->_invoice['id']);
    $this->add('text','amount',    ts('Montant reçu (CHF)'),    ['class'=>'six'], true);
    $this->add('text','paid_date', ts('Date de paiement'),       ['type'=>'date','class'=>'crm-form-text'], true);
    $this->addButtons([
      ['type'=>'submit','name'=>ts('Confirmer le paiement'),'isDefault'=>true],
      ['type'=>'cancel','name'=>ts('Annuler')],
    ]);
    $this->setDefaults([
      'amount'    => number_format((float)$this->_invoice['amount_due'],2,'.',''),
      'paid_date' => date('Y-m-d'),
    ]);
    $this->assign('invoice',$this->_invoice);
  }

  public function postProcess() {
    $vals = $this->exportValues();
    if (empty($this->_invoice)) {
      $id = (int)($vals['invoice_id'] ?? 0);
      if ($id) $this->_invoice = CRM_SwissQRInvoice_BAO_Invoice::getById($id);
    }
    if (empty($this->_invoice)) throw new CRM_Core_Exception('Facture introuvable.');
    $paidDate = $vals['paid_date'];
    CRM_SwissQRInvoice_BAO_Invoice::markAsPaid($this->_invoice['id'],(float)$vals['amount'],$paidDate);
    CRM_Core_Session::setStatus(ts('Facture marquée comme payée.'), ts('Succès'), 'success');
    CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/swissqr/invoice/list'));
  }
}
