{crmScope extensionKey='ch.ipik.swissQRinvoice'}
<div class="crm-block crm-form-block">
  <h3>{ts}Swiss QR Invoice — Settings{/ts}</h3>
  {include file="CRM/common/formButtons.tpl" location="top"}

  <h4>{ts}Sender organisation{/ts}</h4>
  <div class="crm-section"><div class="label">{$form.swissqr_org_contact_id.label}</div><div class="content">{$form.swissqr_org_contact_id.html}</div></div>
  <div class="crm-section"><div class="label">{$form.swissqr_iban.label}</div><div class="content">{$form.swissqr_iban.html}<br><span class="description">Ex: CH88 0076 7000 S560 6787 8</span></div></div>

  <h4>PDF — Logo</h4>
  <div class="crm-section"><div class="label">{$form.swissqr_logo_path.label}</div><div class="content">{$form.swissqr_logo_path.html}<br><span class="description">Chemin absolu sur le serveur, ex: /home/clients/xxx/.../uploads/2024/03/logo.png</span></div></div>
  <div class="crm-section"><div class="label">{$form.swissqr_logo_offset_x.label}</div><div class="content">{$form.swissqr_logo_offset_x.html} mm <span class="description">{ts}Horizontal offset of the logo to the right{/ts}</span></div></div>
  <div class="crm-section"><div class="label">{$form.swissqr_logo_margin_bottom.label}</div><div class="content">{$form.swissqr_logo_margin_bottom.html} mm <span class="description">{ts}Additional space between the bottom of the logo and the address{/ts}</span></div></div>

  <h4>PDF — Signature</h4>
  <div class="crm-section"><div class="label">{$form.swissqr_signature_path.label}</div><div class="content">{$form.swissqr_signature_path.html}</div></div>
  <div class="crm-section"><div class="label">{$form.swissqr_signature_offset_y.label}</div><div class="content">{$form.swissqr_signature_offset_y.html} mm <span class="description">{ts}Positive value = moves down, negative = moves up.{/ts}</span></div></div>
  <div class="crm-section"><div class="label">{$form.swissqr_signatory_name.label}</div><div class="content">{$form.swissqr_signatory_name.html}</div></div>
  <div class="crm-section"><div class="label">{$form.swissqr_vat_note.label}</div><div class="content">{$form.swissqr_vat_note.html}</div></div>

  <h4>{ts}Numbering{/ts}</h4>
  <div class="crm-section"><div class="label">{$form.swissqr_invoice_number_format.label}</div><div class="content">{$form.swissqr_invoice_number_format.html}<br><span class="description">{ts}Variables: {ldelim}YEAR{rdelim} = year, {ldelim}SEQ:4{rdelim} = sequence of 4 digits{/ts}</span></div></div>
  <div class="crm-section"><div class="label">{$form.swissqr_qr_reference_template.label}</div><div class="content">{$form.swissqr_qr_reference_template.html}<br><span class="description">{ts}Variable: {ldelim}NUMBER{rdelim} = invoice number{/ts}</span></div></div>

  <h4>Email</h4>
  <div class="crm-section">
    <div class="label">Template d'envoi</div>
    <div class="content">
      <a href="{crmURL p='civicrm/admin/messageTemplates' q='reset=1'}" target="_blank" class="button"><span><i class="crm-i fa-edit"></i> {ts}Manage message templates{/ts}</span></a>
      <br><span class="description">{ts}Search for <strong>swissqrinvoice_send</strong> in the User-Driven Messages list to edit the invoice email template.{/ts}</span>
    </div>
  </div>

  <h4>{ts}Accounting{/ts}</h4>
  <div class="crm-section"><div class="label">{$form.swissqr_financial_account_id.label}</div><div class="content">{$form.swissqr_financial_account_id.html}<br><span class="description">{ts}Financial account used for QR invoice payments{/ts}</span></div></div>

  <h4>{ts}Access{/ts}</h4>
  {if $form.swissqr_allowed_roles}
  <div class="crm-section"><div class="label">{$form.swissqr_allowed_roles.label}</div><div class="content">{$form.swissqr_allowed_roles.html}</div></div>
  {/if}

  {include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
{/crmScope}
