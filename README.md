# ch.ipik.swissQRinvoice — Swiss QR Invoice for CiviCRM

A CiviCRM extension for generating PDF invoices with a **Swiss QR-bill** (Swiss Payment Standard / ISO 20022) payment slip, linked to CiviCRM contacts and contributions.

## Status

Alpha. In production on one site since September 2026, actively maintained.
Feedback and issues are very welcome.

## Language

The user interface is currently **French only**. All translatable strings are
scoped to the `ch.ipik.swissQRinvoice` domain, so translation files can be
dropped in without touching the code — an English translation is planned, and
contributions for other languages are welcome. Documentation is in English.

## Features

- **Invoice management** — create, edit, duplicate and cancel invoices linked to CiviCRM contacts
- **Dynamic line items** — free-form lines, or picked from a reusable service catalog
- **Discounts** — fixed amount (CHF) or percentage
- **PDF generation** — configurable layout with logo and signature, Swiss QR-bill on page 2
- **Email sending** — HTML template stored in CiviCRM Message Templates, with CiviCRM tokens (`{contact.first_name}`, `{contact.email_greeting_display}`) and invoice tokens (`{invoice_number}`, `{amount_due}`)
- **Mark as paid** — creates a linked CiviCRM contribution automatically
- **Contact tab** — invoice history visible from the contact record
- **Invoice list** — filter by contact (autocomplete), status and date range; sortable columns
- **Auto-install** — routes, navigation menu, financial type and email template are created on activation

## Requirements

- CiviCRM 6.0 or later (developed and tested on 6.15)
- WordPress (tested on shared hosting at Infomaniak); not yet tested on Drupal or Backdrop
- PHP 8.1+

Only the combination above has been tested. Reports from other setups are welcome.

## Dependencies

Installed via Composer, and bundled in the release archives:

- [`sprain/swiss-qr-bill`](https://github.com/sprain/php-swiss-qr-bill) — Swiss QR-bill generation
- [`tecnickcom/tcpdf`](https://github.com/tecnickcom/TCPDF) — PDF rendering (also shipped with CiviCRM)

## Installation

1. Download the latest release archive and extract it into your CiviCRM extensions directory
2. **Administer → System Settings → Extensions** → enable "Swiss QR Invoice"
3. **Facturation → Paramètres** → set the sender organisation, IBAN, logo and signature

If you install from a Git clone rather than a release archive, run
`composer install --no-dev` in the extension directory first. Full details in
[INSTALL.md](INSTALL.md).

## Configuration

| Setting | Description |
|---|---|
| Organisation contact | CiviCRM contact used as sender (name, address) |
| IBAN | Swiss IBAN used for the QR-bill |
| Logo path | Absolute server path to the logo image |
| Logo offset X | Horizontal logo offset, in mm |
| Signature path | Absolute server path to the signature image |
| Signature offset Y | Vertical signature offset, in mm (positive moves it down) |
| Invoice number format | e.g. `{YEAR}-{SEQ:4}` → `2026-0042` |
| QR reference template | e.g. `Facture N° {NUMBER}` |
| VAT note | Shown in italics below the totals |
| Financial account | Account used for contributions created by "mark as paid" |

## Email template

The template is stored in **Administer → Communications → Message Templates**,
under the title `swissqrinvoice_send` in the *User-Driven Messages* tab. Edit it
there to change the wording or the HTML layout.

| Token | Value |
|---|---|
| `{contact_name}` | Recipient display name |
| `{invoice_number}` | Invoice number |
| `{invoice_date}` | Invoice date (dd.mm.yyyy) |
| `{amount_due}` | Amount due, in CHF |
| `{organization_name}` | Signatory name, from the settings |
| `{contact.first_name}` | Recipient first name |
| `{contact.display_name}` | Recipient full name |
| `{contact.email_greeting_display}` | Greeting line, e.g. "Dear John" |

Greeting tokens depend on the contact having a greeting configured in CiviCRM;
when it is empty, the display name is used instead.

## Known limitations

- The QR slip always occupies its own page; a single-page layout is not available
- Batch generation for a group of contacts is not implemented yet
- The interface is French only (see *Language* above)

## License

AGPL-3.0 — see [LICENSE](LICENSE)

## Author

Frédéric Hiltbrand / [IPIK](https://ipik.ch) — fred@ipik.ch
