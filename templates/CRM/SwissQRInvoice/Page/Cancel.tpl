{crmScope extensionKey='ch.ipik.swissQRinvoice'}
<div class="crm-block crm-content-block">
  <div class="messages warning">
    <i class="crm-i fa-exclamation-triangle"></i>
    <strong>Confirmer l'annulation</strong><br><br>
    Voulez-vous vraiment annuler la facture <strong>{$invoice.invoice_number}</strong>
    pour <strong>{$invoice.contact_name|escape}</strong>&nbsp;?<br><br>
    <span class="description">Cette action est irréversible. La facture passera au statut <em>Annulée</em> et ne pourra plus être envoyée ni marquée comme payée.</span>
  </div>
  <div style="margin-top:16px">
    <a href="{$cancelUrl}" class="button" style="background:#c62828;color:#fff;border-color:#b71c1c">
      <span><i class="crm-i fa-ban"></i> Oui, annuler la facture</span>
    </a>
    &nbsp;
    <a href="{$backUrl}" class="button"><span>Retour</span></a>
  </div>
</div>
{/crmScope}
