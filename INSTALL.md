# Installation — ch.ipik.swissQRinvoice

## 1. Install the files

Place the `ch.ipik.swissQRinvoice` folder in your CiviCRM extensions directory.
On WordPress this is usually `wp-content/uploads/civicrm/ext/`.

## 2. Dependencies

**From a release archive**: `vendor/` is included, nothing to do.

**From a Git checkout**:

```bash
cd /path/to/ext/ch.ipik.swissQRinvoice
composer install --no-dev
```

The extension also tries to run Composer when it is enabled, if `composer` is
in the server's `$PATH`. Without `vendor/`, invoices render without their QR
payment part and an error is written to the CiviCRM log.

## 3. Enable

**Administer → System Settings → Extensions** → "Swiss QR Invoice" → Install.

## 4. Configure

**Invoicing → Settings**:

- the **sender organisation**: a CiviCRM contact with a full primary address.
  Since v2.3 of the QR-bill specification (21 November 2025) addresses must be
  structured; street and building number are taken from CiviCRM's parsed
  address fields when available, otherwise split automatically
  ("Rue du Lac 12", "12, rue du Lac");
- the **IBAN** — a QR-IBAN (institution identifier 30000–31999) switches to QR
  references automatically;
- logo and signature: **absolute** server paths, e.g.
  `/home/clients/xxxx/sites/example.org/wp-content/uploads/logo.png`
  (a wrong path is ignored silently);
- numbering format, VAT note, financial account.

## 5. Sending invoices

**One at a time** — from the invoice list, envelope icon. Uses the message
template `swissqrinvoice_send`, created on install.

**Many at once**:

1. In any contact search, select the contacts, then *Actions → Create QR invoices*.
   One issued invoice (with a pending contribution) is created per contact,
   through a queue with a progress bar.
2. *Contributions → Find contributions*: financial type *Facture QR*, status
   *Pending*. Select them, then *Actions → Email invoice*. CiviCRM's own invoice
   is replaced by the QR invoice (setting *Replace CiviCRM invoice*).
   With Invoice Helper installed, this step is queued as well.

CiviCRM's *Email invoice* action requires invoicing to be enabled under
*Administer → CiviContribute → CiviContribute Component Settings*.

## 6. Donation appeals

In a CiviMail mailing, insert `{swissqr.donation_slip_url}` as a link, e.g.
"Download your donation slip". Each recipient gets a personal, non-expiring link
to a one-page QR slip with no amount and no address. The reference on the slip
encodes the donor and the mailing; see [docs/CIVIBANKING.md](docs/CIVIBANKING.md)
to credit donations automatically.

## 7. Upgrading from v0.29

Disable then re-enable the extension: new routes, the payment index and the new
setting are installed. Existing data is kept.

Invoices sent before v0.30 have no pending contribution. To create them — so
that bank imports can match those invoices — run a dry run first, then apply:

```bash
cv ev 'print_r(CRM_SwissQRInvoice_Contribution::backfill());'
cv ev 'print_r(CRM_SwissQRInvoice_Contribution::backfill(FALSE));'
```

Their QR slips still carry no structured reference: only invoices issued from
v0.30 onwards can be matched automatically.

## Technical notes

- The payment part always occupies the bottom 105 mm of a page (from 193 mm).
- Payments are recorded with CiviCRM's Payment API; invoice status follows the
  contribution through `hook_civicrm_post`, with a catch-up query each time the
  invoice list is opened.
- Permissions: `access swissqr invoices`, `edit swissqr invoices`.

## Database tables

| Table | Contents |
|---|---|
| `civicrm_swissqr_invoice` | one row per invoice, linked to its contribution by `payment_contribution_id` |
| `civicrm_swissqr_invoice_line` | line items |
| `civicrm_swissqr_service` | service catalogue |

Tables are kept when the extension is disabled, and dropped on uninstall.

## Translations

CiviCRM loads compiled catalogues from:

```
l10n/<locale>/LC_MESSAGES/swissQRinvoice.mo
```

The file is named after the extension's main file (`swissQRinvoice`), not its
key. To add a language, copy `l10n/swissQRinvoice.pot` to
`l10n/de_DE/LC_MESSAGES/swissQRinvoice.po`, translate it, then compile:

```bash
msgfmt -o l10n/de_DE/LC_MESSAGES/swissQRinvoice.mo l10n/de_DE/LC_MESSAGES/swissQRinvoice.po
```
