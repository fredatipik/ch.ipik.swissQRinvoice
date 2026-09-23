<?php
/**
 * BAO pour les factures Swiss QR.
 */
class CRM_SwissQRInvoice_BAO_Invoice {

  public static function save(array $params): array {
    $invoiceId = !empty($params['id']) ? (int)$params['id'] : null;

    if (!$invoiceId) {
      if (empty($params['invoice_number'])) {
        $params['invoice_number'] = CRM_SwissQRInvoice_Utils::nextInvoiceNumber();
      }
      // Never let two invoices share a number, whatever the form sent.
      $taken = (int) CRM_Core_DAO::singleValueQuery(
        'SELECT COUNT(*) FROM civicrm_swissqr_invoice WHERE invoice_number = %1',
        [1 => [$params['invoice_number'], 'String']]
      );
      if ($taken) {
        $params['invoice_number'] = CRM_SwissQRInvoice_Utils::nextInvoiceNumber();
        $params['reference'] = '';
      }
      if (empty($params['reference'])) {
        $tpl = Civi::settings()->get('swissqr_qr_reference_template') ?: 'Facture N° {NUMBER}';
        $params['reference'] = str_replace('{NUMBER}', $params['invoice_number'], $tpl);
      }
      $params['created_by'] = CRM_Core_Session::getLoggedInContactID();
      $params['created_at'] = date('Y-m-d H:i:s');
      $params['status'] = $params['status'] ?? 'draft';
    }
    $params['updated_at'] = date('Y-m-d H:i:s');

    // Forcer NULL pour les champs optionnels vides
    foreach (['contribution_id','payment_contribution_id','due_date','paid_date','sent_date','created_by'] as $f) {
      if (array_key_exists($f, $params) && ($params[$f] === '' || $params[$f] === null)) {
        $params[$f] = null;
      }
    }

    $cols = ['invoice_number','contact_id','organization_contact_id','invoice_date','due_date',
             'status','amount_paid','discount_type','discount_value','notes','reference',
             'contribution_id','payment_contribution_id','paid_date','sent_date','sent_to_email',
             'created_by','created_at','updated_at'];
    $colTypes = [
      'contact_id'=>'Integer','organization_contact_id'=>'Integer',
      'contribution_id'=>'Integer','payment_contribution_id'=>'Integer',
      'created_by'=>'Integer','amount_paid'=>'Float','discount_value'=>'Float',
    ];
    $sets=[]; $args=[]; $i=1;
    foreach ($cols as $col) {
      if (!array_key_exists($col, $params)) continue;
      $val  = $params[$col];
      $type = $colTypes[$col] ?? 'String';
      if (($val === '' || $val === null) && in_array($col, ['contribution_id','payment_contribution_id','due_date','paid_date','sent_date','created_by'])) {
        $sets[] = "`{$col}` = NULL";
        continue;
      }
      $sets[] = "`{$col}` = %{$i}";
      $args[$i] = [$val, $type];
      $i++;
    }
    if ($sets) {
      if ($invoiceId) {
        $args[$i] = [$invoiceId, 'Integer'];
        CRM_Core_DAO::executeQuery("UPDATE civicrm_swissqr_invoice SET ".implode(', ',$sets)." WHERE id = %{$i}", $args);
      } else {
        CRM_Core_DAO::executeQuery("INSERT INTO civicrm_swissqr_invoice SET ".implode(', ',$sets), $args);
        $invoiceId = (int) CRM_Core_DAO::singleValueQuery("SELECT LAST_INSERT_ID()");
      }
    }

    if (isset($params['lines']) && is_array($params['lines'])) {
      CRM_Core_DAO::executeQuery("DELETE FROM civicrm_swissqr_invoice_line WHERE invoice_id = %1", [1=>[$invoiceId,'Integer']]);
      $sortOrder = 0;
      foreach ($params['lines'] as $line) {
        $lt = round((float)($line['unit_price']??0)*(float)($line['quantity']??1),2);
        CRM_Core_DAO::executeQuery(
          "INSERT INTO civicrm_swissqr_invoice_line (invoice_id,article,description,unit_price,quantity,line_total,sort_order) VALUES (%1,%2,%3,%4,%5,%6,%7)",
          [1=>[$invoiceId,'Integer'],2=>[$line['article']??'','String'],3=>[$line['description']??'','String'],
           4=>[(float)($line['unit_price']??0),'Float'],5=>[(float)($line['quantity']??1),'Float'],
           6=>[$lt,'Float'],7=>[++$sortOrder,'Integer']]
        );
      }
    }
    return self::getById($invoiceId);
  }

