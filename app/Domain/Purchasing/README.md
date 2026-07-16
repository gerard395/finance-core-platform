# Purchasing Domain

## Doel

Purchasing beheert ontvangen inkoopdocumenten als zelfstandige, frameworkonafhankelijke aggregates. P1-001 introduceert `PurchaseInvoice` zonder inheritance of runtime-afhankelijkheid op Sales.

## PurchaseInvoice

PurchaseInvoice bevat immutable identiteit, factuurnummer, AdministrationId, SupplierId, Currency, factuurdatum, vervaldatum en optionele SupplierReference. Alleen de status is wijzigbaar via expliciete domeinmethoden.

```text
Draft → Finalized → Posted → Paid
Draft → Cancelled
Finalized → Cancelled
```

Paid en Cancelled zijn eindstatussen. Dezelfde overgang herhalen is idempotent; iedere andere overgang resulteert in een `DomainException`. Posted kan niet worden geannuleerd.

## Invarianten

- DueDate ligt op of na InvoiceDate.
- Identiteit, nummer, AdministrationId, SupplierId, Currency en datums zijn immutable.
- SupplierReference is optioneel en immutable en bevat geen lege waarde of omliggende whitespace.
- Status wijzigt uitsluitend via `finalize()`, `post()`, `markAsPaid()` en `cancel()`.

## Grenzen

- P1-001 bevat nog geen PurchaseInvoiceLines; de minimumregelcontrole vóór finaliseren volgt samen met die child entity.
- Btw behoort tot Tax.
- Financiële mutaties en JournalEntries lopen later uitsluitend via Accounting en PostingEngine.
- Betalingen, OpenItems, repositories, persistence, Laravel en infrastructuur vallen buiten P1-001.
