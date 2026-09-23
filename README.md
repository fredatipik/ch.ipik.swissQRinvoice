# ch.ipik.swissQRinvoice — Swiss QR Invoice for CiviCRM

A CiviCRM extension for issuing PDF invoices and donation slips with a **Swiss QR-bill** payment part, built on CiviCRM's own contributions so that payments, bank reconciliation and accounting exports use the standard tools.

## Status

Alpha, actively maintained. Feedback and issues are very welcome.

## Language

The interface is available in **English and French**. All strings are scoped to the `ch.ipik.swissQRinvoice` domain; to add a language, see *Translations* in [INSTALL.md](INSTALL.md).

## How it works

Every invoice is backed by a native CiviCRM contribution:

1. **Draft** — an invoice can be edited freely.
2. **Issued** — when it is sent (or created in bulk), a *Pending* contribution is created for its total. The QR slip carries a structured reference, also stored in the contribution's `invoice_id`.
3. **Paid** — payments are recorded on that contribution, by hand or by importing the bank's camt file with [CiviBanking](docs/CIVIBANKING.md). CiviCRM sets the contribution to *Partially paid* or *Completed*, and the invoice follows.

Because the contribution is a normal one, it appears in contribution searches, in the native *Email invoice* action and in accounting batches.

## Features

- **Invoices** — create, edit (while in draft), duplicate and cancel; free lines or a reusable service catalogue; fixed or percentage discounts
- **Swiss QR-bill, specification v2.3** — structured addresses, structured references (ISO 11649 creditor reference, or QR reference with a QR-IBAN), additional information text
- **PDF** — logo and signature, payment part on page 2, or at the bottom of page 1 when the invoice is short enough
- **Email** — per-invoice sending with a CiviCRM message template, or CiviCRM's native *Email invoice* action on contributions, which then attaches the QR invoice
- **Bulk invoicing** — *Actions → Create QR invoices* on any contact search, processed through a CiviCRM queue
- **Donation appeals** — a CiviMail token inserts a personal link to a one-page donation slip, with no amount and no personal data; the reference identifies the donor and the mailing
- **Bank reconciliation** — references designed to be matched by CiviBanking from camt.053 / camt.054 files
- **Contact tab**, filterable and sortable invoice list, automatic installation of routes, menu, financial type and email template

## Requirements

- CiviCRM 6.0 or later (developed and tested on 6.15)
- WordPress (tested on Infomaniak shared hosting); Drupal and Backdrop not yet tested
- PHP 8.1+
- Optional: [CiviBanking](https://civicrm.org/extensions/civibanking) for bank reconciliation, [Invoice Helper](https://lab.civicrm.org/extensions/invoicehelper) for queued sending of native invoices

## Dependencies

Bundled in release archives (`vendor/`), installed with Composer from a Git checkout:

- [`sprain/swiss-qr-bill`](https://github.com/sprain/php-swiss-qr-bill) — QR-bill generation and validation
- TCPDF, shipped with CiviCRM

## Installation

1. Extract the release archive into your CiviCRM extensions directory
2. **Administer → System Settings → Extensions** → enable "Swiss QR Invoice"
3. **Invoicing → Settings** → sender organisation (with a full postal address), IBAN, logo, signature

Details, upgrade notes and the Git-checkout procedure are in [INSTALL.md](INSTALL.md).

## Configuration

| Setting | Description |
|---|---|
| Sender organisation | CiviCRM contact used as creditor; needs a name, postcode and city |
| IBAN | Regular IBAN (creditor reference) or QR-IBAN (QR reference) — detected automatically |
| Logo / signature | Absolute server paths, with position offsets in mm |
| Numbering format | e.g. `{YEAR}-{SEQ:4}` → `2026-0042` |
| QR reference template | Text shown to the payer as additional information, e.g. `Facture N° {NUMBER}` |
| Single page PDF | Payment part at the bottom of page 1 when it fits |
| Financial account | Account used by the *Facture QR* financial type |
| Replace CiviCRM invoice | In the native *Email invoice* action, attach the QR invoice instead of CiviCRM's own |

## Email tokens

Per-invoice emails use the message template `swissqrinvoice_send` (*Administer → Communications → Message Templates*, *User-Driven Messages* tab):

| Token | Value |
|---|---|
| `{contact.email_greeting_display}` | Greeting line, e.g. "Cher Frédéric" |
| `{contact.first_name}`, `{contact.display_name}` | Recipient names |
| `{invoice_number}`, `{invoice_date}`, `{amount_due}` | Invoice data |
| `{organization_name}` | Signatory name from the settings |

For donation appeals in CiviMail:

| Token | Value |
|---|---|
| `{swissqr.donation_slip_url}` | Personal link to the donor's QR donation slip (PDF) |
| `{swissqr.donation_reference}` | The reference printed on that slip |

## Known limitations

- Bank reconciliation requires CiviBanking and a per-bank importer configuration — see [docs/CIVIBANKING.md](docs/CIVIBANKING.md)
- Issued invoices cannot be edited: cancel and duplicate instead, so that the books stay consistent
- Bulk invoicing creates one line per invoice

## License

AGPL-3.0 — see [LICENSE](LICENSE)

## Author

Frédéric Hiltbrand / [IPIK](https://ipik.ch) — fred@ipik.ch
