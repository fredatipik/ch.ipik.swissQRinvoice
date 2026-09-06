# ch.ipik.swissQRinvoice — Swiss QR Invoice for CiviCRM

A CiviCRM extension for generating PDF invoices with a **Swiss QR-bill** (Swiss Payment Standard / ISO 20022) slip, linked to CiviCRM contacts and contributions.

## Features

- **Invoice management** — create, edit, duplicate, cancel invoices linked to CiviCRM contacts
- **Dynamic line items** — free-form lines or from a reusable service catalog (prestations)
- **Discounts** — fixed amount (CHF) or percentage
- **PDF generation** — professional layout with logo, signature, and Swiss QR-bill on page 2
- **Email sending** — HTML template stored in CiviCRM Message Templates, supports CiviCRM tokens (`{contact.first_name}`, `{contact.email_greeting_display}`, etc.) and custom tokens (`{invoice_number}`, `{amount_due}`, `{organization_name}`, etc.)
- **Mark as paid** — creates a linked CiviCRM contribution automatically
- **Contact tab** — invoices visible from the contact view
- **Invoice list** — filterable by contact (autocomplete), status, and date range; sortable columns
- **Cancel invoices** — with confirmation, protected against cancelling paid invoices
- **Auto-install** — all routes, navigation menu, financial type, and email template created on activation

## Requirements

- CiviCRM 5.x or later
- WordPress (tested on WP + CiviCRM 6.x on Infomaniak shared hosting)
- PHP 8.1+
- Composer (for dependencies, auto-installed on extension enable)

## Dependencies (via Composer)

- [`sprain/swiss-qr-bill`](https://github.com/sprain/php-swiss-qr-bill) — Swiss QR-bill generation
- [`tecnickcom/tcpdf`](https://github.com/tecnickcom/TCPDF) — PDF rendering

## Installation

1. Download the latest release tar and extract into your CiviCRM extensions directory
2. In CiviCRM: **Administer → System Settings → Extensions** → Enable "Swiss QR Invoice"
3. Composer dependencies are installed automatically on enable (requires `composer` in `$PATH`)
4. Go to **Facturation → Paramètres** to configure your organisation, IBAN, logo, and signature

## Configuration

| Setting | Description |
|---|---|
| Organisation contact | CiviCRM contact used as sender (name, address, phone) |
| IBAN | Swiss IBAN for the QR-bill (CH format) |
| Logo path | Absolute server path to your logo image |
| Logo offset X | Horizontal logo offset in mm |
| Signature path | Absolute server path to your signature image |
| Signature offset Y | Vertical signature offset in mm (positive = down) |
| Invoice number format | e.g. `{YEAR}-{SEQ:4}` → `2025-0042` |
| QR reference template | e.g. `Facture N° {NUMBER}` |
| VAT note | Displayed in italic below the totals |

## Email Template

The email template is stored in **CiviCRM → Message Templates** under the title `swissqrinvoice_send`. Edit it there to customise the HTML layout.

**Available tokens:**

| Token | Value |
|---|---|
| `{contact_name}` | Contact display name |
| `{invoice_number}` | Invoice number |
| `{invoice_date}` | Invoice date (dd.mm.yyyy) |
| `{amount_due}` | Amount due (CHF) |
| `{organization_name}` | Signatory name from settings |
| `{contact.first_name}` | CiviCRM contact first name |
| `{contact.email_greeting_display}` | e.g. "Dear John" |
| `{contact.display_name}` | Full display name |

## License

AGPL-3.0 — see [LICENSE](LICENSE)

## Author

Frédéric Hiltbrand / [IPIK](https://ipik.ch) — fred@ipik.ch