  public static function getById(int $id): array {
    $dao = CRM_Core_DAO::executeQuery(
      "SELECT i.*, c.display_name AS contact_name FROM civicrm_swissqr_invoice i LEFT JOIN civicrm_contact c ON c.id = i.contact_id WHERE i.id = %1",
      [1=>[$id,'Integer']]
    );
    if (!$dao->fetch()) return [];
    $invoice = $dao->toArray();
    $linesDao = CRM_Core_DAO::executeQuery("SELECT * FROM civicrm_swissqr_invoice_line WHERE invoice_id = %1 ORDER BY sort_order", [1=>[$id,'Integer']]);
    $lines = [];
    while ($linesDao->fetch()) $lines[] = $linesDao->toArray();
    $subtotal = array_sum(array_column($lines,'line_total'));
    // Calcul rabais
    $discount = 0;
    if (($invoice['discount_type']??'none') === 'amount') {
      $discount = (float)($invoice['discount_value']??0);
    } elseif (($invoice['discount_type']??'none') === 'percent') {
      $discount = round($subtotal * (float)($invoice['discount_value']??0) / 100, 2);
    }
    $total = $subtotal - $discount;
    $invoice['lines']      = $lines;
    $invoice['subtotal']   = $subtotal;
    $invoice['discount']   = $discount;
    $invoice['total']      = $total;
    $invoice['amount_due'] = $total - (float)($invoice['amount_paid']??0);
    return $invoice;
  }

  public static function getList(array $filters=[], int $page=1, int $perPage=25): array {
    $where=['1=1']; $args=[]; $i=1;
    if (!empty($filters['status']))     { $where[]="i.status=%{$i}"; $args[$i]=[$filters['status'],'String']; $i++; }
    if (!empty($filters['contact_id'])) { $where[]="(i.contact_id=%{$i} OR i.organization_contact_id=%{$i})"; $args[$i]=[(int)$filters['contact_id'],'Integer']; $i++; }
    if (!empty($filters['date_from']))  { $where[]="i.invoice_date>=%{$i}"; $args[$i]=[$filters['date_from'],'String']; $i++; }
    if (!empty($filters['date_to']))    { $where[]="i.invoice_date<=%{$i}"; $args[$i]=[$filters['date_to'],'String']; $i++; }
    $w=implode(' AND ',$where);
    $offset=($page-1)*$perPage;
    $rows = CRM_Core_DAO::executeQuery(
      "SELECT i.*, c.display_name AS contact_name, cr.display_name AS creator_name,
        (SELECT SUM(l.line_total) FROM civicrm_swissqr_invoice_line l WHERE l.invoice_id=i.id) AS subtotal
       FROM civicrm_swissqr_invoice i
       LEFT JOIN civicrm_contact c ON c.id=i.contact_id
       LEFT JOIN civicrm_contact cr ON cr.id=i.created_by
       WHERE {$w} ORDER BY i.invoice_date DESC,i.id DESC LIMIT {$perPage} OFFSET {$offset}",
      $args
    )->fetchAll();
    // Calcul total avec rabais pour chaque ligne
    foreach ($rows as &$row) {
      $sub = (float)($row['subtotal']??0);
      $disc = 0;
      if (($row['discount_type']??'none') === 'amount') $disc = (float)($row['discount_value']??0);
      elseif (($row['discount_type']??'none') === 'percent') $disc = round($sub*(float)($row['discount_value']??0)/100,2);
      $row['total']      = $sub - $disc;
      $row['amount_due'] = $row['total'] - (float)($row['amount_paid']??0);
    }
    $total = (int)CRM_Core_DAO::singleValueQuery("SELECT COUNT(*) FROM civicrm_swissqr_invoice i WHERE {$w}", $args);
    return ['rows'=>$rows,'total'=>$total,'pages'=>(int)ceil($total/$perPage)];
  }

  public static function getCountForContact(int $contactId): int {
    return (int)CRM_Core_DAO::singleValueQuery(
      "SELECT COUNT(*) FROM civicrm_swissqr_invoice WHERE contact_id=%1 OR organization_contact_id=%1",
      [1=>[$contactId,'Integer']]
    );
  }

  /**
   * Record a payment. Goes through the invoice's contribution, so the result is
   * identical to a payment imported from the bank: CiviCRM sets the status.
   */
  public static function markAsPaid(int $invoiceId, float $amount, string $paidDate): array {
    $invoice = self::getById($invoiceId);
    if (!$invoice) {
      throw new CRM_Core_Exception(ts('Invoice not found.', ['domain' => CRM_SwissQRInvoice_Utils::DOMAIN]));
    }
    CRM_SwissQRInvoice_Contribution::recordPayment($invoice, $amount, $paidDate);
    return self::getById($invoiceId);
  }

