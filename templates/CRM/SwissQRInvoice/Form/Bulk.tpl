{crmScope extensionKey='ch.ipik.swissQRinvoice'}
<div class="crm-block crm-form-block crm-swissqr-bulk-form">
  <div class="messages status no-popup">
    <i class="crm-i fa-info-circle" aria-hidden="true"></i>
    {ts 1=$contactCount}One invoice will be created and issued for each of the %1 selected contacts.{/ts}
    {ts}Each invoice gets a pending contribution, so payments can be reconciled from the bank. Tick the box below to email them right away, or send them later from the invoice list.{/ts}
  </div>

  <div class="crm-section">
    <div class="label">{$form.service_id.label}</div>
    <div class="content">{$form.service_id.html}
      <div class="description">{ts}Pick a service from the catalogue, or leave on free entry and fill in the fields below. Fields you fill in override the service.{/ts}</div>
    </div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.article.label}</div>
    <div class="content">{$form.article.html}</div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.description.label}</div>
    <div class="content">{$form.description.html}</div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.unit_price.label}</div>
    <div class="content">{$form.unit_price.html}</div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.invoice_date.label}</div>
    <div class="content">{$form.invoice_date.html}</div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.due_date.label}</div>
    <div class="content">{$form.due_date.html}</div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.notes.label}</div>
    <div class="content">{$form.notes.html}</div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.send_now.label}</div>
    <div class="content">{$form.send_now.html}
      <div class="description">{ts}Uses the swissqrinvoice_send message template, with the QR invoice attached. A contact without an email address is skipped and logged.{/ts}</div>
    </div>
  </div>

  <div class="crm-submit-buttons">{include file="CRM/common/formButtons.tpl" location="bottom"}</div>
</div>
{/crmScope}
