{crmScope extensionKey='ch.ipik.swissQRinvoice'}
<div class="crm-block crm-form-block">
  <h3>Envoyer la facture {$invoice.invoice_number} — {$invoice.contact_name|escape}</h3>

  <div class="crm-section">
    <div class="label">{$form.to_email.label}</div>
    <div class="content">{$form.to_email.html}</div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.subject.label}</div>
    <div class="content">{$form.subject.html}</div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.body_html.label}</div>
    <div class="content">
      {$form.body_html.html}
      <span class="description">
        <a href="{$editTemplateUrl}" target="_blank"><i class="crm-i fa-external-link"></i> Modifier le template de base</a>
      </span>
    </div>
  </div>
  <div class="crm-section">
    <div class="label"></div>
    <div class="content">
      <span class="description"><i class="crm-i fa-paperclip"></i> Le PDF de la facture sera joint automatiquement.</span>
    </div>
  </div>
  <div class="crm-submit-buttons">
    {include file="CRM/common/formButtons.tpl" location="bottom"}
  </div>
</div>
{/crmScope}
