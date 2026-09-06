<?php
/**
 * ch.ipik.swissQRinvoice — Swiss QR Invoice extension for CiviCRM
 * Version 0.29
 */

function swissQRinvoice_civicrm_config(&$config) {
  set_include_path(__DIR__ . PATH_SEPARATOR . get_include_path());
  $template = CRM_Core_Smarty::singleton();
  $template->addTemplateDir(__DIR__ . '/templates');
  require_once __DIR__ . '/CRM/SwissQRInvoice/Utils.php';
  require_once __DIR__ . '/CRM/SwissQRInvoice/BAO/Invoice.php';
  require_once __DIR__ . '/CRM/SwissQRInvoice/PDF/Generator.php';
  require_once __DIR__ . '/CRM/SwissQRInvoice/Form/Settings.php';
  require_once __DIR__ . '/CRM/SwissQRInvoice/Form/Invoice.php';
  require_once __DIR__ . '/CRM/SwissQRInvoice/Form/Send.php';
  require_once __DIR__ . '/CRM/SwissQRInvoice/Form/MarkPaid.php';
  require_once __DIR__ . '/CRM/SwissQRInvoice/Page/InvoiceList.php';
  require_once __DIR__ . '/CRM/SwissQRInvoice/Page/PDF.php';
  require_once __DIR__ . '/CRM/SwissQRInvoice/Page/Services.php';
  require_once __DIR__ . '/CRM/SwissQRInvoice/Page/Duplicate.php';
  require_once __DIR__ . '/CRM/SwissQRInvoice/Page/Cancel.php';
  require_once __DIR__ . '/CRM/SwissQRInvoice/Page/CancelDuplicate.php';
}

/**
 * Lance composer install --no-dev si le vendor est absent ou incomplet.
 * Fonctionne depuis l'interface CiviCRM (install/enable).
 */
function _swissQRinvoice_composer_install(): void {
  $vendorAutoload = __DIR__ . '/vendor/autoload.php';
  $composerJson   = __DIR__ . '/composer.json';

  // Vendor déjà présent et complet
  if (file_exists($vendorAutoload) && file_exists(__DIR__ . '/vendor/sprain')) {
    return;
  }

  if (!file_exists($composerJson)) {
    CRM_Core_Error::debug_log_message('SwissQRInvoice: composer.json introuvable, vendor non installé.');
    return;
  }

  // Trouver composer
  $composerBin = null;
  foreach (['/usr/bin/composer', '/usr/local/bin/composer', '/home/' . get_current_user() . '/bin/composer'] as $p) {
    if (file_exists($p)) { $composerBin = $p; break; }
  }
  if (!$composerBin) {
    $composerBin = trim(shell_exec('which composer 2>/dev/null') ?: '');
  }
  if (!$composerBin || !file_exists($composerBin)) {
    CRM_Core_Error::debug_log_message('SwissQRInvoice: composer introuvable, vendor non installé.');
    return;
  }

  $cmd    = escapeshellarg($composerBin) . ' install --no-dev --no-interaction --quiet 2>&1';
  $output = [];
  $code   = 0;
  exec("cd " . escapeshellarg(__DIR__) . " && {$cmd}", $output, $code);

  if ($code !== 0) {
    CRM_Core_Error::debug_log_message('SwissQRInvoice composer error: ' . implode("\n", $output));
  } else {
    CRM_Core_Error::debug_log_message('SwissQRInvoice: composer install OK.');
  }
}

/**
 * Installation : tables SQL + routes + menu + type financier.
 * Tout est idempotent (safe à relancer).
 */
function swissQRinvoice_civicrm_install() {
  // 0. Dépendances composer
  _swissQRinvoice_composer_install();

  // 1. Tables SQL
  $sqlDir = __DIR__ . '/sql';
  if (file_exists("{$sqlDir}/install.sql")) {
    CRM_Utils_File::sourceSQLFile(CIVICRM_DSN, "{$sqlDir}/install.sql");
  }

  // 2. Routes civicrm_menu
  _swissQRinvoice_install_routes();

  // 3. Menu de navigation
  _swissQRinvoice_install_navigation();

  // 4. Type financier
  _swissQRinvoice_install_financial_type();

  // 5. Template email CiviCRM
  _swissQRinvoice_install_msg_template();
}

