# Installation — ch.ipik.swissQRinvoice

## 1. Install the extension files

Place the `ch.ipik.swissQRinvoice` folder in your CiviCRM extensions directory:

```
/path/to/civicrm/ext/ch.ipik.swissQRinvoice/
```

On WordPress this is usually `wp-content/uploads/civicrm/ext/`.

## 2. Dependencies

The QR-bill generation relies on the `sprain/swiss-qr-bill` PHP library.

**If you installed from a release archive**, the `vendor/` directory is already
included and there is nothing to do — skip to step 3.

**If you cloned the Git repository**, install the dependencies yourself:

```bash
cd /path/to/civicrm/ext/ch.ipik.swissQRinvoice
composer install --no-dev
```

The extension also attempts to run `composer install` automatically when it is
enabled, provided `composer` is available in the server's `$PATH`. This is a
convenience, not a guarantee: on restricted shared hosting it may silently do
nothing, in which case the PDF will render without its QR slip and an error is
written to the CiviCRM log.

## 3. Enable the extension

**Administer → System Settings → Extensions** → find "Swiss QR Invoice" → Install.

## 4. Configure

Go to **Facturation → Paramètres** in the CiviCRM menu and set:

- the organisation contact used as the sender (name, address, postcode, city);
- the IBAN, in Swiss format (e.g. `CH88 0076 7000 S560 6787 8`);
- the absolute server paths to your logo and signature images;
- the invoice numbering format (e.g. `{YEAR}-{SEQ:4}`);
- the financial account used when an invoice is marked as paid.

The administration interface is currently in French — see the note on
translations in the README.

## 5. Logo and signature

Upload the image files to the server (SFTP, or your host's file manager), then
enter their **absolute** path in the settings, for example:

```
/home/clients/xxxx/sites/example.org/wp-content/uploads/logo.png
```

A relative or truncated path will be silently ignored and the image will simply
not appear on the PDF. Both images have an offset setting (horizontal for the
logo, vertical for the signature) to fine-tune their placement in millimetres.

## 6. Email template

An HTML message template named `swissqrinvoice_send` is created automatically on
install. Edit it under **Administer → Communications → Message Templates**, in
the *User-Driven Messages* tab. The available tokens are listed in the README.

## Technical notes

- **TCPDF** ships with CiviCRM; no separate installation is required.
- **sprain/swiss-qr-bill** produces QR data compliant with the Swiss Payment
  Standard (ISO 20022).
- The QR payment slip is rendered on **page 2** of the PDF, as a full-width
  payment part with the standard cutting lines.
- Access is controlled by two CiviCRM permissions: `access swissqr invoices`
  and `edit swissqr invoices`.

## Database tables

| Table | Contents |
|---|---|
| `civicrm_swissqr_invoice` | one row per invoice |
| `civicrm_swissqr_invoice_line` | invoice line items (n rows per invoice) |
| `civicrm_swissqr_service` | reusable service catalog |

These tables are created on install and are **not** dropped when the extension
is merely disabled.

## Translations

All `ts()` calls are scoped to the `ch.ipik.swissQRinvoice` domain, so the
extension is ready for translation files. To add a language, create:

```
l10n/de_DE/LC_MESSAGES/ch.ipik.swissQRinvoice.po
```

## Planned

Batch invoice generation for a group of contacts: the data model already
supports it, the user interface does not exist yet.
