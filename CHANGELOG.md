# Changelog

## 0.31 — 2026-09-23

### Fixed
- The bundled QR library was only loaded when producing a PDF, so recording a
  payment and bulk creation failed with "Class RfCreditorReferenceGenerator not
  found". Loading is now shared by everything that needs it.
- Saving an edited invoice could create a second invoice carrying the same
  number, when the id was not preserved in the URL. The form now carries the id
  itself, and a number already in use is never reissued.

### Added
- "Save and send" button on the invoice form.

## 0.30 — 2026-09-22

### Changed
- Invoices are backed by native CiviCRM contributions: a *Pending* contribution
  is created when an invoice is issued (sent or bulk-created), payments go
  through the Payment API, and the invoice follows the contribution's status.
- Issued invoices can no longer be edited (cancel or duplicate instead).
- Cancelling an invoice cancels its pending contribution.

### Added
- Structured QR references (ISO 11649 creditor reference, or QR reference with a
  QR-IBAN), stored in the contribution's `invoice_id` for bank reconciliation.
- *Create QR invoices* action on contact searches, processed through a queue.
- The QR invoice is attached to CiviCRM's native *Email invoice* action, and
  replaces CiviCRM's own invoice by default.
- Donation slips: CiviMail tokens `{swissqr.donation_slip_url}` and
  `{swissqr.donation_reference}`, and a public checksum-protected slip page.
- Single-page PDF: payment part at the bottom of page 1 when it fits.
- `docs/CIVIBANKING.md`.
- Backfill helper for invoices sent before 0.30.

### Fixed
- QR-bill specification v2.3: creditor and debtor addresses are now structured
  (combined addresses have been invalid since 21 November 2025); country taken
  from the contact's address instead of always CH.
- The "QR reference" text was never included in the QR code; it is now sent as
  additional information.
- PDF generation failed with a syntax error after the i18n change.
- Translations were never loaded: the compiled catalogue is now at
  `l10n/fr_FR/LC_MESSAGES/swissQRinvoice.mo`; all strings translated.
- Cancel buttons of the Send and Mark as paid forms caused a blank page.
- Duplicate field in the settings form; unused root `xml/Menu.xml` removed.
- `CRM_Core_Error::fatal()` replaced by exceptions.

## 0.29 — 2026-09-16

First public release.
