{crmScope extensionKey='ch.ipik.swissQRinvoice'}
<div class="crm-content-block crm-block">
  <h3>{ts}Services{/ts}</h3>

  <div style="background:#f9f9f9;border:1px solid #ddd;padding:12px;margin-bottom:16px;border-radius:4px">
    <h4 style="margin-top:0">{if $editService}Modifier la prestation{else}Nouvelle prestation{/if}</h4>
    <form method="post" action="{crmURL p='civicrm/swissqr/services'}">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="sid" value="{if $editService}{$editService.id}{else}0{/if}">
      <table>
        <tr>
          <td style="padding:4px 8px"><label>Nom *</label></td>
          <td style="padding:4px"><input type="text" name="name" value="{if $editService}{$editService.name|escape}{/if}" required style="width:250px"></td>
        </tr>
        <tr>
          <td style="padding:4px 8px"><label>Description</label></td>
          <td style="padding:4px"><input type="text" name="description" value="{if $editService}{$editService.description|escape}{/if}" style="width:350px"></td>
        </tr>
        <tr>
          <td style="padding:4px 8px"><label>Prix (CHF)</label></td>
          <td style="padding:4px"><input type="number" name="unit_price" step="0.01" min="0" value="{if $editService}{$editService.unit_price|string_format:'%.2f'}{else}0.00{/if}" style="width:100px"></td>
        </tr>
        <tr>
          <td></td>
          <td style="padding:8px 4px">
            <input type="submit" class="button" value="{if $editService}Enregistrer{else}Ajouter{/if}">
            {if $editService}&nbsp;<a href="{crmURL p='civicrm/swissqr/services' q='reset=1'}" class="button">Annuler</a>{/if}
          </td>
        </tr>
      </table>
    </form>
  </div>

  {if $services}
  <table class="crm-datatable display" style="width:100%">
    <thead>
      <tr><th>Nom</th><th>Description</th><th style="text-align:right">Prix (CHF)</th><th class="crm-no-sort">Actions</th></tr>
    </thead>
    <tbody>
    {foreach from=$services item=svc}
    <tr>
      <td>{$svc.name|escape}</td>
      <td>{$svc.description|escape}</td>
      <td style="text-align:right">{$svc.unit_price|string_format:"%.2f"}</td>
      <td style="white-space:nowrap">
        <a href="{crmURL p='civicrm/swissqr/services' q="action=edit&sid=`$svc.id`"}" title="Modifier"><i class="crm-i fa-pencil"></i></a>
        &nbsp;
        {* Delete via POST pour éviter les problèmes de reset=1 dans l'URL *}
        <form method="post" action="{crmURL p='civicrm/swissqr/services'}" style="display:inline"
              onsubmit="return confirm('Supprimer cette prestation ?')">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="sid" value="{$svc.id}">
          <button type="submit" style="background:none;border:none;cursor:pointer;padding:0" title="Supprimer">
            <i class="crm-i fa-trash" style="color:#c00"></i>
          </button>
        </form>
      </td>
    </tr>
    {/foreach}
    </tbody>
  </table>
  {else}
  <div class="messages status no-popup">{ts}No service defined.{/ts}</div>
  {/if}
</div>
{/crmScope}
