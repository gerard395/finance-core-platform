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
- Import en importformaten zoals CAMT053, MT940 en CSV vallen buiten de Banking Foundation.
- Reconciliation, settlement en PSD2 vallen buiten de Banking Foundation.
- PostingRequest en PostingEngine zijn Accounting-verantwoordelijkheden en vallen buiten Banking.
- Banking bevat geen Laravel-, database-, repository- of infrastructuurafhankelijkheden.

## B2 manual payment alignment

B2 behoudt BankTransaction als Aggregate Root en enige postingbron. Zij representeert
een handmatige geldbeweging op een Administration-owned operationele bankrekening met
signed EUR Money: positief is ontvangst, negatief is uitgave. Exact één Payment-child
interpreteert die beweging als CustomerReceipt of SupplierPayment en bezit één of meer
positieve allocations naar compatible OpenItems van dezelfde Relation. Payment heeft
geen zelfstandig statusveld.

De toekomstige lifecycle is Draft → Finalized → Posted en Draft → Cancelled. Finalize
bevriest movement, Payment en allocations; uitsluitend een Application-orchestrator mag
via PostingEngine atomair de Bank JournalEntry, Applied OpenItemSettlements, linkage en
Posted-status bewaren. TransactionDate en expliciete PostingDate blijven afzonderlijk.

Relation BankAccount blijft counterparty-masterdata. B2 introduceert een eigen
AdministrationBankAccount-identity en per rekening een expliciete
BankingPostingConfiguration naar active Bank Journal en active Asset Bank account.
OpenItems dragen voor nieuwe facts hun historische AR/AP control-account-ID; geen
actuele configuratie of rekeningheuristiek reconstrueert die waarheid.

B2-001A heeft dit predecessorcontract gerealiseerd als verplicht immutable
`OpenItem.controlLedgerAccountId`, inclusief deterministische historische backfill en
same-tenant RESTRICT-FK. Een later inactive control account blijft historische
postingtruth en wordt bij settlement niet automatisch vervangen.

`OpenItemSettlement` is cashrealisatie; `OpenItemMatch` blijft uitsluitend een
opposite-side documentbalance-match. Partial en meerdere payments en één payment over
meerdere same-Relation OpenItems zijn toegestaan. Allocation sum moet exact de absolute
bankwaarde zijn; unallocated, overpayment/suspense, FX, import en reconciliation blijven
deferred.

## Capabilitystatus

Banking Foundation first domain iteration completed; B2-000 manual payment contracts
aligned. B2-001 provides the independent permissions and canonical roles without
automatic assignments, plus product-managed EUR AdministrationBankAccounts and typed
per-account posting configuration. BankTransaction/Payment persistence starts in
B2-002.
