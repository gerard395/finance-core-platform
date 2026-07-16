# Banking Domain

## Doel

Banking beheert bankmutaties als zelfstandige, frameworkonafhankelijke aggregates. `BankTransaction` is de primaire financiële gebeurtenis en verwijst naar een bestaande BankAccount uit Relations en een Administration.

## BankTransaction

BankTransaction bevat immutable identiteit, BankAccountId, AdministrationId, BookingDate, ValueDate, Money-bedrag, referentie en omschrijving. Het aggregate beheert zijn Payment child entities; daarnaast wijzigt alleen de status via expliciet domeingedrag.

Iedere BankTransaction hoort bij precies één Administration. AdministrationId en BankAccountId worden beide immutable en expliciet vastgelegd. De consistentie tussen deze identifiers wordt later buiten het aggregate gecontroleerd, omdat daarvoor externe gegevens nodig zijn.

```text
Imported → Matched → Posted
```

Dezelfde overgang herhalen is idempotent. Posted is een eindstatus; iedere andere ongeldige overgang resulteert in een `DomainException`.

## Payment

Payment bestaat uitsluitend als child entity binnen BankTransaction en bevat een immutable PaymentId, OpenItemId en positief Money-bedrag. Payment-mutaties zijn alleen toegestaan zolang de BankTransaction Imported is. Payment-bedragen gebruiken dezelfde Currency als de BankTransaction en dubbele PaymentId-waarden worden geweigerd.

## Matching

De frameworkonafhankelijke domain service Matching telt bestaande Payment-allocaties exact op via `Money::add()` en vergelijkt de som met de absolute waarde van het BankTransaction-bedrag. Alleen een Imported transactie met minimaal één volledig passende allocatie kan naar Matched overgaan. Een mislukte match laat transactie en Payments ongewijzigd; opnieuw matchen van Matched is idempotent en Posted wordt geweigerd.

## Grenzen

- Geldbedragen gebruiken het gedeelde `Money` value object en geen floats.
- Banking heeft geen dependency op Sales of Purchasing.
- Banking mag uitsluitend afhankelijk zijn van Shared, Administration, Accounting en Relations.
- BankTransaction maakt geen JournalEntries; financiële boekingen blijven de verantwoordelijkheid van Accounting en PostingEngine.
- Matching maakt geen Payments, JournalEntries of PostingRequests en muteert geen allocaties.
- Settlement, CAMT053, MT940, PSD2, CSV-import, PostingRequest en PostingEngine vallen buiten B1-003.
- Banking bevat geen Laravel-, database-, repository- of infrastructuurafhankelijkheden.
