<?php
/**
 * ch.ipik.swissQRinvoice — Swiss QR Invoice extension for CiviCRM
 * Version 0.36
 */

function swissQRinvoice_civicrm_config(&$config) {
  set_include_path(__DIR__ . PATH_SEPARATOR . get_include_path());
  CRM_Core_Smarty::singleton()->addTemplateDir(__DIR__ . '/templates');
  foreach ([
    'Utils', 'Reference', 'Contribution', 'Mail', 'Sender', 'Tokens',
    'BAO/Invoice', 'PDF/Generator',
    'Form/Settings', 'Form/Invoice', 'Form/Send', 'Form/MarkPaid', 'Form/Bulk',
    'Page/InvoiceList', 'Page/PDF', 'Page/Services', 'Page/Duplicate',
    'Page/Cancel', 'Page/CancelDuplicate', 'Page/Slip',
  ] as $class) {
    require_once __DIR__ . '/CRM/SwissQRInvoice/' . $class . '.php';
  }

  // Register token listeners once per request.
  if (!isset(Civi::$statics[__FUNCTION__])) {
    Civi::$statics[__FUNCTION__] = TRUE;
    Civi::dispatcher()->addListener('civi.token.list', ['CRM_SwissQRInvoice_Tokens', 'register']);
    Civi::dispatcher()->addListener('civi.token.eval', ['CRM_SwissQRInvoice_Tokens', 'evaluate']);
  }
}

// ── Lifecycle ────────────────────────────────────────────────────────────────

function swissQRinvoice_civicrm_install() {
  _swissQRinvoice_composer_install();
  if (file_exists(__DIR__ . '/sql/install.sql')) {
    CRM_Utils_File::sourceSQLFile(CIVICRM_DSN, __DIR__ . '/sql/install.sql');
  }
  _swissQRinvoice_schema_upgrade();
  _swissQRinvoice_install_routes();
  _swissQRinvoice_install_navigation();
  _swissQRinvoice_install_financial_type();
  _swissQRinvoice_install_msg_template();
}

function swissQRinvoice_civicrm_enable() {
  _swissQRinvoice_composer_install();
  _swissQRinvoice_schema_upgrade();
  _swissQRinvoice_install_msg_template();
  _swissQRinvoice_install_routes();
  $navCount = (int) CRM_Core_DAO::singleValueQuery(
    "SELECT COUNT(*) FROM civicrm_navigation WHERE name = 'swissqr_facturation'"
  );
  if (!$navCount) {
    _swissQRinvoice_install_navigation();
  }
  CRM_Core_Config::clearDBCache();
}

function swissQRinvoice_civicrm_disable() {
  CRM_Core_DAO::executeQuery("DELETE FROM civicrm_menu WHERE path LIKE 'civicrm/swissqr%' OR path = 'civicrm/admin/swissqr/settings'");
  CRM_Core_Config::clearDBCache();
}

function swissQRinvoice_civicrm_uninstall() {
  if (file_exists(__DIR__ . '/sql/uninstall.sql')) {
    CRM_Utils_File::sourceSQLFile(CIVICRM_DSN, __DIR__ . '/sql/uninstall.sql');
  }
  CRM_Core_DAO::executeQuery("DELETE FROM civicrm_menu WHERE path LIKE 'civicrm/swissqr%' OR path = 'civicrm/admin/swissqr/settings'");
  CRM_Core_DAO::executeQuery("DELETE FROM civicrm_navigation WHERE name LIKE 'swissqr_%'");
  CRM_Core_Config::clearDBCache();
}

/**
 * Run composer if the vendor directory is missing (Git checkouts).
 * Release archives already contain vendor/, so this normally does nothing.
 */
function _swissQRinvoice_composer_install(): void {
  if (file_exists(__DIR__ . '/vendor/autoload.php') && file_exists(__DIR__ . '/vendor/sprain')) {
    return;
  }
  if (!file_exists(__DIR__ . '/composer.json')) {
    CRM_Core_Error::debug_log_message('SwissQRInvoice: composer.json not found, vendor not installed.');
    return;
  }
  $composerBin = NULL;
  foreach (['/usr/bin/composer', '/usr/local/bin/composer', '/home/' . get_current_user() . '/bin/composer'] as $p) {
    if (file_exists($p)) {
      $composerBin = $p;
      break;
    }
  }
  if (!$composerBin && function_exists('shell_exec')) {
    $composerBin = trim((string) shell_exec('which composer 2>/dev/null'));
  }
  if (!$composerBin || !file_exists($composerBin) || !function_exists('exec')) {
    CRM_Core_Error::debug_log_message('SwissQRInvoice: composer not found, vendor not installed.');
    return;
  }
  $output = [];
  $code   = 0;
  exec('cd ' . escapeshellarg(__DIR__) . ' && ' . escapeshellarg($composerBin) . ' install --no-dev --no-interaction --quiet 2>&1', $output, $code);
  CRM_Core_Error::debug_log_message($code
    ? 'SwissQRInvoice composer error: ' . implode("\n", $output)
    : 'SwissQRInvoice: composer install OK.');
}

