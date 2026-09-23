<?php
class CRM_SwissQRInvoice_Form_Invoice extends CRM_Core_Form {
  private ?array $_invoice    = null;
  private int    $_prefillCid = 0;

  public function preProcess() {
    $id = CRM_Utils_Request::retrieve('id', 'Integer');
    if ($id) {
      $this->_invoice = CRM_SwissQRInvoice_BAO_Invoice::getById((int)$id);
      if (!$this->_invoice) throw new CRM_Core_Exception(ts('Invoice not found.', ['domain' => 'ch.ipik.swissQRinvoice']));
      if (!CRM_SwissQRInvoice_BAO_Invoice::isEditable($this->_invoice)) {
        CRM_Core_Session::setStatus(
          ts('Invoice %1 has been issued and can no longer be edited. Cancel it and create a new one, or duplicate it.', ['domain' => 'ch.ipik.swissQRinvoice', 1 => $this->_invoice['invoice_number']]),
          ts('Invoice issued', ['domain' => 'ch.ipik.swissQRinvoice']), 'info'
        );
        CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/swissqr/invoice/list', 'reset=1'));
      }
    }

    // URL d'annulation — si duplicata, le supprimer
    $isDuplicate = (int) CRM_Utils_Request::retrieve('duplicate', 'Integer');
    if ($isDuplicate && !empty($this->_invoice['id'])) {
      $this->_cancelURL = CRM_Utils_System::url('civicrm/swissqr/invoice/cancel-duplicate', 'id=' . $this->_invoice['id'] . '&reset=1');
    } else {
      $this->_cancelURL = CRM_Utils_System::url('civicrm/swissqr/invoice/list', 'reset=1');
    }

    // Pré-remplissage contact depuis ?cid=
    $cid = (int) CRM_Utils_Request::retrieve('cid', 'Integer');
    if ($cid) {
      $this->_prefillCid = $cid;
    }

    // Pré-remplissage depuis contribution_id (existant)
    $contribId = CRM_Utils_Request::retrieve('contribution_id', 'Integer');
    if ($contribId && !$this->_invoice) {
      try {
        $contrib = civicrm_api3('Contribution', 'getsingle', ['id' => $contribId]);
        if (empty($this->_prefillCid)) {
          $this->_prefillCid = (int)($contrib['contact_id'] ?? 0);
        }
        $this->assign('prefill_contribution_id', $contribId);
      } catch (Exception $e) {}
    }

    if ($this->_prefillCid) {
      $this->assign('prefill_contact_id', $this->_prefillCid);
    }
  }

  public function buildQuickForm() {
    $this->addEntityRef('contact_id', ts('Recipient', ['domain' => 'ch.ipik.swissQRinvoice']), ['create' => true], true);

    $orgId   = (int) Civi::settings()->get('swissqr_org_contact_id');
    $orgs    = civicrm_api3('Contact', 'get', ['contact_type' => 'Organization', 'return' => 'id,display_name', 'options' => ['limit' => 50]]);
    $orgOpts = ['' => '-- Choisir --'];
    foreach ($orgs['values'] as $o) $orgOpts[$o['id']] = $o['display_name'];
    $this->add('select', 'organization_contact_id', ts('Sender organisation', ['domain' => 'ch.ipik.swissQRinvoice']), $orgOpts, true);

    $this->add('text',     'invoice_date',   ts('Invoice date', ['domain' => 'ch.ipik.swissQRinvoice']), ['type' => 'date', 'class' => 'crm-form-text'], true);
    $this->add('text',     'due_date',       ts('Due date', ['domain' => 'ch.ipik.swissQRinvoice']),            ['type' => 'date', 'class' => 'crm-form-text']);
    $this->add('text',     'invoice_number', ts('Invoice no.', ['domain' => 'ch.ipik.swissQRinvoice']),          ['class' => 'huge']);
    $this->add('text',     'reference',      ts('QR reference', ['domain' => 'ch.ipik.swissQRinvoice']),        ['class' => 'huge']);
    $this->add('textarea', 'notes',          ts('Terms', ['domain' => 'ch.ipik.swissQRinvoice']),          ['rows' => 2, 'cols' => 60, 'class' => 'huge']);
    $this->add('text',     'amount_paid',    ts('Paid to date', ['domain' => 'ch.ipik.swissQRinvoice']),      ['class' => 'six']);

    $this->add('select', 'discount_type', ts('Discount', ['domain' => 'ch.ipik.swissQRinvoice']), [
      'none'    => 'Aucun',
      'amount'  => 'Montant fixe (CHF)',
      'percent' => 'Pourcentage (%)',
    ]);
    $this->add('text',   'discount_value',  ts('Discount value', ['domain' => 'ch.ipik.swissQRinvoice']), ['class' => 'six']);
    $this->add('hidden', 'contribution_id', '');
    $this->add('hidden', 'invoice_id', $this->_invoice['id'] ?? '');

    $this->addButtons([
      ['type' => 'submit', 'name' => ts('Save', ['domain' => 'ch.ipik.swissQRinvoice']), 'isDefault' => TRUE],
      ['type' => 'submit', 'subName' => 'send', 'name' => ts('Save and send', ['domain' => 'ch.ipik.swissQRinvoice'])],
    ]);
    $this->assign('cancelURL', $this->_cancelURL);

    $services = CRM_SwissQRInvoice_BAO_Invoice::getServices();
    $this->assign('services', $services);
    $this->assign('invoice',  $this->_invoice);
    $this->assign('is_edit',  !empty($this->_invoice));
  }

