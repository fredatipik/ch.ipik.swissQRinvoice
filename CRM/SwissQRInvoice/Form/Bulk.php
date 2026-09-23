<?php
/**
 * Contact search action: "Create QR invoices".
 *
 * Creates one issued invoice (with its pending contribution) per selected
 * contact, through a CiviCRM queue so that hundreds of contacts do not hit
 * the PHP time limit. Sending is then done with CiviCRM's native
 * "Email invoice" action on the resulting contributions — queued too when
 * Invoice Helper is installed — and the QR slip is attached automatically.
 */
class CRM_SwissQRInvoice_Form_Bulk extends CRM_Contact_Form_Task {

  const CHUNK = 20;

  public function preProcess() {
    parent::preProcess();
    if (!CRM_Core_Permission::check('edit swissqr invoices')) {
      CRM_Utils_System::permissionDenied();
    }
  }

  public function buildQuickForm() {
    $d = CRM_SwissQRInvoice_Utils::DOMAIN;
    $this->setTitle(ts('Create QR invoices', ['domain' => $d]));

    $services = ['' => ts('- free entry -', ['domain' => $d])];
    foreach (CRM_SwissQRInvoice_BAO_Invoice::getServices() as $s) {
      $services[$s['id']] = $s['name'] . ' — CHF ' . number_format((float) $s['unit_price'], 2, '.', "'");
    }
    $this->add('select', 'service_id', ts('Service', ['domain' => $d]), $services);
    $this->add('text', 'article', ts('Item', ['domain' => $d]), ['class' => 'huge']);
    $this->add('text', 'description', ts('Description', ['domain' => $d]), ['class' => 'huge']);
    $this->add('text', 'unit_price', ts('Amount (CHF)', ['domain' => $d]), ['class' => 'six']);
    $this->add('text', 'invoice_date', ts('Invoice date', ['domain' => $d]), ['type' => 'date', 'class' => 'crm-form-text'], TRUE);
    $this->add('text', 'due_date', ts('Due date', ['domain' => $d]), ['type' => 'date', 'class' => 'crm-form-text']);
    $this->add('textarea', 'notes', ts('Conditions', ['domain' => $d]), ['rows' => 2, 'cols' => 60, 'class' => 'huge']);
    $this->add('checkbox', 'send_now', ts('Email the invoices immediately', ['domain' => $d]));

    $this->addFormRule([self::class, 'formRule']);
    $this->addDefaultButtons(ts('Create invoices', ['domain' => $d]));

    $this->assign('contactCount', count($this->_contactIds));
  }

  public function setDefaultValues() {
    return [
      'invoice_date' => date('Y-m-d'),
      'due_date'     => date('Y-m-d', strtotime('+30 days')),
      'notes'        => Civi::settings()->get('swissqr_vat_note') ?: '',
    ];
  }

  public static function formRule($values) {
    $errors = [];
    if (empty($values['service_id'])) {
      if (trim($values['article'] ?? '') === '') {
        $errors['article'] = ts('Choose a service or enter an item.', ['domain' => CRM_SwissQRInvoice_Utils::DOMAIN]);
      }
      if (!is_numeric($values['unit_price'] ?? '') || (float) $values['unit_price'] <= 0) {
        $errors['unit_price'] = ts('Enter an amount greater than zero.', ['domain' => CRM_SwissQRInvoice_Utils::DOMAIN]);
      }
    }
    return $errors ?: TRUE;
  }

  public function postProcess() {
    $v = $this->exportValues();

    $line = ['article' => trim($v['article'] ?? ''), 'description' => trim($v['description'] ?? ''), 'unit_price' => (float) ($v['unit_price'] ?? 0), 'quantity' => 1];
    if (!empty($v['service_id'])) {
      foreach (CRM_SwissQRInvoice_BAO_Invoice::getServices() as $s) {
        if ((int) $s['id'] === (int) $v['service_id']) {
          $line['article']     = $line['article'] !== '' ? $line['article'] : $s['name'];
          $line['description'] = $line['description'] !== '' ? $line['description'] : (string) $s['description'];
          $line['unit_price']  = $line['unit_price'] > 0 ? $line['unit_price'] : (float) $s['unit_price'];
        }
      }
    }
    $spec = [
      'send_now'     => !empty($v['send_now']),
      'invoice_date' => $v['invoice_date'],
      'due_date'     => $v['due_date'] ?? '',
      'notes'        => $v['notes'] ?? '',
      'lines'        => [$line],
    ];

    $queue = CRM_Queue_Service::singleton()->create([
      'type'  => 'Sql',
      'name'  => 'swissqr_bulk_' . CRM_Core_Session::getLoggedInContactID() . '_' . time(),
      'reset' => TRUE,
    ]);
    $chunks = array_chunk(array_map('intval', $this->_contactIds), self::CHUNK);
    $total  = count($this->_contactIds);
    foreach ($chunks as $i => $chunk) {
      $queue->createItem(new CRM_Queue_Task(
        [self::class, 'processChunk'],
        [$chunk, $spec],
        ts('Creating invoices %1 to %2 of %3', [
          1 => $i * self::CHUNK + 1,
          2 => $i * self::CHUNK + count($chunk),
          3 => $total,
          'domain' => CRM_SwissQRInvoice_Utils::DOMAIN,
        ])
      ));
    }

    $runner = new CRM_Queue_Runner([
      'title'     => ts('Creating QR invoices', ['domain' => CRM_SwissQRInvoice_Utils::DOMAIN]),
      'queue'     => $queue,
      'errorMode' => CRM_Queue_Runner::ERROR_ABORT,
      // URL absolue : le lanceur de file redirige depuis son propre ecran.
      'onEndUrl'  => CRM_Utils_System::url('civicrm/swissqr/invoice/list', 'reset=1', TRUE),
    ]);
    $runner->runAllViaWeb();
  }

  /**
   * Queue task: one chunk of contacts.
   */
  public static function processChunk(CRM_Queue_TaskContext $ctx, array $contactIds, array $spec): bool {
    foreach ($contactIds as $cid) {
      $invoice = CRM_SwissQRInvoice_BAO_Invoice::createIssued((int) $cid, $spec);
      if (empty($spec['send_now'])) {
        continue;
      }
      // One failed recipient must not lose the whole batch: log and carry on.
      $error   = NULL;
      $toEmail = CRM_SwissQRInvoice_Sender::primaryEmail((int) $cid);
      $message = CRM_SwissQRInvoice_Sender::render($invoice);
      if (!CRM_SwissQRInvoice_Sender::send($invoice, $toEmail, $message['subject'], $message['html'], $error)) {
        CRM_Core_Error::debug_log_message(sprintf(
          'SwissQRInvoice: bulk send of invoice %s to contact %d failed: %s',
          $invoice['invoice_number'], $cid, $error
        ));
      }
    }
    return TRUE;
  }

}
