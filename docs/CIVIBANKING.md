# Bank reconciliation with CiviBanking

This extension does not read bank files itself. It produces references that
[CiviBanking](https://civicrm.org/extensions/civibanking) can match, and it
keeps invoices in step with the contributions CiviBanking updates.

## What this extension guarantees

**Invoices.** Each issued invoice has a *Pending* contribution. The reference
printed on its QR slip is stored, exactly as printed and without spaces, in that
contribution's **`invoice_id`** field. When the payment arrives, finding the
contribution whose `invoice_id` equals the reference in the bank file is enough.
Once CiviBanking records the payment, the invoice moves to *paid* (or keeps a
partial amount) automatically.

**Donation slips.** No contribution exists in advance, since the amount is
unknown. The reference identifies the donor contact and the mailing:

| IBAN type | Reference | Pattern |
|---|---|---|
| Regular IBAN (creditor reference) | `RF91D0000004567M000012` | `RF` + 2 check digits + `D` + contact id (10 digits) + `M` + mailing id (6 digits) |
| QR-IBAN (QR reference) | `000000000200000045670000120` | zero padding + `2` + contact id (10) + mailing id (6) + 1 check digit |

Invoice references follow the same idea (`RF..I` + invoice id, or QR reference
starting with `1`), but for invoices the `invoice_id` lookup is simpler.

To decode a reference from PHP:

```php
CRM_SwissQRInvoice_Reference::parse('RF91D0000004567M000012');
// ['kind' => 'donation', 'contact_id' => 4567, 'mailing_id' => 12]
```

## Configuration to build in CiviBanking

The exact configuration depends on the bank: Swiss banks all deliver ISO 20022
camt files, but layouts differ in detail (camt.053 statement or camt.054
notification, individual or batched bookings). Start from the camt example
importer shipped with CiviBanking and test it with a real file from the bank
before touching production. The steps:

1. **Importer** — camt XML. Make sure the structured reference of each booking
   (element `RmtInf/Strd/CdtrRefInf/Ref`) is imported into a transaction field.
   If the bank batches QR payments into one booking in the camt.053 statement,
   use the camt.054 notification instead, which lists them individually.

2. **Invoices** — look up a contribution by `invoice_id` = imported reference,
   then let the existing-contribution matcher record the payment on it.

3. **Donations** — a regex analyser on the reference:
   - creditor reference: `^RF\d{2}D(\d{10})M(\d{6})$`
   - QR reference: `^0*2(\d{10})(\d{6})\d$`

   Group 1 is the contact id (leading zeros to strip), group 2 the mailing id.
   Then a matcher that creates a new contribution for that contact, with
   financial type *Donation*.

4. **Everything else** — left for manual review, as usual in CiviBanking.

## Checking the result

After an import, open *Invoicing → All invoices*: invoices whose contribution
was completed show as paid. The list also runs a catch-up query on each visit,
so an invoice is updated even if a payment was recorded in a way that bypassed
CiviCRM's hooks.