/**
 * Idempotent schema adjustments for existing installs.
 */
function _swissQRinvoice_schema_upgrade(): void {
  $hasIndex = CRM_Core_DAO::singleValueQuery(
    "SELECT COUNT(*) FROM information_schema.statistics
      WHERE table_schema = DATABASE() AND table_name = 'civicrm_swissqr_invoice'
        AND index_name = 'idx_payment_contribution_id'"
  );
  if (!$hasIndex) {
    CRM_Core_DAO::executeQuery('ALTER TABLE civicrm_swissqr_invoice ADD INDEX idx_payment_contribution_id (payment_contribution_id)');
  }
}

// ── Routes ───────────────────────────────────────────────────────────────────

/**
 * Route table shared by alterMenu and the civicrm_menu inserts.
 * [path, title, page_callback, permission, is_public]
 */
function _swissQRinvoice_routes(): array {
  $d = ['domain' => 'ch.ipik.swissQRinvoice'];
  return [
    ['civicrm/swissqr/invoice/list',             ts('All invoices', $d),        'CRM_SwissQRInvoice_Page_InvoiceList',     'access CiviCRM', FALSE],
    ['civicrm/swissqr/invoice/new',              ts('New invoice', $d),         'CRM_SwissQRInvoice_Form_Invoice',         'access CiviCRM', FALSE],
    ['civicrm/swissqr/invoice/edit',             ts('Edit invoice', $d),        'CRM_SwissQRInvoice_Form_Invoice',         'access CiviCRM', FALSE],
    ['civicrm/swissqr/invoice/pdf',              ts('Download PDF', $d),        'CRM_SwissQRInvoice_Page_PDF',             'access CiviCRM', FALSE],
    ['civicrm/swissqr/invoice/send',             ts('Send invoice', $d),        'CRM_SwissQRInvoice_Form_Send',            'edit swissqr invoices', FALSE],
    ['civicrm/swissqr/invoice/markpaid',         ts('Mark as paid', $d),        'CRM_SwissQRInvoice_Form_MarkPaid',        'edit swissqr invoices', FALSE],
    ['civicrm/swissqr/invoice/duplicate',        ts('Duplicate invoice', $d),   'CRM_SwissQRInvoice_Page_Duplicate',       'edit swissqr invoices', FALSE],
    ['civicrm/swissqr/invoice/cancel',           ts('Cancel invoice', $d),      'CRM_SwissQRInvoice_Page_Cancel',          'edit swissqr invoices', FALSE],
    ['civicrm/swissqr/invoice/cancel-duplicate', ts('Discard duplicate', $d),   'CRM_SwissQRInvoice_Page_CancelDuplicate', 'edit swissqr invoices', FALSE],
    ['civicrm/swissqr/task/bulk',                ts('Create QR invoices', $d),  'CRM_SwissQRInvoice_Form_Bulk',            'edit swissqr invoices', FALSE],
    ['civicrm/swissqr/services',                 ts('Services', $d),            'CRM_SwissQRInvoice_Page_Services',        'access CiviCRM', FALSE],
    ['civicrm/admin/swissqr/settings',           ts('SwissQR Settings', $d),    'CRM_SwissQRInvoice_Form_Settings',        'administer CiviCRM', FALSE],
    ['civicrm/swissqr/slip',                     ts('Donation slip', $d),       'CRM_SwissQRInvoice_Page_Slip',            '*always allow*', TRUE],
  ];
}

function swissQRinvoice_civicrm_alterMenu(&$items) {
  foreach (_swissQRinvoice_routes() as [$path, $title, $callback, $perm, $public]) {
    $items[$path] = [
      'title'            => $title,
      'page_callback'    => $callback,
      'access_arguments' => [[$perm], 'and'],
      'is_public'        => $public,
    ];
  }
}

