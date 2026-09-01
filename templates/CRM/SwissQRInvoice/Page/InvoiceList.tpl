{crmScope extensionKey='com.ipik.swissQRinvoice'}
<style>
.swissqr-badge { display:inline-block; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:600; white-space:nowrap; }
.swissqr-draft     { background:#fff3cd; color:#7a5c00; border:1px solid #f0c430; }
.swissqr-sent      { background:#1565c0; color:#fff;    border:1px solid #0d47a1; }
.swissqr-paid      { background:#2e7d32; color:#fff;    border:1px solid #1b5e20; }
.swissqr-cancelled { background:#eeeeee; color:#555;    border:1px solid #ccc; }
.swissqr-filter-bar { display:flex; flex-wrap:wrap; gap:8px; align-items:flex-end; margin:12px 0; }
.swissqr-filter-bar label { font-weight:600; margin-bottom:2px; display:block; font-size:12px; }
.swissqr-filter-group { display:flex; flex-direction:column; }
.swissqr-contact-wrap { position:relative; }
.swissqr-contact-wrap input { padding-right:24px; }
.swissqr-contact-clear { position:absolute; right:4px; top:50%; transform:translateY(-50%); cursor:pointer; color:#888; font-size:14px; line-height:1; border:none; background:none; padding:0; display:{if $filters.contact_id}inline{else}none{/if}; }
</style>

<div class="crm-content-block crm-block">
  {if $canEdit}
  <div class="action-link" style="margin-bottom:8px">
    <a href="{crmURL p='civicrm/swissqr/invoice/new' q='reset=1'}" class="button"><span><i class="crm-i fa-plus-circle"></i> Nouvelle facture</span></a>
    <a href="{crmURL p='civicrm/swissqr/services' q='reset=1'}" class="button" style="margin-left:8px"><span><i class="crm-i fa-list"></i> Prestations</span></a>
  </div>
  {/if}

  {* Barre de filtres *}
  <form method="get" action="{crmURL p='civicrm/swissqr/invoice/list'}" id="swissqr-filter-form">
    <div class="swissqr-filter-bar">

      <div class="swissqr-filter-group">
        <label>Contact</label>
        <div class="swissqr-contact-wrap">
          <input type="text" id="swissqr-contact-display" placeholder="Rechercher un contact…"
                 value="{$filterContactName|escape}" autocomplete="off"
                 style="width:200px">
          <input type="hidden" name="cid" id="swissqr-contact-id" value="{$filters.contact_id|escape}">
          <button type="button" class="swissqr-contact-clear" id="swissqr-contact-clear" title="Effacer">×</button>
        </div>
        <div id="swissqr-contact-results" style="position:absolute;z-index:999;background:#fff;border:1px solid #ccc;border-radius:4px;width:220px;display:none;box-shadow:0 2px 6px rgba(0,0,0,.15)"></div>
      </div>

      <div class="swissqr-filter-group">
        <label>Statut</label>
        <select name="status" style="height:30px">
          <option value="">Tous</option>
          <option value="draft"     {if $filters.status == 'draft'}selected{/if}>Brouillon</option>
          <option value="sent"      {if $filters.status == 'sent'}selected{/if}>Envoyée</option>
          <option value="paid"      {if $filters.status == 'paid'}selected{/if}>Payée</option>
          <option value="cancelled" {if $filters.status == 'cancelled'}selected{/if}>Annulée</option>
        </select>
      </div>

      <div class="swissqr-filter-group">
        <label>Du</label>
        <input type="date" name="date_from" value="{$filters.date_from|escape}" style="height:30px">
      </div>

      <div class="swissqr-filter-group">
        <label>Au</label>
        <input type="date" name="date_to" value="{$filters.date_to|escape}" style="height:30px">
      </div>

      <div class="swissqr-filter-group">
        <label>&nbsp;</label>
        <button type="submit" class="button" style="height:30px"><span><i class="crm-i fa-filter"></i> Filtrer</span></button>
      </div>

      {if $filters.status || $filters.contact_id || $filters.date_from || $filters.date_to}
      <div class="swissqr-filter-group">
        <label>&nbsp;</label>
        <a href="{crmURL p='civicrm/swissqr/invoice/list' q='reset=1'}" class="button" style="height:30px;line-height:30px"><span>Réinitialiser</span></a>
      </div>
      {/if}
    </div>
  </form>

  <p class="description" style="margin:4px 0 8px">{$total} facture(s)</p>

  {if $invoices}
  <table class="crm-datatable display" id="swissqr-invoice-table" style="width:100%">
    <thead>
    <tr>
      <th>N° Facture</th>
      <th>Contact</th>
      <th>Date</th>
      <th>Échéance</th>
      <th style="text-align:right">Montant</th>
      <th style="text-align:right">Solde dû</th>
      <th>Créé par</th>
      <th>Statut</th>
      <th class="crm-no-sort">Actions</th>
    </tr>
    </thead>
    <tbody>
    {foreach from=$invoices item=inv}
    <tr {if $inv.status == 'cancelled'}style="opacity:.55"{/if}>
      <td>{$inv.invoice_number|escape}</td>
      <td><a href="{crmURL p='civicrm/contact/view' q="reset=1&cid=`$inv.contact_id`"}">{$inv.contact_name|escape}</a></td>
      <td>{$inv.invoice_date|crmDate}</td>
      <td>{if $inv.due_date}{$inv.due_date|crmDate}{/if}</td>
      <td style="text-align:right">CHF {$inv.total|string_format:"%.2f"}</td>
      <td style="text-align:right">CHF {$inv.amount_due|string_format:"%.2f"}</td>
      <td>{$inv.creator_name|escape}</td>
      <td>
        {if $inv.status == 'draft'}
          <span class="swissqr-badge swissqr-draft">Brouillon</span>
        {elseif $inv.status == 'sent'}
          <span class="swissqr-badge swissqr-sent">Envoyée</span>
        {elseif $inv.status == 'paid'}
          <span class="swissqr-badge swissqr-paid">Payée le {$inv.paid_date|crmDate}</span>
        {elseif $inv.status == 'cancelled'}
          <span class="swissqr-badge swissqr-cancelled">Annulée</span>
        {/if}
      </td>
      <td style="white-space:nowrap">
        <a href="{crmURL p='civicrm/swissqr/invoice/pdf' q="id=`$inv.id`"}" title="Télécharger PDF" target="_blank"><i class="crm-i fa-download"></i></a>
        {if $inv.status != 'cancelled'}
        &nbsp;
        {if $canEdit && $inv.status == 'draft'}
        <a href="{crmURL p='civicrm/swissqr/invoice/edit' q="id=`$inv.id`&reset=1"}" title="Modifier"><i class="crm-i fa-pencil"></i></a>
        &nbsp;
        {/if}
        {if $canEdit}
        <a href="{crmURL p='civicrm/swissqr/invoice/duplicate' q="id=`$inv.id`&reset=1"}" title="Dupliquer"><i class="crm-i fa-copy"></i></a>
        &nbsp;
        <a href="{crmURL p='civicrm/swissqr/invoice/send' q="id=`$inv.id`&reset=1"}" title="Envoyer par email"><i class="crm-i fa-envelope"></i></a>
        &nbsp;
        {if $inv.status != 'paid'}
        <a href="{crmURL p='civicrm/swissqr/invoice/markpaid' q="id=`$inv.id`&reset=1"}" title="Marquer comme payée"><i class="crm-i fa-check-circle" style="color:#2e7d32"></i></a>
        &nbsp;
        <a href="{crmURL p='civicrm/swissqr/invoice/cancel' q="id=`$inv.id`&reset=1"}" title="Annuler la facture" onclick="return confirm('Annuler la facture {$inv.invoice_number|escape} ?')"><i class="crm-i fa-ban" style="color:#c62828"></i></a>
        {/if}
        {/if}
        {/if}
      </td>
    </tr>
    {/foreach}
    </tbody>
  </table>

  {if $pages > 1}
  <div class="crm-pager" style="margin-top:8px">
    {for $i=1 to $pages}
    <a href="{crmURL p='civicrm/swissqr/invoice/list' q="p=`$i`&status=`$filters.status`&cid=`$filters.contact_id`&date_from=`$filters.date_from`&date_to=`$filters.date_to`"}"
       {if $i == $page}style="font-weight:bold"{/if}>{$i}</a>
    {/for}
  </div>
  {/if}

  {else}
  <div class="messages status no-popup">
    <i class="crm-i fa-info-circle"></i> Aucune facture trouvée.
    {if $canEdit}<a href="{crmURL p='civicrm/swissqr/invoice/new' q='reset=1'}">Créer une première facture</a>.{/if}
  </div>
  {/if}
</div>

{literal}
<script>
(function($) {
  // DataTables init sur le tableau factures
  if ($.fn.dataTable && $('#swissqr-invoice-table').length) {
    $('#swissqr-invoice-table').DataTable({
      paging:    false,
      info:      false,
      searching: false,
      order:     [[2, 'desc']], // tri par date desc par défaut
      columnDefs: [
        { orderable: false, targets: 8 }, // colonne Actions
        { type: 'num', targets: [4, 5] }  // montants numériques
      ],
      language: {
        decimal:  ',',
        thousands: "'",
        zeroRecords: 'Aucune facture trouvée.'
      }
    });
  }

  // Autocomplete contact
  var $display = $('#swissqr-contact-display');
  var $hidden  = $('#swissqr-contact-id');
  var $results = $('#swissqr-contact-results');
  var $clear   = $('#swissqr-contact-clear');
  var timer;

  $display.on('input', function() {
    clearTimeout(timer);
    var q = $(this).val();
    $hidden.val('');
    if (q.length < 2) { $results.hide(); return; }
    timer = setTimeout(function() {
      CRM.api3('Contact', 'get', {
        input: q, return: 'id,display_name', rowCount: 10,
        contact_type: '', is_deleted: 0
      }).done(function(data) {
        $results.empty();
        if (!data.values || !Object.keys(data.values).length) {
          $results.hide(); return;
        }
        $.each(data.values, function(_, c) {
          $('<div>').text(c.display_name)
            .css({padding:'6px 10px', cursor:'pointer'})
            .hover(function(){ $(this).css('background','#f5f5f5'); },
                   function(){ $(this).css('background',''); })
            .on('click', function() {
              $display.val(c.display_name);
              $hidden.val(c.id);
              $clear.show();
              $results.hide();
              $('#swissqr-filter-form').submit();
            })
            .appendTo($results);
        });
        $results.show();
      });
    }, 300);
  });

  $clear.on('click', function() {
    $display.val(''); $hidden.val(''); $(this).hide(); $results.hide();
    $('#swissqr-filter-form').submit();
  });

  $(document).on('click', function(e) {
    if (!$(e.target).closest('.swissqr-contact-wrap, #swissqr-contact-results').length) {
      $results.hide();
    }
  });
})(CRM.$);
</script>
{/literal}
{/crmScope}
