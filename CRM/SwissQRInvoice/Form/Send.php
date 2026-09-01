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
    if (!$this->_invoice) CRM_Core_Error::fatal(ts('Facture introuvable.'));
    if ($this->_invoice['status'] === 'cancelled') {
      CRM_Core_Session::setStatus(ts('Impossible d\'envoyer une facture annulée.'), ts('Erreur'), 'error');
      CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/swissqr/invoice/list'));
    }
  }

  public function buildQuickForm() {
    try {
      $email = civicrm_api3('Email', 'getsingle', [
        'contact_id' => $this->_invoice['contact_id'],
        'is_primary'  => 1,
        'return'      => 'email',
      ]);
    } catch (Exception $e) { $email = []; }

    $this->add('hidden', 'invoice_id', $this->_invoice['id']);
    $this->add('text',   'to_email', ts('Email destinataire'), ['class' => 'huge'], true);
    $this->add('text',   'subject',  ts('Objet'),              ['class' => 'huge'], true);
    $this->add('wysiwyg', 'body_html', ts('Message'), ['rows' => 10, 'cols' => 80]);

    $this->addButtons([
      ['type' => 'submit', 'name' => ts('Envoyer'), 'isDefault' => true],
      ['type' => 'cancel', 'name' => ts('Annuler')],
    ]);

    [$subject, $bodyHtml] = $this->_loadMsgTemplate();

    $vars = [
      '{contact_name}'      => $this->_invoice['contact_name'] ?? '',
      '{contact_greeting}'  => '',
      '{invoice_number}'    => $this->_invoice['invoice_number'],
      '{invoice_date}'      => $this->_invoice['invoice_date'] ? date('d.m.Y', strtotime($this->_invoice['invoice_date'])) : '',
      '{amount_due}'        => number_format((float)$this->_invoice['amount_due'], 2, '.', "'"),
      '{organization_name}' => Civi::settings()->get('swissqr_signatory_name') ?? '',
    ];
    $subject  = strtr($subject,  $vars);
    $bodyHtml = strtr($bodyHtml, $vars);

    try {
      $cd = civicrm_api3('Contact', 'getsingle', [
        'id'     => $this->_invoice['contact_id'],
        'return' => 'display_name,first_name,last_name,email,email_greeting_display,postal_greeting_display',
      ]);
      // email_greeting_display pas toujours retourné par l'API — lecture directe
      if (empty($cd['email_greeting_display'])) {
        $cd['email_greeting_display'] = (string) CRM_Core_DAO::singleValueQuery(
          'SELECT email_greeting_display FROM civicrm_contact WHERE id = %1',
          [1 => [$this->_invoice['contact_id'], 'Integer']]
        );
      }
      if (empty($cd['postal_greeting_display'])) {
        $cd['postal_greeting_display'] = (string) CRM_Core_DAO::singleValueQuery(
          'SELECT postal_greeting_display FROM civicrm_contact WHERE id = %1',
          [1 => [$this->_invoice['contact_id'], 'Integer']]
        );
      }
      $tokenList = CRM_Utils_Token::getTokens($subject . $bodyHtml);
      $subject  = CRM_Utils_Token::replaceContactTokens($subject,  $cd, FALSE, $tokenList);
      $bodyHtml = CRM_Utils_Token::replaceContactTokens($bodyHtml, $cd, FALSE, $tokenList);
      // Greetings non gérés par replaceContactTokens — résolution manuelle
      $greetingTokens = [
        '{contact.email_greeting_display}'  => $cd['email_greeting_display'] ?? $cd['display_name'] ?? '',
        '{contact.postal_greeting_display}' => $cd['postal_greeting_display'] ?? $cd['display_name'] ?? '',
      ];
      $subject  = strtr($subject,  $greetingTokens);
      $bodyHtml = strtr($bodyHtml, $greetingTokens);
    } catch (Exception $e) {
      CRM_Core_Error::debug_log_message('SwissQR token error: ' . $e->getMessage());
    }

    $this->setDefaults([
      'to_email'  => $email['email'] ?? '',
      'subject'   => $subject,
      'body_html' => $bodyHtml,
    ]);
    $this->assign('invoice', $this->_invoice);
    $this->assign('editTemplateUrl', CRM_Utils_System::url(
      'civicrm/admin/messageTemplates',
      'reset=1'
    ));
  }

  private function _loadMsgTemplate(): array {
    $tplId = $this->_getMsgTemplateId();
    if ($tplId) {
      try {
        $tpl = civicrm_api3('MessageTemplate', 'getsingle', ['id' => $tplId]);
        return [$tpl['msg_subject'] ?? '', $tpl['msg_html'] ?? ''];
      } catch (Exception $e) {}
    }
    $subjTpl = Civi::settings()->get('swissqr_email_subject_template') ?: 'Facture N° {invoice_number}';
    $bodyTpl  = Civi::settings()->get('swissqr_email_body_template')
               ?: nl2br(CRM_SwissQRInvoice_Utils::defaultEmailBody());
    return [$subjTpl, $bodyTpl];
  }

  private function _getMsgTemplateId(): ?int {
    $id = CRM_Core_DAO::singleValueQuery(
      "SELECT id FROM civicrm_msg_template WHERE msg_title = 'swissqrinvoice_send' AND is_active = 1 LIMIT 1"
    );
    return $id ? (int)$id : null;
  }

  public function postProcess() {
    $vals = $this->exportValues();
    if (empty($this->_invoice)) {
      $id = (int)($vals['invoice_id'] ?? 0);
      if ($id) $this->_invoice = CRM_SwissQRInvoice_BAO_Invoice::getById($id);
    }
    if (empty($this->_invoice)) throw new CRM_Core_Exception('Facture introuvable.');

    $generator = new CRM_SwissQRInvoice_PDF_Generator($this->_invoice);
    $pdf       = $generator->generate();
    $filename  = 'Facture-' . $this->_invoice['invoice_number'] . '.pdf';
    $tmpFile   = tempnam(sys_get_temp_dir(), 'swissqr_') . '_' . $filename;
    file_put_contents($tmpFile, $pdf);

    $bodyHtml = $vals['body_html'] ?? '';
    $bodyText = strip_tags($bodyHtml);

    $mailParams = [
      'from'        => (function() {
        $ne = CRM_Core_BAO_Domain::getNameAndEmail();
        return '"' . addslashes($ne[0]) . '" <' . $ne[1] . '>';
      })(),
      'toEmail'     => $vals['to_email'],
      'subject'     => $vals['subject'],
      'html'        => $bodyHtml,
      'text'        => $bodyText,
      'attachments' => [['fullPath' => $tmpFile, 'mime_type' => 'application/pdf', 'cleanName' => $filename]],
    ];
    $result = CRM_Utils_Mail::send($mailParams);
    @unlink($tmpFile);

    if ($result) {
      CRM_SwissQRInvoice_BAO_Invoice::recordSent($this->_invoice['id'], $vals['to_email']);
      CRM_Core_Session::setStatus(
        ts('Facture envoyée à %1.', [1 => $vals['to_email']]),
        ts('Envoi réussi'), 'success'
      );
    } else {
      CRM_Core_Session::setStatus(ts("Erreur lors de l'envoi."), ts('Erreur'), 'error');
    }
    CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/swissqr/invoice/list'));
  }
}
