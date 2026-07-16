# Purchasing Domain

## Doel

Purchasing beheert ontvangen inkoopdocumenten als zelfstandige, frameworkonafhankelijke aggregates. `PurchaseInvoice` en `PurchaseCreditInvoice` gebruiken geen inheritance of runtime-afhankelijkheid op Sales en beheren hun eigen child entities.

## PurchaseInvoice

PurchaseInvoice bevat immutable identiteit, factuurnummer, AdministrationId, SupplierId, Currency, factuurdatum, vervaldatum en optionele SupplierReference. Alleen de status is wijzigbaar via expliciete domeinmethoden.

```text
Draft → Finalized → Posted → Paid
Draft → Cancelled
Finalized → Cancelled
```

Paid en Cancelled zijn eindstatussen. Dezelfde overgang herhalen is idempotent; iedere andere overgang resulteert in een `DomainException`. Posted kan niet worden geannuleerd.

## PurchaseCreditInvoice

PurchaseCreditInvoice is een zelfstandig Aggregate Root met immutable identiteit, creditfactuurnummer, AdministrationId, SupplierId, Currency, creditfactuurdatum en een optionele verwijzing naar de bron-PurchaseInvoice.

```text
Draft → Finalized → Posted
Draft → Cancelled
Finalized → Cancelled
```

Posted en Cancelled zijn eindstatussen. Dezelfde overgang herhalen is idempotent; iedere andere overgang resulteert in een `DomainException`.

## Invarianten

- DueDate ligt op of na InvoiceDate.
- Identiteit, nummer, AdministrationId, SupplierId, Currency en datums zijn immutable.
- SupplierReference is optioneel en immutable en bevat geen lege waarde of omliggende whitespace.
- Status wijzigt uitsluitend via `finalize()`, `post()`, `markAsPaid()` en `cancel()`.
- Iedere PurchaseInvoice en PurchaseCreditInvoice bevat minimaal één eigen regel voordat deze wordt gefinaliseerd.
- Regels hebben binnen hun aggregate een unieke immutable identiteit en kunnen alleen worden toegevoegd of verwijderd zolang het document Draft is.
- Line totals worden zonder floats exact afgeleid via gedeelde `Money`, `Quantity` en `LineDescription` value objects.
- De context van een PurchaseCreditInvoice is immutable en de status wijzigt uitsluitend via `finalize()`, `post()` en `cancel()`.

## Grenzen

- Btw behoort tot Tax.
- Financiële mutaties en JournalEntries lopen uitsluitend via Accounting; `PostingRequest` en `PostingEngine` behoren tot Accounting en blijven buiten Purchasing.
- Btw, boekingen, betalingen en OpenItems worden niet door Purchasing beheerd.
- Purchasing bevat geen Laravel-, database-, repository- of infrastructuurafhankelijkheden.