function swissQRinvoice_civicrm_uninstall() {
  // Tables
  $sqlDir = __DIR__ . '/sql';
  if (file_exists("{$sqlDir}/uninstall.sql")) {
    CRM_Utils_File::sourceSQLFile(CIVICRM_DSN, "{$sqlDir}/uninstall.sql");
  }
  // Routes
  CRM_Core_DAO::executeQuery("DELETE FROM civicrm_menu WHERE path LIKE 'civicrm/swissqr%' OR path = 'civicrm/admin/swissqr/settings'");
  // Menu
  CRM_Core_DAO::executeQuery("DELETE FROM civicrm_navigation WHERE name LIKE 'swissqr_%'");
  // Vider cache
  CRM_Core_Config::clearDBCache();
}

function swissQRinvoice_civicrm_enable() {
  // Dépendances composer si vendor absent
  _swissQRinvoice_composer_install();

  // Template email si absent
  _swissQRinvoice_install_msg_template();

  // Réinstaller routes et menu si absents (ex: après désactivation)
  $count = (int) CRM_Core_DAO::singleValueQuery(
    "SELECT COUNT(*) FROM civicrm_menu WHERE path = 'civicrm/swissqr/invoice/list'"
  );
  if (!$count) {
    _swissQRinvoice_install_routes();
  }
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

/**
 * Installe les routes dans civicrm_menu.
 */
function _swissQRinvoice_install_routes(): void {
  $aclCheck  = serialize(['CRM_Core_Permission', 'checkMenu']);
  $argUser   = serialize([['access CiviCRM'], 'and']);
  $argEdit   = serialize([['edit swissqr invoices'], 'and']);
  $argAdmin  = serialize([['administer CiviCRM'], 'and']);

  $routes = [
    ['civicrm/swissqr/invoice/list',     'Toutes les factures',  'CRM_SwissQRInvoice_Page_InvoiceList', $argUser],
    ['civicrm/swissqr/invoice/new',      'Nouvelle facture',     'CRM_SwissQRInvoice_Form_Invoice',     $argUser],
    ['civicrm/swissqr/invoice/edit',     'Modifier la facture',  'CRM_SwissQRInvoice_Form_Invoice',     $argUser],
    ['civicrm/swissqr/invoice/pdf',      'Télécharger PDF',      'CRM_SwissQRInvoice_Page_PDF',         $argUser],
    ['civicrm/swissqr/invoice/send',     'Envoyer la facture',   'CRM_SwissQRInvoice_Form_Send',        $argEdit],
    ['civicrm/swissqr/invoice/markpaid', 'Marquer comme payée',  'CRM_SwissQRInvoice_Form_MarkPaid',    $argEdit],
    ['civicrm/admin/swissqr/settings',   'Paramètres SwissQR',   'CRM_SwissQRInvoice_Form_Settings',    $argAdmin],
    ['civicrm/swissqr/services',         'Prestations',          'CRM_SwissQRInvoice_Page_Services',    $argUser],
    ['civicrm/swissqr/invoice/duplicate', 'Dupliquer',            'CRM_SwissQRInvoice_Page_Duplicate',   $argEdit],
    ['civicrm/swissqr/invoice/cancel',    'Annuler la facture',   'CRM_SwissQRInvoice_Page_Cancel',          $argEdit],
    ['civicrm/swissqr/invoice/cancel-duplicate', 'Annuler duplicata', 'CRM_SwissQRInvoice_Page_CancelDuplicate', $argEdit],
  ];

  // Supprimer les anciennes
  CRM_Core_DAO::executeQuery(
    "DELETE FROM civicrm_menu WHERE path LIKE 'civicrm/swissqr%' OR path = 'civicrm/admin/swissqr/settings'"
  );

  foreach ($routes as [$path, $title, $callback, $args]) {
    CRM_Core_DAO::executeQuery(
      "INSERT INTO civicrm_menu
         (domain_id, path, title, page_callback, access_callback, access_arguments, is_active, is_public, is_exposed, weight, type, page_type)
       VALUES (1, %1, %2, %3, %4, %5, 1, 0, 1, 1, 1, 1)",
      [
        1 => [$path,     'String'],
        2 => [$title,    'String'],
        3 => [$callback, 'String'],
        4 => [$aclCheck, 'String'],
        5 => [$args,     'String'],
      ]
    );
  }
}

/**
 * Installe le menu de navigation CiviCRM.
 */
function _swissQRinvoice_install_navigation(): void {
  // Supprimer les anciennes entrées
  CRM_Core_DAO::executeQuery("DELETE FROM civicrm_navigation WHERE name LIKE 'swissqr_%'");

  // Entrée racine "Facturation" avec icône
  CRM_Core_DAO::executeQuery(
    "INSERT INTO civicrm_navigation
       (domain_id, label, name, url, permission, permission_operator, parent_id, is_active, has_separator, weight, icon)
     VALUES (1, 'Facturation', 'swissqr_facturation', NULL, 'access CiviCRM', 'OR', NULL, 1, 0, 35, 'crm-i fa-file-invoice')"
  );
  $parentId = (int) CRM_Core_DAO::singleValueQuery("SELECT LAST_INSERT_ID()");

  $items = [
    ['Toutes les factures', 'swissqr_list',     'civicrm/swissqr/invoice/list?reset=1',    'access CiviCRM',       10, 0],
    ['Nouvelle facture',    'swissqr_new',      'civicrm/swissqr/invoice/new?reset=1',     'access CiviCRM',       20, 0],
    ['Prestations',         'swissqr_services', 'civicrm/swissqr/services?reset=1',        'access CiviCRM',       30, 0],
    ['Paramètres',          'swissqr_settings', 'civicrm/admin/swissqr/settings?reset=1',  'administer CiviCRM',   40, 1],
  ];

  foreach ($items as [$label, $name, $url, $perm, $weight, $sep]) {
    CRM_Core_DAO::executeQuery(
      "INSERT INTO civicrm_navigation
         (domain_id, label, name, url, permission, permission_operator, parent_id, is_active, has_separator, weight)
       VALUES (1, %1, %2, %3, %4, 'OR', %5, 1, %6, %7)",
      [
        1 => [$label,    'String'],
        2 => [$name,     'String'],
        3 => [$url,      'String'],
        4 => [$perm,     'String'],
        5 => [$parentId, 'Integer'],
        6 => [$sep,      'Integer'],
        7 => [$weight,   'Integer'],
      ]
    );
  }
}

/**
 * Crée le type financier "Facture QR" si absent.
 */
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
  // Lier les comptes financiers (copier depuis Dons ft_id=1)
  $ftId = (int) CRM_Core_DAO::singleValueQuery(
    "SELECT id FROM civicrm_financial_type WHERE name = 'Facture QR' LIMIT 1"
  );
  $linked = (int) CRM_Core_DAO::singleValueQuery(
    "SELECT COUNT(*) FROM civicrm_entity_financial_account
     WHERE entity_table = 'civicrm_financial_type' AND entity_id = %1",
    [1 => [$ftId, 'Integer']]
  );
  if (!$linked && $ftId) {
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

function swissQRinvoice_civicrm_xmlMenu(&$files) {
  $files[] = __DIR__ . '/xml/Menu/swissQRinvoice.xml';
}

function swissQRinvoice_civicrm_alterMenu(&$items) {
  $routes = [
    'civicrm/swissqr/invoice/list'     => ['Toutes les factures',  'CRM_SwissQRInvoice_Page_InvoiceList'],
    'civicrm/swissqr/invoice/new'      => ['Nouvelle facture',     'CRM_SwissQRInvoice_Form_Invoice'],
    'civicrm/swissqr/invoice/edit'     => ['Modifier la facture',  'CRM_SwissQRInvoice_Form_Invoice'],
    'civicrm/swissqr/invoice/pdf'      => ['Télécharger PDF',      'CRM_SwissQRInvoice_Page_PDF'],
    'civicrm/swissqr/invoice/send'     => ['Envoyer la facture',   'CRM_SwissQRInvoice_Form_Send',    'edit swissqr invoices'],
    'civicrm/swissqr/invoice/markpaid' => ['Marquer comme payée',  'CRM_SwissQRInvoice_Form_MarkPaid', 'edit swissqr invoices'],
    'civicrm/admin/swissqr/settings'   => ['Paramètres SwissQR',   'CRM_SwissQRInvoice_Form_Settings'],
    'civicrm/swissqr/services'         => ['Prestations',          'CRM_SwissQRInvoice_Page_Services'],
    'civicrm/swissqr/invoice/duplicate' => ['Dupliquer la facture', 'CRM_SwissQRInvoice_Page_Duplicate'],
    'civicrm/swissqr/invoice/cancel'    => ['Annuler la facture',   'CRM_SwissQRInvoice_Page_Cancel', 'edit swissqr invoices'],
    'civicrm/swissqr/invoice/cancel-duplicate' => ['Annuler duplicata', 'CRM_SwissQRInvoice_Page_CancelDuplicate', 'edit swissqr invoices'],
  ];
  foreach ($routes as $path => $info) {
    $items[$path] = [
      'title'            => $info[0],
      'page_callback'    => $info[1],
      'access_arguments' => [[$info[2] ?? 'access CiviCRM']],
    ];
  }
}

function swissQRinvoice_civicrm_permission(&$permissions) {
  $permissions['access swissqr invoices'] = [
    'label'       => ts('Swiss QR Invoice: consulter les factures'),
    'description' => ts('Voir la liste et les détails des factures QR.'),
  ];
  $permissions['edit swissqr invoices'] = [
    'label'       => ts('Swiss QR Invoice: créer et modifier des factures'),
    'description' => ts('Créer, éditer, envoyer et marquer payées les factures QR.'),
  ];
}

/**
 * Installe le template d'email dans civicrm_msg_template.
 * Idempotent : ne recrée pas si déjà présent.
 */
function _swissQRinvoice_install_msg_template(): void {
  $exists = (int) CRM_Core_DAO::singleValueQuery(
    "SELECT COUNT(*) FROM civicrm_msg_template WHERE msg_title = 'swissqrinvoice_send'"
  );
  if ($exists) {
    // Mettre à jour le token si l'ancienne version avec {contact.display_name} est présente
    CRM_Core_DAO::executeQuery(
      "UPDATE civicrm_msg_template
       SET msg_html = REPLACE(msg_html, '{contact.display_name}', '{contact_name}'),
           msg_text = REPLACE(msg_text, '{contact.display_name}', '{contact_name}')
       WHERE msg_title = 'swissqrinvoice_send'"
    );
    return;
  }

  $bodyHtml = <<<HTML
<p>Madame, Monsieur {contact_name},</p>

<p>Veuillez trouver ci-joint la facture n° <strong>{invoice_number}</strong>
du {invoice_date} pour un montant de <strong>CHF {amount_due}</strong>.</p>

<p>Nous vous remercions de votre règlement avant l'échéance indiquée sur la facture.</p>

<p>N'hésitez pas à nous contacter pour toute question.</p>

<p>Avec nos meilleures salutations,<br>
{organization_name}</p>
HTML;

  $bodyText = "Madame, Monsieur {contact_name},\n\n"
    . "Veuillez trouver ci-joint la facture n° {invoice_number} "
    . "du {invoice_date} pour un montant de CHF {amount_due}.\n\n"
    . "Nous vous remercions de votre règlement avant l'échéance indiquée sur la facture.\n\n"
    . "Avec nos meilleures salutations,\n{organization_name}";

  CRM_Core_DAO::executeQuery(
    "INSERT INTO civicrm_msg_template
       (msg_title, msg_subject, msg_text, msg_html, is_active, is_default, is_reserved)
     VALUES (%1, %2, %3, %4, 1, 1, 0)",
    [
      1 => ['swissqrinvoice_send',           'String'],
      2 => ['Facture N° {invoice_number}',   'String'],
      3 => [$bodyText,                        'String'],
      4 => [$bodyHtml,                        'String'],
    ]
  );
}

function swissQRinvoice_civicrm_tabset($tabsetName, &$tabs, $context) {
  if ($tabsetName !== 'civicrm/contact/view') return;
  if (!CRM_Core_Permission::check('access CiviCRM')) return;
  $contactID = (int)($context['contact_id'] ?? 0);
  if (!$contactID) return;
  $count = CRM_SwissQRInvoice_BAO_Invoice::getCountForContact($contactID);
  $tabs['swissqr_invoices'] = [
    'id'     => 'swissqr_invoices',
    'url'    => CRM_Utils_System::url('civicrm/swissqr/invoice/list', "cid={$contactID}&reset=1"),
    'title'  => ts('Factures QR') . ($count ? " ({$count})" : ''),
    'weight' => 150,
    'valid'  => 1,
    'active' => 1,
    'class'  => 'livePage',
  ];
}

// Garder l'ancien hook pour compatibilité éventuelle
function swissQRinvoice_civicrm_tabs(&$tabs, $contactID) {
  if (!CRM_Core_Permission::check('access CiviCRM')) return;
  $count = CRM_SwissQRInvoice_BAO_Invoice::getCountForContact($contactID);
  $tabs[] = [
    'id'     => 'swissqr_invoices',
    'url'    => CRM_Utils_System::url('civicrm/swissqr/invoice/list', "cid={$contactID}&reset=1"),
    'title'  => ts('Factures QR') . ($count ? " ({$count})" : ''),
    'weight' => 150,
  ];
}


