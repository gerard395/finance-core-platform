# Banking Domain

## Doel

Banking beheert bankmutaties als zelfstandige, frameworkonafhankelijke aggregates. `BankTransaction` is de primaire financiële gebeurtenis en verwijst naar een bestaande BankAccount uit Relations en een Administration.

## BankTransaction

BankTransaction bevat immutable identiteit, BankAccountId, AdministrationId, BookingDate, ValueDate, Money-bedrag, referentie en omschrijving. Alleen de status wijzigt via expliciet domeingedrag.

```text
Imported → Matched → Posted
```

Dezelfde overgang herhalen is idempotent. Posted is een eindstatus; iedere andere ongeldige overgang resulteert in een `DomainException`.

## Grenzen

- Geldbedragen gebruiken het gedeelde `Money` value object en geen floats.
- Banking heeft geen dependency op Sales of Purchasing.
- Banking mag uitsluitend afhankelijk zijn van Shared, Accounting en Relations.
- BankTransaction maakt geen JournalEntries; financiële boekingen blijven de verantwoordelijkheid van Accounting en PostingEngine.
- Payment, Matching, CAMT053, MT940, PSD2, CSV-import, PostingRequest en PostingEngine vallen buiten B1-001.
- Banking bevat geen Laravel-, database-, repository- of infrastructuurafhankelijkheden.