function _swissQRinvoice_install_routes(): void {
  $aclCheck = serialize(['CRM_Core_Permission', 'checkMenu']);
  CRM_Core_DAO::executeQuery(
    "DELETE FROM civicrm_menu WHERE path LIKE 'civicrm/swissqr%' OR path = 'civicrm/admin/swissqr/settings'"
  );
  foreach (_swissQRinvoice_routes() as [$path, $title, $callback, $perm, $public]) {
    CRM_Core_DAO::executeQuery(
      "INSERT INTO civicrm_menu
         (domain_id, path, title, page_callback, access_callback, access_arguments, is_active, is_public, is_exposed, weight, type, page_type)
       VALUES (1, %1, %2, %3, %4, %5, 1, %6, 1, 1, 1, 1)",
      [
        1 => [$path, 'String'],
        2 => [$title, 'String'],
        3 => [$callback, 'String'],
        4 => [$aclCheck, 'String'],
        5 => [serialize([[$perm], 'and']), 'String'],
        6 => [$public ? 1 : 0, 'Integer'],
      ]
    );
  }
}

function _swissQRinvoice_install_navigation(): void {
  $d = ['domain' => 'ch.ipik.swissQRinvoice'];
  CRM_Core_DAO::executeQuery("DELETE FROM civicrm_navigation WHERE name LIKE 'swissqr_%'");
  CRM_Core_DAO::executeQuery(
    "INSERT INTO civicrm_navigation
       (domain_id, label, name, url, permission, permission_operator, parent_id, is_active, has_separator, weight, icon)
     VALUES (1, %1, 'swissqr_facturation', NULL, 'access CiviCRM', 'OR', NULL, 1, 0, 35, 'crm-i fa-file-invoice')",
    [1 => [ts('Invoicing', $d), 'String']]
  );
  $parentId = (int) CRM_Core_DAO::singleValueQuery('SELECT LAST_INSERT_ID()');
  $items = [
    [ts('All invoices', $d), 'swissqr_list',     'civicrm/swissqr/invoice/list?reset=1',   'access CiviCRM',     10, 0],
    [ts('New invoice', $d),  'swissqr_new',      'civicrm/swissqr/invoice/new?reset=1',    'access CiviCRM',     20, 0],
    [ts('Services', $d),     'swissqr_services', 'civicrm/swissqr/services?reset=1',       'access CiviCRM',     30, 0],
    [ts('Settings', $d),     'swissqr_settings', 'civicrm/admin/swissqr/settings?reset=1', 'administer CiviCRM', 40, 1],
  ];
  foreach ($items as [$label, $name, $url, $perm, $weight, $sep]) {
    CRM_Core_DAO::executeQuery(
      "INSERT INTO civicrm_navigation
         (domain_id, label, name, url, permission, permission_operator, parent_id, is_active, has_separator, weight)
       VALUES (1, %1, %2, %3, %4, 'OR', %5, 1, %6, %7)",
      [
        1 => [$label, 'String'],
        2 => [$name, 'String'],
        3 => [$url, 'String'],
        4 => [$perm, 'String'],
        5 => [$parentId, 'Integer'],
        6 => [$sep, 'Integer'],
        7 => [$weight, 'Integer'],
      ]
    );
  }
}

function _swissQRinvoice_install_financial_type(): void {
  $exists = (int) CRM_Core_DAO::singleValueQuery(
    "SELECT COUNT(*) FROM civicrm_financial_type WHERE name = 'Facture QR'"
  );
  if (!$exists) {
    CRM_Core_DAO::executeQuery(
      "INSERT INTO civicrm_financial_type (name, description, is_deductible, is_reserved, is_active)
       VALUES ('Facture QR', 'Paiements de factures Swiss QR', 0, 0, 1)"
    );
  }
  $ftId = (int) CRM_Core_DAO::singleValueQuery(
    "SELECT id FROM civicrm_financial_type WHERE name = 'Facture QR' LIMIT 1"
  );
  $linked = (int) CRM_Core_DAO::singleValueQuery(
    "SELECT COUNT(*) FROM civicrm_entity_financial_account
     WHERE entity_table = 'civicrm_financial_type' AND entity_id = %1",
    [1 => [$ftId, 'Integer']]
  );
  if (!$linked && $ftId) {
    // Copy the account links of "Donation" (financial type 1).
    CRM_Core_DAO::executeQuery(
      "INSERT INTO civicrm_entity_financial_account
         (entity_table, entity_id, account_relationship, financial_account_id)
       SELECT 'civicrm_financial_type', %1, account_relationship, financial_account_id
       FROM civicrm_entity_financial_account
       WHERE entity_table = 'civicrm_financial_type' AND entity_id = 1",
      [1 => [$ftId, 'Integer']]
    );
  }
}

/**
 * Default email template. Created once; afterwards it belongs to the admin.
 */
