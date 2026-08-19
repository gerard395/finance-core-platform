# Reporting

**Status:** Completed through R2 – Operational Reporting

## Bron van financiële waarheid

Reporting is een read-only afleiding van bestaande domeinwaarheid. `TrialBalance` leest uitsluitend `Posted` JournalEntries en hun regels, plus aangeleverde LedgerAccounts voor identiteit. `BalanceSheet` en `ProfitAndLoss` verwerken geen JournalEntries opnieuw: zij gebruiken uitsluitend `TrialBalanceResult` en LedgerAccounts voor classificatie.

R2 voegt drie operationele rapportages toe:

- `GeneralLedgerReport` leest uitsluitend Posted JournalEntries en berekent per regel de period movement balance als `vorige + debit - credit`;
- `OpenItemsReport` gebruikt uitsluitend `OpenItem::openAmountAt()` en `statusAt()` voor de gevraagde peildatum;
- `VatOverview` gebruikt uitsluitend immutable Fiscal-owned `TaxPosting`-feiten, inclusief afzonderlijke Original- en Reversal-records.

Rapportresultaten zijn reproduceerbare berekeningen. Reporting schrijft geen saldi, totalen of andere financiële waarheid terug naar Accounting of een andere capability en bevat geen database-, repository-, infrastructuur-, Laravel- of UI-code.

## Rapportagecontext

Iedere Trial Balance wordt expliciet berekend voor:

- één `AdministrationId`;
- een inclusieve start- en einddatum;
- één `Currency`.

`TrialBalanceResult` behoudt deze context immutable. Balance Sheet neemt AdministrationId, Currency en de einddatum als balansdatum over; de expliciete balansdatum moet gelijk zijn aan die einddatum. Profit & Loss neemt AdministrationId, startdatum, einddatum en Currency ongewijzigd over.

## Tekenconventies

Trial Balance gebruikt exact:

```text
balance = totalDebit - totalCredit
```

- Asset heeft normaal een positieve balance en Balance Sheet gebruikt die direct.
- Liability en Equity hebben normaal een negatieve balance; uitsluitend Balance Sheet normaliseert deze presentatiewaarden met `Money::absolute()`.
- Revenue heeft normaal een negatieve balance; uitsluitend Profit & Loss normaliseert deze presentatiewaarde met `Money::absolute()`.
- Expense heeft normaal een positieve balance en Profit & Loss gebruikt die direct.
- Balance Sheet vergelijkt exact `totalAssets` met `totalLiabilities + totalEquity`.
- Profit & Loss berekent exact `netResult = totalRevenue - totalExpenses` met `Money::subtract()`.

Normalisatie muteert de Trial Balance niet. Alle bedragen blijven `Money`; er worden geen floats of presentatierounding gebruikt.

## Grenzen en guards

- Draft JournalEntries tellen nooit mee.
- Administration- en inclusieve periodefilters worden vóór aggregatie toegepast.
- Ontbrekende rekeningkoppelingen en dubbele aangeleverde LedgerAccounts worden geweigerd.
- Een startdatum na de einddatum, een afwijkende Balance Sheet-datum en gemengde Currency worden geweigerd.
- Reporting maakt of post geen JournalEntries en muteert geen Accounting-objecten.
- Exacte algemene geldrekenkunde (`zero`, `add`, `subtract`, `absolute`, `equals`) blijft in Shared Finance `Money`.

## Bekende niet-blokkerende beperkingen

- De calculators werken op volledig aangeleverde in-memory domeinobjecten; selectie, autorisatie, persistence, caching en projecties horen later in Application/Infrastructure.
- Balance Sheet veronderstelt dat de aangeleverde Trial Balance de benodigde openings- en historische saldi bevat. Boekjaaropening, resultaatbestemming en carry-forward-orchestratie zijn nog niet gemodelleerd.
- `absolute()` volgt de vastgelegde presentatieconventie en signaleert geen afwijkende debet-/creditsaldi als aparte rapportwaarschuwing.
- General Ledger begint bewust op nul bij de start van de gevraagde periode en rapporteert daarom een period movement balance, geen historisch openingssaldo.
- `VatOverviewLine` biedt volledige audit-drill-down, maar de gedelegeerde getter-returntypes zijn momenteel nog `mixed`; de concrete waarden blijven wel typed value objects uit `TaxPosting`.
- De vier fiscale Application-orchestrators bevatten symmetrische Sales/Purchasing- en Original/Reversal-structuur. Dit schaadt de capabilitygrenzen of correctheid niet, maar verdient consolidatie wanneer meer fiscale documenttypen worden toegevoegd.
- Selectie, autorisatie, concurrency-safe fiscale identiteitsuniciteit, persistence, export en UI-contracten vallen nog buiten Domain.

## Aanbevolen vervolg

- Application query-contracten en Infrastructure-projecties voor schaalbare selectie, zonder nieuwe financiële waarheid.
- Persistenceconstraints en transacties voor concurrency-safe `TaxPostingId`-uniciteit en dubbele-reversalpreventie.
- Typed audit-getters voor `VatOverviewLine` en, bij groei, capabilityneutrale consolidatie van fiscale orchestration.
- Aging buckets, export-/presentatiecontracten en autorisatie bovenop de bestaande read-only resultaten.
- Boekjaaropening, carry-forward en resultaatbestemming expliciet ontwerpen voor balansrapportage.
- Rapportwaarschuwingen en drill-down/audit-trace voor afwijkende saldi.