  public function setDefaultValues() {
    $orgId = (int) Civi::settings()->get('swissqr_org_contact_id');
    $due   = date('Y-m-d', strtotime('+30 days'));

    if ($this->_invoice) {
      $defaults = $this->_invoice;
    } else {
      $defaults = [
        'invoice_date'            => date('Y-m-d'),
        'due_date'                => $due,
        'organization_contact_id' => $orgId,
        'notes'                   => Civi::settings()->get('swissqr_vat_note') ?: '',
        'discount_type'           => 'none',
        'discount_value'          => '',
      ];
    }

    // Pré-remplir contact_id depuis ?cid= ou contribution_id
    if (empty($defaults['contact_id']) && $this->_prefillCid) {
      $defaults['contact_id'] = $this->_prefillCid;
    }

    return $defaults;
  }

  public function postProcess() {
    $vals   = $this->exportValues();
    $params = [
      'contact_id'              => $vals['contact_id'],
      'organization_contact_id' => $vals['organization_contact_id'],
      'invoice_date'            => $vals['invoice_date'],
      'due_date'                => !empty($vals['due_date']) ? $vals['due_date'] : null,
      'notes'                   => $vals['notes'] ?? null,
      'reference'               => $vals['reference'] ?? null,
      'amount_paid'             => (float)($vals['amount_paid'] ?? 0),
      'discount_type'           => $vals['discount_type'] ?? 'none',
      'discount_value'          => (float)($vals['discount_value'] ?? 0),
      'contribution_id'         => $vals['contribution_id'] ?: null,
    ];
    if (!empty($vals['invoice_number'])) $params['invoice_number'] = $vals['invoice_number'];
    // The id travels with the form, not only in the URL: a lost id used to
    // save an edited invoice as a new one, with the same number.
    $invoiceId = (int) ($vals['invoice_id'] ?? 0) ?: (int) ($this->_invoice['id'] ?? 0);
    if ($invoiceId) $params['id'] = $invoiceId;

    $linesJson      = $_POST['lines_json'] ?? '[]';
    $params['lines'] = json_decode($linesJson, true) ?: [];

    $invoice = CRM_SwissQRInvoice_BAO_Invoice::save($params);
    CRM_Core_Session::setStatus(ts('Invoice %1 saved.', ['domain' => 'ch.ipik.swissQRinvoice', 1 => $invoice['invoice_number']]), ts('Success', ['domain' => 'ch.ipik.swissQRinvoice']), 'success');

    if (substr((string) $this->controller->getButtonName(), -5) === '_send') {
      CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/swissqr/invoice/send', 'id=' . $invoice['id'] . '&reset=1'));
    }
    CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/swissqr/invoice/list'));
  }
}
