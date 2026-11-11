# Changelog

## 0.41 — 2026-11-10

### Changed
- The recipient of a single invoice is now a contact too, preselected from the
  invoice. Its primary address is read at send time; a contact without one is
  reported instead of sending nowhere.

## 0.40 — 2026-11-10

### Changed
- Copies are now CiviCRM contacts everywhere, in the settings and on the send
  screen, with a contact creatable straight from the field. The free-text
  address fields are gone: an address with no contact is added by creating one.

## 0.39 — 2026-11-10

### Added
- Cc and Bcc fields on the single-invoice send screen, prefilled from the
  standing copies and editable for that invoice only — clearing a field sends
  no copy at all. Invalid addresses are reported on the form instead of being
  dropped. Bulk sending keeps the standing copies.

## 0.38 — 2026-11-10

### Added
- Cc and Bcc copies can now be CiviCRM contacts, picked in the settings. Their
  addresses are read at send time, so they follow the contact. Contacts that are
  deleted, marked "do not email", on hold or without a primary address are
  skipped and logged. The free-text fields remain for addresses that belong to
  no contact; both lists are merged and de-duplicated.

## 0.37 — 2026-11-10

### Added
- Standing Cc and Bcc addresses in the settings, applied to every invoice email,
  sent one at a time or in bulk. Invalid addresses are dropped and logged rather
  than failing the send. Donation slips sent through CiviMail are unaffected.

### Fixed
- One French string left hardcoded in the settings page is now translatable.

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
