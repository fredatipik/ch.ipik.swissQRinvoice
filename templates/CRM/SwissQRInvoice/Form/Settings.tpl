{crmScope extensionKey='com.ipik.swissQRinvoice'}
<div class="crm-block crm-form-block">
  <h3>Swiss QR Invoice — Paramètres</h3>
  {include file="CRM/common/formButtons.tpl" location="top"}

  <h4>Organisation expéditrice</h4>
  <div class="crm-section"><div class="label">{$form.swissqr_org_contact_id.label}</div><div class="content">{$form.swissqr_org_contact_id.html}</div></div>
  <div class="crm-section"><div class="label">{$form.swissqr_iban.label}</div><div class="content">{$form.swissqr_iban.html}<br><span class="description">Ex: CH88 0076 7000 S560 6787 8</span></div></div>

  <h4>PDF — Logo</h4>
  <div class="crm-section"><div class="label">{$form.swissqr_logo_path.label}</div><div class="content">{$form.swissqr_logo_path.html}<br><span class="description">Chemin absolu sur le serveur, ex: /home/clients/xxx/.../uploads/2024/03/logo.png</span></div></div>
  <div class="crm-section"><div class="label">{$form.swissqr_logo_offset_x.label}</div><div class="content">{$form.swissqr_logo_offset_x.html} mm <span class="description">Décalage horizontal du logo vers la droite</span></div></div>
  <div class="crm-section"><div class="label">{$form.swissqr_logo_margin_bottom.label}</div><div class="content">{$form.swissqr_logo_margin_bottom.html} mm <span class="description">Espace supplémentaire entre le bas du logo et l'adresse</span></div></div>

  <h4>PDF — Signature</h4>
  <div class="crm-section"><div class="label">{$form.swissqr_signature_path.label}</div><div class="content">{$form.swissqr_signature_path.html}</div></div>
  <div class="crm-section"><div class="label">{$form.swissqr_signature_offset_y.label}</div><div class="content">{$form.swissqr_signature_offset_y.html} mm <span class="description">Valeur positive = descend, négative = monte.</span></div></div>
  <div class="crm-section"><div class="label">{$form.swissqr_signatory_name.label}</div><div class="content">{$form.swissqr_signatory_name.html}</div></div>
  <div class="crm-section"><div class="label">{$form.swissqr_vat_note.label}</div><div class="content">{$form.swissqr_vat_note.html}</div></div>

  <h4>Numérotation</h4>
  <div class="crm-section"><div class="label">{$form.swissqr_invoice_number_format.label}</div><div class="content">{$form.swissqr_invoice_number_format.html}<br><span class="description">Variables : {ldelim}YEAR{rdelim} = année, {ldelim}SEQ:4{rdelim} = séquence sur 4 chiffres</span></div></div>
  <div class="crm-section"><div class="label">{$form.swissqr_qr_reference_template.label}</div><div class="content">{$form.swissqr_qr_reference_template.html}<br><span class="description">Variable : {ldelim}NUMBER{rdelim} = numéro de facture</span></div></div>

  <h4>Email</h4>
  <div class="crm-section">
    <div class="label">Template d'envoi</div>
    <div class="content">
      <a href="{crmURL p='civicrm/admin/messageTemplates' q='reset=1'}" target="_blank" class="button"><span><i class="crm-i fa-edit"></i> Gérer les templates de messages</span></a>
      <br><span class="description">Cherchez <strong>swissqrinvoice_send</strong> dans la liste des User-Driven Messages pour modifier le template d'email de facturation.</span>
    </div>
  </div>

  <h4>Comptabilité</h4>
  <div class="crm-section"><div class="label">{$form.swissqr_financial_account_id.label}</div><div class="content">{$form.swissqr_financial_account_id.html}<br><span class="description">Compte financier utilisé pour les paiements de factures QR</span></div></div>

  <h4>Accès</h4>
  {if $form.swissqr_allowed_roles}
  <div class="crm-section"><div class="label">{$form.swissqr_allowed_roles.label}</div><div class="content">{$form.swissqr_allowed_roles.html}</div></div>
  {/if}

  {include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
{/crmScope}
