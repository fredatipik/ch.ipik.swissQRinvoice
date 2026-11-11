{crmScope extensionKey='ch.ipik.swissQRinvoice'}
<div class="crm-block crm-form-block">
  <h3>Envoyer la facture {$invoice.invoice_number} — {$invoice.contact_name|escape}</h3>

  <div class="crm-section">
    <div class="label">{$form.to_contact_id.label}</div>
    <div class="content">{$form.to_contact_id.html}<br><span class="description">{ts}The invoice goes to this contact's primary email address.{/ts}</span></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.cc_contact_ids.label}</div>
    <div class="content">{$form.cc_contact_ids.html}</div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.bcc_contact_ids.label}</div>
    <div class="content">{$form.bcc_contact_ids.html}<br><span class="description">{ts}Contacts to copy for this invoice. Prefilled from the settings; a contact can be created from the field itself.{/ts}</span></div>
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
    &nbsp;<a href="{$cancelURL}" class="button"><span>{ts}Cancel{/ts}</span></a>
  </div>
</div>
{/crmScope}
