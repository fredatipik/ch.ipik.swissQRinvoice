<?php
class CRM_SwissQRInvoice_Form_Send extends CRM_Core_Form {
  private array $_invoice;

  public function preProcess() {
    $id = (int) CRM_Utils_Request::retrieve('id', 'Integer');
    if ($id) {
      $this->_invoice = CRM_SwissQRInvoice_BAO_Invoice::getById($id);
      CRM_Core_Session::singleton()->set('swissqr_send_invoice_id', $id);
    } else {
      $id = (int) CRM_Core_Session::singleton()->get('swissqr_send_invoice_id');
      $this->_invoice = $id ? CRM_SwissQRInvoice_BAO_Invoice::getById($id) : [];
    }
    if (!$this->_invoice) throw new CRM_Core_Exception(ts('Invoice not found.', ['domain' => 'ch.ipik.swissQRinvoice']));
    if ($this->_invoice['status'] === 'cancelled') {
      CRM_Core_Session::setStatus(ts('Cannot send a cancelled invoice.', ['domain' => 'ch.ipik.swissQRinvoice']), ts('Error', ['domain' => 'ch.ipik.swissQRinvoice']), 'error');
      CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/swissqr/invoice/list'));
    }
  }

  public function buildQuickForm() {
    $this->add('hidden', 'invoice_id', $this->_invoice['id']);
    $this->add('text',   'to_email', ts('Recipient email', ['domain' => 'ch.ipik.swissQRinvoice']), ['class' => 'huge'], TRUE);
    $this->add('text',   'subject',  ts('Subject', ['domain' => 'ch.ipik.swissQRinvoice']), ['class' => 'huge'], TRUE);
    $this->add('wysiwyg', 'body_html', ts('Message', ['domain' => 'ch.ipik.swissQRinvoice']), ['rows' => 10, 'cols' => 80]);

    $this->addButtons([
      ['type' => 'submit', 'name' => ts('Send', ['domain' => 'ch.ipik.swissQRinvoice']), 'isDefault' => TRUE],
    ]);
    $this->assign('cancelURL', CRM_Utils_System::url('civicrm/swissqr/invoice/list', 'reset=1'));

    $message = CRM_SwissQRInvoice_Sender::render($this->_invoice);
    $this->setDefaults([
      'to_email'  => CRM_SwissQRInvoice_Sender::primaryEmail((int) $this->_invoice['contact_id']),
      'subject'   => $message['subject'],
      'body_html' => $message['html'],
    ]);
    $this->assign('invoice', $this->_invoice);
    $this->assign('editTemplateUrl', CRM_Utils_System::url('civicrm/admin/messageTemplates', 'reset=1'));
  }

  public function postProcess() {
    $vals = $this->exportValues();
    if (empty($this->_invoice)) {
      $id = (int) ($vals['invoice_id'] ?? 0);
      if ($id) {
        $this->_invoice = CRM_SwissQRInvoice_BAO_Invoice::getById($id);
      }
    }
    if (empty($this->_invoice)) {
      throw new CRM_Core_Exception(ts('Invoice not found.', ['domain' => 'ch.ipik.swissQRinvoice']));
    }

    $error = NULL;
    $sent  = CRM_SwissQRInvoice_Sender::send(
      $this->_invoice, $vals['to_email'], $vals['subject'], $vals['body_html'] ?? '', $error
    );
    if ($sent) {
      CRM_Core_Session::setStatus(
        ts('Invoice sent to %1.', ['domain' => 'ch.ipik.swissQRinvoice', 1 => $vals['to_email']]),
        ts('Successfully sent', ['domain' => 'ch.ipik.swissQRinvoice']), 'success'
      );
    }
    else {
      CRM_Core_Session::setStatus($error, ts('Error sending invoice.', ['domain' => 'ch.ipik.swissQRinvoice']), 'error');
    }
    CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/swissqr/invoice/list'));
  }
}
