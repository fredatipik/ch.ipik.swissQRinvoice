{crmScope extensionKey='com.ipik.swissQRinvoice'}
<div class="crm-block crm-form-block">
  <h3>{if $is_edit}Modifier la facture {$invoice.invoice_number}{else}Nouvelle facture{/if}</h3>

  <div class="crm-section">
    <div class="label">{$form.contact_id.label}</div>
    <div class="content">{$form.contact_id.html}</div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.organization_contact_id.label}</div>
    <div class="content">{$form.organization_contact_id.html}</div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.invoice_date.label}</div>
    <div class="content"><input type="date" name="invoice_date" id="invoice_date" value="{$form.invoice_date.value|default:''}" class="crm-form-text"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.due_date.label}</div>
    <div class="content"><input type="date" name="due_date" id="due_date" value="{$form.due_date.value|default:''}" class="crm-form-text"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.invoice_number.label}</div>
    <div class="content">{$form.invoice_number.html} <span class="description">Laisser vide pour numérotation automatique</span></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.reference.label}</div>
    <div class="content">{$form.reference.html}</div>
  </div>

  {* Lignes de facture *}
  <h4 style="margin-top:16px">Lignes de facture
    {if $services}
    &nbsp;
    <select id="swissqr-service-select" style="font-size:12px;font-weight:normal">
      <option value="">— Ajouter une prestation —</option>
      {foreach from=$services item=svc}
      <option value="{$svc.id}" data-name="{$svc.name|escape}" data-desc="{$svc.description|escape}" data-price="{$svc.unit_price}">{$svc.name} (CHF {$svc.unit_price|string_format:"%.2f"})</option>
      {/foreach}
    </select>
    {/if}
  </h4>

  <table style="width:100%;border-collapse:collapse;margin:8px 0">
    <thead>
      <tr style="background:#f0f0f0;font-size:12px">
        <th style="padding:5px 6px;text-align:left;width:25%">Article</th>
        <th style="padding:5px 6px;text-align:left">Description</th>
        <th style="padding:5px 6px;text-align:right;width:100px">Prix unit.</th>
        <th style="padding:5px 6px;text-align:right;width:70px">Qté</th>
        <th style="padding:5px 6px;text-align:right;width:100px">Total HT</th>
        <th style="width:30px"></th>
      </tr>
    </thead>
    <tbody id="swissqr-lines-tbody"></tbody>
    <tfoot>
      <tr id="swissqr-row-subtotal" style="display:none">
        <td colspan="4" style="text-align:right;padding:4px 6px;font-size:12px">Sous-total</td>
        <td id="swissqr-subtotal" style="text-align:right;padding:4px 6px;font-size:12px"></td>
        <td></td>
      </tr>
      <tr id="swissqr-row-discount" style="display:none">
        <td colspan="4" style="text-align:right;padding:4px 6px;font-size:12px;color:#c00">Rabais</td>
        <td id="swissqr-discount-display" style="text-align:right;padding:4px 6px;font-size:12px;color:#666"></td>
        <td></td>
      </tr>
      <tr>
        <td colspan="4" style="text-align:right;padding:6px;font-weight:bold">Total</td>
        <td id="swissqr-lines-total" style="text-align:right;padding:6px;font-weight:bold">CHF 0.00</td>
        <td></td>
      </tr>
    </tfoot>
  </table>
  <button type="button" id="swissqr-add-line" class="button" style="margin-bottom:12px">+ Ajouter une ligne</button>
  <input type="hidden" id="lines_json" name="lines_json" value="{if $invoice && $invoice.lines}{$invoice.lines|@json_encode|escape:'html'}{else}[]{/if}">

  {* Rabais *}
  <div class="crm-section">
    <div class="label">{$form.discount_type.label}</div>
    <div class="content">
      {$form.discount_type.html}
      <span id="swissqr-discount-val-wrap" style="display:none;margin-left:8px">
        {$form.discount_value.html}
      </span>
    </div>
  </div>

  <div class="crm-section">
    <div class="label">{$form.amount_paid.label}</div>
    <div class="content">{$form.amount_paid.html} &nbsp; Solde dû : <strong id="swissqr-amount-due">CHF 0.00</strong></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.notes.label}</div>
    <div class="content">{$form.notes.html}</div>
  </div>

  <div class="crm-submit-buttons">
    {include file="CRM/common/formButtons.tpl" location="bottom"}
    &nbsp;<a href="{$cancelURL}" class="button"><span>Annuler</span></a>
  </div>
</div>

