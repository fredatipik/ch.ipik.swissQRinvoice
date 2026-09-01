{crmScope extensionKey='com.ipik.swissQRinvoice'}
<div class="crm-block crm-form-block">
  <h3>Confirmer le paiement — Facture {$invoice.invoice_number}</h3>
  <div class="crm-section">
    <div class="label">Contact</div>
    <div class="content"><strong>{$invoice.contact_name}</strong></div>
  </div>
  <div class="crm-section">
    <div class="label">Total facture</div>
    <div class="content">CHF {$invoice.total|string_format:"%.2f"}</div>
  </div>
  <div class="crm-section">
    <div class="label">Solde dû</div>
    <div class="content"><strong>CHF {$invoice.amount_due|string_format:"%.2f"}</strong></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.amount.label}</div>
    <div class="content">{$form.amount.html}</div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.paid_date.label}</div>
    <div class="content"><input type="date" name="paid_date" value="{$form.paid_date.value|default:''}" class="crm-form-text"></div>
  </div>
  <p class="description">Une contribution CiviCRM sera créée automatiquement.</p>
  <div class="crm-submit-buttons">
    {include file="CRM/common/formButtons.tpl" location="bottom"}
  </div>
</div>
{/crmScope}