function _swissQRinvoice_install_msg_template(): void {
  $exists = (int) CRM_Core_DAO::singleValueQuery(
    "SELECT COUNT(*) FROM civicrm_msg_template WHERE msg_title = 'swissqrinvoice_send'"
  );
  if ($exists) {
    return;
  }
  $bodyHtml = <<<HTML
<p>{contact.email_greeting_display},</p>

<p>Veuillez trouver ci-joint la facture n° <strong>{invoice_number}</strong>
du {invoice_date} pour un montant de <strong>CHF {amount_due}</strong>.</p>

<p>Nous vous remercions de votre règlement avant l'échéance indiquée sur la facture.</p>

<p>N'hésitez pas à nous contacter pour toute question.</p>

<p>Avec nos meilleures salutations,<br>
{organization_name}</p>
HTML;

  $bodyText = "{contact.email_greeting_display},\n\n"
    . "Veuillez trouver ci-joint la facture n° {invoice_number} "
    . "du {invoice_date} pour un montant de CHF {amount_due}.\n\n"
    . "Nous vous remercions de votre règlement avant l'échéance indiquée sur la facture.\n\n"
    . "Avec nos meilleures salutations,\n{organization_name}";

  CRM_Core_DAO::executeQuery(
    "INSERT INTO civicrm_msg_template
       (msg_title, msg_subject, msg_text, msg_html, is_active, is_default, is_reserved)
     VALUES (%1, %2, %3, %4, 1, 1, 0)",
    [
      1 => ['swissqrinvoice_send', 'String'],
      2 => ['Facture N° {invoice_number}', 'String'],
      3 => [$bodyText, 'String'],
      4 => [$bodyHtml, 'String'],
    ]
  );
}

// ── Hooks ────────────────────────────────────────────────────────────────────

function swissQRinvoice_civicrm_permission(&$permissions) {
  $d = ['domain' => 'ch.ipik.swissQRinvoice'];
  $permissions['access swissqr invoices'] = [
    'label'       => ts('Swiss QR Invoice: view invoices', $d),
    'description' => ts('View the list and details of QR invoices.', $d),
  ];
  $permissions['edit swissqr invoices'] = [
    'label'       => ts('Swiss QR Invoice: create and edit invoices', $d),
    'description' => ts('Create, edit, send and mark QR invoices as paid.', $d),
  ];
}

function swissQRinvoice_civicrm_tabset($tabsetName, &$tabs, $context) {
  if ($tabsetName !== 'civicrm/contact/view' || !CRM_Core_Permission::check('access CiviCRM')) {
    return;
  }
  $contactID = (int) ($context['contact_id'] ?? 0);
  if (!$contactID) {
    return;
  }
  $count = CRM_SwissQRInvoice_BAO_Invoice::getCountForContact($contactID);
  $tabs['swissqr_invoices'] = [
    'id'     => 'swissqr_invoices',
    'url'    => CRM_Utils_System::url('civicrm/swissqr/invoice/list', "cid={$contactID}&reset=1"),
    'title'  => ts('QR Invoices', ['domain' => 'ch.ipik.swissQRinvoice']) . ($count ? " ({$count})" : ''),
    'weight' => 150,
    'valid'  => 1,
    'active' => 1,
    'class'  => 'livePage',
  ];
}

/**
 * "Create QR invoices" in the Actions menu of contact searches.
 */
function swissQRinvoice_civicrm_searchTasks($objectType, &$tasks) {
  if ($objectType !== 'contact' || !CRM_Core_Permission::check('edit swissqr invoices')) {
    return;
  }
  $tasks[] = [
    'title'  => ts('Create QR invoices', ['domain' => 'ch.ipik.swissQRinvoice']),
    'class'  => 'CRM_SwissQRInvoice_Form_Bulk',
    'url'    => 'civicrm/swissqr/task/bulk',
    'result' => FALSE,
  ];
}

/**
 * Keep invoices in step with their contribution (payments recorded by hand,
 * by CiviBanking, or cancellations made from the contribution itself).
 */
function swissQRinvoice_civicrm_post($op, $objectName, $objectId, &$objectRef) {
  if ($objectName === 'Contribution' && $objectId && in_array($op, ['create', 'edit'], TRUE)) {
    CRM_SwissQRInvoice_Contribution::syncInvoice((int) $objectId);
  }
}

/**
 * Attach the QR invoice to CiviCRM's native "Email invoice".
 */
function swissQRinvoice_civicrm_alterMailParams(&$params, $context) {
  if ($context === 'singleEmail') {
    CRM_SwissQRInvoice_Mail::alterNativeInvoiceEmail($params);
  }
}
