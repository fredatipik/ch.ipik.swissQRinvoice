<?php
class CRM_SwissQRInvoice_Form_Settings extends CRM_Core_Form {
  public function buildQuickForm() {
    $this->addEntityRef('swissqr_org_contact_id', ts('Sender organisation contact', ['domain' => 'ch.ipik.swissQRinvoice']), ['contact_type'=>'Organization'], true);
    $this->add('text','swissqr_iban',           ts('IBAN', ['domain' => 'ch.ipik.swissQRinvoice']),              ['class'=>'huge'], true);
    $this->add('text','swissqr_signatory_name', ts('Signatory name', ['domain' => 'ch.ipik.swissQRinvoice']), ['class'=>'huge']);
    $this->add('text','swissqr_vat_note',       ts('VAT note', ['domain' => 'ch.ipik.swissQRinvoice']),       ['class'=>'huge']);
    $this->add('text','swissqr_invoice_number_format', ts('Numbering format', ['domain' => 'ch.ipik.swissQRinvoice']), ['class'=>'huge','placeholder'=>'{YEAR}-{SEQ:4}']);
    $this->add('text','swissqr_qr_reference_template', ts('QR reference template', ['domain' => 'ch.ipik.swissQRinvoice']), ['class'=>'huge','placeholder'=>'Facture N° {NUMBER}']);
    $this->add('text','swissqr_qr_reference_template', ts('QR reference template', ['domain' => 'ch.ipik.swissQRinvoice']), ['class'=>'huge','placeholder'=>'Facture N° {NUMBER}']);
    // swissqr_email_body_template et swissqr_email_subject_template conservés en base (fallback Send.php)
    // mais retirés de l'UI — édition via CiviCRM > Templates de messages > "swissqrinvoice_send"
    $this->add('text','swissqr_logo_path',       ts('Logo path (absolute)', ['domain' => 'ch.ipik.swissQRinvoice']),      ['class'=>'huge']);
    $this->add('text','swissqr_logo_offset_x',   ts('Logo — horizontal offset (mm)', ['domain' => 'ch.ipik.swissQRinvoice']), ['class'=>'four','placeholder'=>'0']);
    $this->add('text','swissqr_logo_margin_bottom', ts('Logo — space below logo (mm)', ['domain' => 'ch.ipik.swissQRinvoice']), ['class'=>'four','placeholder'=>'0']);
    $this->add('text','swissqr_signature_path',  ts('Signature path (absolute)', ['domain' => 'ch.ipik.swissQRinvoice']), ['class'=>'huge']);
    $this->add('text','swissqr_signature_offset_y', ts('Signature — vertical offset (mm)', ['domain' => 'ch.ipik.swissQRinvoice']), ['class'=>'four','placeholder'=>'0']);
    $this->add('checkbox','swissqr_single_page_pdf', ts('Single page PDF (QR at bottom of page 1)', ['domain' => 'ch.ipik.swissQRinvoice']));

    // Compte financier pour Facture QR
    $faDao = CRM_Core_DAO::executeQuery(
      "SELECT id, name FROM civicrm_financial_account WHERE is_active = 1 ORDER BY name"
    );
    $faOpts = ['' => ts('-- Same as Donations (default) --')];
    while ($faDao->fetch()) $faOpts[$faDao->id] = $faDao->name;
    $this->add('select','swissqr_financial_account_id', ts('Financial account (QR Invoice)', ['domain' => 'ch.ipik.swissQRinvoice']), $faOpts);

    // Rôles WordPress
    $roleOpts = [];
    if (function_exists('wp_roles')) {
      foreach (wp_roles()->roles as $k => $r) $roleOpts[$k] = $r['name'];
    }
    if ($roleOpts) $this->addCheckBox('swissqr_allowed_roles', ts('Authorised roles', ['domain' => 'ch.ipik.swissQRinvoice']), $roleOpts);

    $this->addButtons([['type'=>'submit','name'=>ts('Save'),'isDefault'=>true]]);
    $this->setDefaults($this->_getDefaults());
  }

  private function _getDefaults(): array {
    $keys = ['swissqr_org_contact_id','swissqr_iban','swissqr_signatory_name','swissqr_vat_note',
             'swissqr_invoice_number_format','swissqr_qr_reference_template','swissqr_email_body_template',
             'swissqr_email_subject_template','swissqr_logo_path','swissqr_logo_offset_x',
             'swissqr_logo_margin_bottom','swissqr_signature_path','swissqr_allowed_roles',
             'swissqr_financial_account_id','swissqr_single_page_pdf','swissqr_signature_offset_y'];
    $defaults = [];
    foreach ($keys as $k) $defaults[$k] = Civi::settings()->get($k);
    if (!$defaults['swissqr_email_body_template'])
      $defaults['swissqr_email_body_template'] = CRM_SwissQRInvoice_Utils::defaultEmailBody();
    if (!$defaults['swissqr_invoice_number_format'])
      $defaults['swissqr_invoice_number_format'] = '{YEAR}-{SEQ:4}';
    if (!$defaults['swissqr_qr_reference_template'])
      $defaults['swissqr_qr_reference_template'] = 'Facture N° {NUMBER}';
    return $defaults;
  }

  public function postProcess() {
    $vals = $this->exportValues();
    $keys = ['swissqr_org_contact_id','swissqr_iban','swissqr_signatory_name','swissqr_vat_note',
             'swissqr_invoice_number_format','swissqr_qr_reference_template','swissqr_email_body_template',
             'swissqr_email_subject_template','swissqr_logo_path','swissqr_logo_offset_x',
             'swissqr_logo_margin_bottom','swissqr_signature_path','swissqr_allowed_roles',
             'swissqr_financial_account_id','swissqr_single_page_pdf','swissqr_signature_offset_y'];
    foreach ($keys as $k) Civi::settings()->set($k, $vals[$k] ?? null);

    // Si un compte financier est choisi, lier au type "Facture QR"
    $faId = (int)($vals['swissqr_financial_account_id'] ?? 0);
    if ($faId) {
      $ftId = (int) CRM_Core_DAO::singleValueQuery(
        "SELECT id FROM civicrm_financial_type WHERE name = 'Facture QR' LIMIT 1"
      );
      if ($ftId) {
        CRM_Core_DAO::executeQuery(
          "DELETE FROM civicrm_entity_financial_account
           WHERE entity_table = 'civicrm_financial_type' AND entity_id = %1",
          [1 => [$ftId, 'Integer']]
        );
        CRM_Core_DAO::executeQuery(
          "INSERT INTO civicrm_entity_financial_account
             (entity_table, entity_id, account_relationship, financial_account_id)
           SELECT 'civicrm_financial_type', %1, account_relationship, financial_account_id
           FROM civicrm_entity_financial_account
           WHERE entity_table = 'civicrm_financial_type' AND entity_id = 1",
          [1 => [$ftId, 'Integer']]
        );
        // Remplacer le compte principal par celui choisi
        CRM_Core_DAO::executeQuery(
          "UPDATE civicrm_entity_financial_account
           SET financial_account_id = %1
           WHERE entity_table = 'civicrm_financial_type' AND entity_id = %2 AND account_relationship = 1",
          [1 => [$faId, 'Integer'], 2 => [$ftId, 'Integer']]
        );
      }
    }

    CRM_Core_Session::setStatus(ts('Settings saved.', ['domain' => 'ch.ipik.swissQRinvoice']), ts('Success', ['domain' => 'ch.ipik.swissQRinvoice']), 'success');
  }
}
