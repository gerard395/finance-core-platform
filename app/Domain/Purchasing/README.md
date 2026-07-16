# Purchasing Domain

## Doel

Purchasing beheert ontvangen inkoopdocumenten als zelfstandige, frameworkonafhankelijke aggregates. `PurchaseInvoice` gebruikt geen inheritance of runtime-afhankelijkheid op Sales en beheert zijn eigen `PurchaseInvoiceLine` child entities.

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
- Iedere PurchaseInvoice bevat minimaal één PurchaseInvoiceLine voordat deze wordt gefinaliseerd.
- Regels hebben een unieke immutable identiteit en kunnen alleen worden toegevoegd of verwijderd zolang de factuur Draft is.
- Line totals worden zonder floats exact afgeleid via gedeelde `Money`, `Quantity` en `LineDescription` value objects.

## Grenzen

- Btw behoort tot Tax.
- Financiële mutaties en JournalEntries lopen later uitsluitend via Accounting en PostingEngine.
- Betalingen, OpenItems, repositories, persistence, Laravel en infrastructuur vallen buiten P1-001.
