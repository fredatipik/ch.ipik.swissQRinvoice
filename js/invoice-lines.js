/**
 * Swiss QR Invoice — gestion dynamique des lignes de facture.
 * Vanilla JS, pas de dépendance externe.
 */
(function($) {
  'use strict';

  var lines = [];

  function renderLines() {
    var $tbody = $('#swissqr-lines-tbody');
    $tbody.empty();
    lines.forEach(function(line, idx) {
      var total = (parseFloat(line.unit_price)||0) * (parseFloat(line.quantity)||1);
      line.line_total = Math.round(total * 100) / 100;
      $tbody.append(
        '<tr data-idx="'+idx+'">' +
        '<td><input type="text" class="line-article" value="'+escHtml(line.article||'')+'" style="width:98%"></td>' +
        '<td><input type="text" class="line-desc" value="'+escHtml(line.description||'')+'" style="width:98%"></td>' +
        '<td><input type="number" class="line-price" value="'+(line.unit_price||0)+'" step="0.01" style="width:80px"></td>' +
        '<td><input type="number" class="line-qty" value="'+(line.quantity||1)+'" step="0.001" style="width:60px"></td>' +
        '<td class="right">CHF '+line.line_total.toFixed(2)+'</td>' +
        '<td><button type="button" class="button-small remove-line" title="Supprimer"><i class="crm-i fa-times"></i></button></td>' +
        '</tr>'
      );
    });
    updateTotal();
    syncHidden();
  }

  function updateTotal() {
    var total = lines.reduce(function(s,l){ return s + (l.line_total||0); }, 0);
    $('#swissqr-lines-total').text('CHF ' + total.toFixed(2));
    var paid   = parseFloat($('#amount_paid').val()) || 0;
    var due    = total - paid;
    $('#swissqr-amount-due').text('CHF ' + due.toFixed(2));
  }

  function syncHidden() {
    $('#lines_json').val(JSON.stringify(lines));
  }

  function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  $(document).ready(function() {
    // Init depuis données existantes (édition)
    var existing = $('#lines_json').val();
    try { lines = JSON.parse(existing) || []; } catch(e) { lines = []; }
    renderLines();

    // Ajouter ligne
    $('#swissqr-add-line').on('click', function() {
      lines.push({ article:'', description:'', unit_price:0, quantity:1, line_total:0 });
      renderLines();
    });

    // Editer champs inline
    $('#swissqr-lines-tbody').on('input', '.line-article', function() {
      var idx = $(this).closest('tr').data('idx');
      lines[idx].article = $(this).val();
      syncHidden();
    }).on('input', '.line-desc', function() {
      var idx = $(this).closest('tr').data('idx');
      lines[idx].description = $(this).val();
      syncHidden();
    }).on('input change', '.line-price', function() {
      var idx = $(this).closest('tr').data('idx');
      lines[idx].unit_price = parseFloat($(this).val()) || 0;
      renderLines();
    }).on('input change', '.line-qty', function() {
      var idx = $(this).closest('tr').data('idx');
      lines[idx].quantity = parseFloat($(this).val()) || 1;
      renderLines();
    }).on('click', '.remove-line', function() {
      var idx = $(this).closest('tr').data('idx');
      lines.splice(idx, 1);
      renderLines();
    });

    // Mise à jour solde quand payé change
    $('#amount_paid').on('input change', function() { updateTotal(); });
  });

})(CRM.$);