  /**
   * Create one invoice for a contact and issue it (pending contribution).
   * Used by the bulk "Create QR invoices" action.
   *
   * @param array $spec  invoice_date, due_date, notes, lines[]
   */
  public static function createIssued(int $contactId, array $spec): array {
    $invoice = self::save([
      'contact_id'              => $contactId,
      'organization_contact_id' => (int) Civi::settings()->get('swissqr_org_contact_id'),
      'invoice_date'            => $spec['invoice_date'],
      'due_date'                => $spec['due_date'] ?: NULL,
      'notes'                   => $spec['notes'] ?? NULL,
      'discount_type'           => 'none',
      'discount_value'          => 0,
      'amount_paid'             => 0,
      'lines'                   => $spec['lines'],
    ]);
    CRM_SwissQRInvoice_Contribution::ensurePending($invoice);
    return self::getById((int) $invoice['id']);
  }

  public static function getInvoiceFinancialTypeId(): int {
    $id = CRM_Core_DAO::singleValueQuery(
      "SELECT id FROM civicrm_financial_type WHERE name = 'Facture QR' AND is_active = 1 LIMIT 1"
    );
    if (!$id) {
      $id = CRM_Core_DAO::singleValueQuery(
        "SELECT id FROM civicrm_financial_type WHERE is_active = 1 ORDER BY id LIMIT 1"
      );
    }
    return (int)$id;
  }

  public static function duplicate(int $invoiceId): array {
    $original = self::getById($invoiceId);
    if (!$original) throw new CRM_Core_Exception(ts('Invoice not found.', ['domain' => 'ch.ipik.swissQRinvoice']));
    $params = [
      'contact_id'              => $original['contact_id'],
      'organization_contact_id' => $original['organization_contact_id'],
      'invoice_date'            => date('Y-m-d'),
      'due_date'                => date('Y-m-d', strtotime('+30 days')),
      'notes'                   => $original['notes'],
      'discount_type'           => $original['discount_type'],
      'discount_value'          => $original['discount_value'],
      'amount_paid'             => 0,
      'status'                  => 'draft',
      'lines'                   => $original['lines'],
    ];
    return self::save($params);
  }

  public static function cancel(int $invoiceId): void {
    $invoice = self::getById($invoiceId);
    if ($invoice) {
      CRM_SwissQRInvoice_Contribution::cancel($invoice);
    }
    CRM_Core_DAO::executeQuery(
      "UPDATE civicrm_swissqr_invoice SET status='cancelled', updated_at=%1 WHERE id=%2",
      [1 => [date('Y-m-d H:i:s'), 'String'], 2 => [$invoiceId, 'Integer']]
    );
  }

  /**
   * Mark an invoice as sent and issue it. Sending again a paid invoice keeps it paid.
   */
  public static function recordSent(int $invoiceId, string $email): void {
    $invoice = self::getById($invoiceId);
    if (!$invoice) {
      return;
    }
    CRM_SwissQRInvoice_Contribution::ensurePending($invoice);
    $params = ['id' => $invoiceId, 'sent_date' => date('Y-m-d H:i:s'), 'sent_to_email' => $email];
    if ($invoice['status'] === 'draft') {
      $params['status'] = 'sent';
    }
    self::save($params);
  }

  /**
   * An issued invoice has a contribution: its amounts are in the books and it
   * can no longer be edited, only cancelled and replaced.
   */
  public static function isEditable(array $invoice): bool {
    return ($invoice['status'] ?? '') === 'draft' && empty($invoice['payment_contribution_id']);
  }

  // ── Services (prestations) ────────────────────────────────────────────────
  public static function getServices(): array {
    $dao = CRM_Core_DAO::executeQuery(
      "SELECT * FROM civicrm_swissqr_service WHERE is_active=1 ORDER BY sort_order,name"
    );
    $services = [];
    while ($dao->fetch()) $services[] = $dao->toArray();
    return $services;
  }

  public static function saveService(array $params): int {
    if (!empty($params['id'])) {
      CRM_Core_DAO::executeQuery(
        "UPDATE civicrm_swissqr_service SET name=%1,description=%2,unit_price=%3,is_active=%4 WHERE id=%5",
        [1=>[$params['name'],'String'],2=>[$params['description']??'','String'],
         3=>[(float)$params['unit_price'],'Float'],4=>[(int)($params['is_active']??1),'Integer'],
         5=>[(int)$params['id'],'Integer']]
      );
      return (int)$params['id'];
    }
    CRM_Core_DAO::executeQuery(
      "INSERT INTO civicrm_swissqr_service (name,description,unit_price,is_active,sort_order) VALUES (%1,%2,%3,%4,%5)",
      [1=>[$params['name'],'String'],2=>[$params['description']??'','String'],
       3=>[(float)($params['unit_price']??0),'Float'],4=>[(int)($params['is_active']??1),'Integer'],
       5=>[(int)($params['sort_order']??0),'Integer']]
    );
    return (int)CRM_Core_DAO::singleValueQuery("SELECT LAST_INSERT_ID()");
  }

  public static function deleteService(int $id): void {
    CRM_Core_DAO::executeQuery("DELETE FROM civicrm_swissqr_service WHERE id=%1",[1=>[$id,'Integer']]);
  }
}