<style>
#swissqr-lines-tbody td { padding:4px 6px; border-bottom:1px solid #eee; vertical-align:middle; }
#swissqr-lines-tbody input[type=text]   { width:98%; box-sizing:border-box; border:1px solid #ddd; padding:3px 5px; }
#swissqr-lines-tbody input[type=number] { border:1px solid #ddd; padding:3px 5px; text-align:right; }
</style>

<script>
{literal}
(function($) {
  var lines = [];

  function roundTo5(n) { return Math.round(n * 20) / 20; }
  function fmt(n) { return 'CHF ' + parseFloat(n).toFixed(2); }
  function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

  function calcDiscount(subtotal) {
    var type = $('[name=discount_type]').val();
    var val  = parseFloat($('[name=discount_value]').val()) || 0;
    if (type === 'amount')   return val;
    if (type === 'percent')  return Math.round(subtotal * val) / 100;
    return 0;
  }

  function render() {
    var tbody = document.getElementById('swissqr-lines-tbody');
    tbody.innerHTML = '';
    var subtotal = 0;
    lines.forEach(function(line, idx) {
      line.line_total = Math.round((parseFloat(line.unit_price)||0) * (parseFloat(line.quantity)||1) * 10) / 10;
      subtotal += line.line_total;
      var tr = document.createElement('tr');
      tr.innerHTML =
        '<td><input type="text" value="'+esc(line.article)+'" onblur="swissqr.upd('+idx+',\'article\',this.value)"></td>'+
        '<td><input type="text" value="'+esc(line.description)+'" onblur="swissqr.upd('+idx+',\'description\',this.value)"></td>'+
        '<td style="text-align:right"><input type="number" step="0.10" min="0" value="'+parseFloat(line.unit_price||0).toFixed(2)+'" style="width:90px" onchange="swissqr.upd('+idx+',\'unit_price\',this.value)"></td>'+
        '<td style="text-align:right"><input type="number" step="1" min="1" value="'+Math.round(parseFloat(line.quantity||1))+'" style="width:65px" onchange="swissqr.upd('+idx+',\'quantity\',this.value)"></td>'+
        '<td style="text-align:right;font-size:12px">CHF '+line.line_total.toFixed(2)+'</td>'+
        '<td style="text-align:center"><button type="button" onclick="swissqr.del('+idx+')" style="color:#c00;background:none;border:none;cursor:pointer;font-size:16px" title="Supprimer">✕</button></td>';
      tbody.appendChild(tr);
    });

    var discount = calcDiscount(subtotal);
    var total    = subtotal - discount;
    var paid     = parseFloat($('[name=amount_paid]').val()) || 0;

    // Sous-total (si rabais)
    if (discount > 0) {
      $('#swissqr-row-subtotal').show();
      $('#swissqr-row-discount').show();
      $('#swissqr-subtotal').text(fmt(subtotal));
      $('#swissqr-discount-display').text('- '+fmt(discount));
    } else {
      $('#swissqr-row-subtotal').hide();
      $('#swissqr-row-discount').hide();
    }

    $('#swissqr-lines-total').text(fmt(total));
    $('#swissqr-amount-due').text(fmt(total - paid));
    document.getElementById('lines_json').value = JSON.stringify(lines);
  }

  window.swissqr = {
    upd: function(idx, field, val) { lines[idx][field] = val; render(); },
    del: function(idx) { lines.splice(idx,1); render(); }
  };

  $(document).ready(function() {
    // Init lignes existantes
    var existing = document.getElementById('lines_json').value;
    try { lines = JSON.parse(existing) || []; } catch(e) { lines = []; }
    render();

    // Ajouter ligne vide
    $('#swissqr-add-line').on('click', function() {
      lines.push({article:'',description:'',unit_price:0,quantity:1,line_total:0});
      render();
    });

    // Ajouter depuis prestation
    $('#swissqr-service-select').on('change', function() {
      var opt = $(this).find(':selected');
      if (!opt.val()) return;
      lines.push({
        article:     opt.data('name'),
        description: opt.data('desc'),
        unit_price:  parseFloat(opt.data('price'))||0,
        quantity:    1,
        line_total:  parseFloat(opt.data('price'))||0
      });
      $(this).val('');
      render();
    });

    // Rabais — afficher/masquer le champ valeur
    $('[name=discount_type]').on('change', function() {
      if ($(this).val() === 'none') {
        $('#swissqr-discount-val-wrap').hide();
        $('[name=discount_value]').val('');
      } else {
        $('#swissqr-discount-val-wrap').show();
      }
      render();
    }).trigger('change');

    $('[name=discount_value]').on('input change', render);
    $('[name=amount_paid]').on('input change', render);
  });
})(CRM.$);
{/literal}
</script>
{/crmScope}
