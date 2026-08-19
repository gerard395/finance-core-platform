# Finance Core Platform Roadmap

## Productvisie

Finance Core Platform biedt een betrouwbare, uitbreidbare financiële kern waarmee organisaties hun administratie, commerciële processen en financiële verantwoording beheerst kunnen uitvoeren. Het platform groeit vanuit een frameworkonafhankelijk domeinmodel naar een productieklare oplossing via kleine, toetsbare capabilities.

## Ontwikkelprincipes

- **Domain First:** domeinbegrippen, invarianten en gedrag sturen het ontwerp.
- **Framework Independent Domain:** de Domain-laag blijft onafhankelijk van Laravel en infrastructuur.
- **Story Driven Development:** elke wijziging is gekoppeld aan een afgebakende, toetsbare story.
- **One Story = One Commit:** een story wordt als één herkenbare en zelfstandig beoordeelbare commit vastgelegd.
- **Test First waar passend:** gedrag en invarianten worden waar zinvol eerst of gelijktijdig met tests gespecificeerd.
- **Small Vertical Slices:** functionaliteit groeit in kleine, complete stappen met directe waarde en beperkte scope.

## Capability 01 – Administration

**Status:** Completed for first domain iteration
**Epic:** Administration

### Stories

- S1-001
- S1-002
- S1-003A
- S1-003B
- S1-005
- S1-006
- S1-007
- S1-008
- S1-009
- S1-010

## Capability 02 – Identity & Security

**Status:** Planned

### Capability Identity & Security

Deze capability omvat gebruikers, administratielidmaatschappen, rollen en expliciete businessautorisaties. De eerste autorisaties per productcapability zijn vastgelegd in het [Permission Catalogue](../.ai/PERMISSION_CATALOGUE.md); rollen en technische handhaving volgen in afzonderlijke stories.

## Capability 03 – Relations

**Status:** Planned

## Capability 04 – Sales

**Status:** In Progress

### Capability Sales

Sales beheert het commerciële traject van offerte tot verkoopfactuur en verkoopcreditfactuur. De capability omvat de Aggregate Roots `Quotation`, `Order`, `SalesInvoice` en `SalesCreditInvoice`, elk met eigen regels, statusovergangen en child lines.

De eerste domeiniteratie is voltooid in S4-000 tot en met S4-010. Aggregate boundaries, exacte Money-berekeningen en Application-orchestratie zijn vastgelegd in `app/Domain/Sales/README.md`. Btw, Posting Engine, betalingen en openstaande posten blijven bij Tax, Accounting en Banking.

## Capability 05 – Purchasing

**Status:** Completed for first domain iteration

### Capability Purchasing

Purchasing beheert ontvangen inkoopfacturen en inkoopcreditfacturen. De capability omvat de Aggregate Roots `PurchaseInvoice` en `PurchaseCreditInvoice`, met respectievelijk `PurchaseInvoiceLine` en `PurchaseCreditInvoiceLine` als child entities.

Purchasing modelleert eigen aggregategrenzen en statusgedrag en erft niet van Sales. Neutrale financiële en regelconcepten worden hergebruikt zonder ze binnen Purchasing te dupliceren: `Money` en `Currency` uit Shared Finance en `Quantity` en `LineDescription` uit Shared Commerce. Purchasing heeft geen afhankelijkheid op Sales.

Een PurchaseInvoice doorloopt `Draft → Finalized → Posted → Paid`; een PurchaseCreditInvoice doorloopt `Draft → Finalized → Posted`. Beide kunnen vanuit Draft of Finalized worden geannuleerd. Minimaal één eigen regel is vereist vóór finalisatie en regelmutaties zijn daarna geblokkeerd.

De eerste Purchasing-domeiniteratie is voltooid in P1-000 tot en met P1-005. Financiële integratiecontracten blijven eigendom van Accounting: `PostingRequest` en `PostingEngine` staan buiten Purchasing. Purchasing maakt nooit zelf JournalEntries of OpenItems.

## Capability 06 – Accounting

**Status:** Completed for first domain iteration

### Capability Accounting

Accounting beheert grootboekrekeningen, dagboeken, journaalposten en openstaande posten. De capability omvat de Aggregate Roots `LedgerAccount`, `Journal`, `JournalEntry` en `OpenItem`, met `JournalEntryLine` als child entity van `JournalEntry`.

Alle financiële mutaties verlopen via de `PostingEngine`. Facturen, betalingen en banktransacties leveren daarvoor een `PostingRequest` aan en ontvangen een `PostingResult`. De `PostingEngine` is de enige component die `JournalEntry`-aggregates mag aanmaken.

De eerste Accounting-domeiniteratie is geïmplementeerd. Het Accounting-domein blijft frameworkonafhankelijk en bevat geen Laravel-, database- of infrastructuurafhankelijkheden.

## Capability 07 – Fiscal

**Status:** Planned

### Capability Fiscal

Fiscal beheert capabilityneutrale fiscale classificatie en berekening. De eerste Aggregate Root is `TaxCode`, met de immutable value objects `TaxRate`, `TaxCodeCode` en `TaxCodeName`; de frameworkonafhankelijke domain service `TaxCalculation` berekent fiscale bedragen met `Money`.

Iedere TaxCode heeft precies één actief TaxRate. Fiscal maakt geen JournalEntries: Sales en Purchasing gebruiken Fiscal voor berekening, waarna alle financiële mutaties en boekingen uitsluitend via Accounting en `PostingEngine` verlopen. Land-specifieke fiscale regimes en aangifteregels behoren niet tot de kern.

## Capability 08 – Banking

**Status:** Completed for first domain iteration

### Capability Banking

Banking beheert bankmutaties en de koppeling daarvan aan openstaande posten. `BankTransaction` is de Aggregate Root en primaire financiële gebeurtenis; het aggregate beheert nul, één of meerdere `Payment` child entities. Iedere Payment verwijst naar precies één `OpenItem` uit Accounting.

De frameworkonafhankelijke domain service `Matching` telt bestaande Payment-allocaties exact op met `Money` en vergelijkt die som met het absolute BankTransaction-bedrag. Alleen een Imported transactie met minimaal één volledig passende allocatie wordt Matched. Mislukte matching wijzigt niets, Matched opnieuw matchen is idempotent en Posted wordt geweigerd.

De eerste Banking-domeiniteratie is voltooid in B1-000 tot en met B1-004. Banking is niet afhankelijk van Sales of Purchasing en mag uitsluitend afhankelijk zijn van Shared, Administration, Accounting en Relations. Iedere BankTransaction hoort bij precies één Administration en legt zowel de immutable AdministrationId als de immutable BankAccountId vast. Consistentie tussen beide identifiers wordt later buiten het aggregate gecontroleerd, omdat daarvoor externe gegevens nodig zijn.

OpenItem en alle financiële boekingen blijven eigendom van Accounting; uitsluitend `PostingEngine` maakt JournalEntries. UI, bankimport, reconciliation, PSD2, Laravel, databases en infrastructuur vallen buiten de Banking Foundation.

## Capability 09 – Documents

**Status:** Planned

## Capability 10 – Reporting

**Status:** Reporting Foundation completed

### Capability Reporting

Reporting is een read-only capability ten opzichte van de financiële domeinwaarheid. Zij leidt rapportages af uit uitsluitend geposte `JournalEntry`-aggregates en hun `JournalEntryLine`-regels, aangevuld met `LedgerAccount`, `OpenItem` en fiscale gegevens waar het rapport dat vereist. Draft JournalEntries tellen nooit mee. Iedere rapportvraag bevat een expliciet `Administration`-filter en een expliciete datum- of periodeafbakening.

De eerste rapportages Trial Balance, Balance Sheet en Profit & Loss zijn frameworkonafhankelijk geïmplementeerd. Trial Balance gebruikt exact `balance = debit - credit`; Balance Sheet normaliseert uitsluitend Liability en Equity met `absolute()`, terwijl Profit & Loss uitsluitend Revenue normaliseert. Balance Sheet vergelijkt `totalAssets = totalLiabilities + totalEquity` en Profit & Loss berekent `netResult = totalRevenue - totalExpenses`. AdministrationId, periode en Currency blijven expliciet en immutable behouden.

General Ledger Card, Open Items Report en VAT Overview volgen later. Reporting maakt geen JournalEntries en wijzigt geen Accounting-, Sales-, Purchasing-, Banking- of Fiscal-aggregates. Grootboeksaldi blijven berekende uitkomsten en worden niet als domeinwaarheid opgeslagen. Latere read models en projecties mogen in Application/Infrastructure worden toegevoegd, maar vormen geen nieuwe financiële waarheid.

## Capability 11 – Workflow

**Status:** Planned

## Capability 12 – Platform

**Status:** Planned

## Milestone M5 – Integrated Financial Flow

**Status:** Completed

### Epic I1 – Prove the System

I1 bewijst dat de bestaande Sales-, Accounting- en Banking-componenten samen één financiële keten kunnen vormen, zonder nieuwe domeinaggregates of financiële boekingsroutes te introduceren.

```text
SalesInvoice
    ↓
PostingRequest → PostingValidation → PostingEngine
    ↓
JournalEntry
    ↓
OpenItem
    ↓
Payment binnen BankTransaction
    ↓
Matching
    ↓
OpenItem settle() → close()
```

Sales blijft verantwoordelijk tot en met de gefinaliseerde SalesInvoice. Accounting neemt de boekingsopdracht over bij PostingRequest; uitsluitend PostingEngine maakt de geposte JournalEntry. OpenItem blijft een Accounting Aggregate Root. Banking beheert BankTransaction en Payment en Matching valideert uitsluitend de allocaties. Na succesvolle matching moet application-orchestratie het gekoppelde OpenItem vereffenen en sluiten.

I1-001 tot en met I1-004 bewijzen de application-koppelingen voor SalesInvoice, PurchaseInvoice en BankTransaction naar PostingRequest en de volledige verkoopfactuur-tot-afwikkelingketen. De Application-laag kiest expliciet JournalId, LedgerAccountIds, regelidentiteiten, boekingsdatum en referentie. PostingValidation bewaakt de boekingsopdracht en uitsluitend PostingEngine maakt JournalEntries. Matching valideert Payment-allocaties zonder OpenItems te muteren; application-orchestratie past een succesvol MatchingResult toe via `OpenItem::settle()` en `close()`.

De acceptance review in I1-005 bevestigt dat geen fundamentele architectuurwijziging nodig is voordat Reporting start. Reporting moet nog een expliciet read-/projectiemodel, periode- en administratieafbakening en audit-/correctietrace ontwerpen. Dit is vervolgscope, geen blocker voor het starten van de Reporting-milestone. De exclusiviteit van PostingEngine wordt momenteel door architectuurregels en productiecode bewaakt en nog niet door een technische modulegrens afgedwongen.

## Milestone M6 – Financial Insight

**Status:** In Progress

### Batch R1 – Reporting Foundation

**Status:** Completed

R1 levert een frameworkonafhankelijke Trial Balance, Balance Sheet en Profit & Loss als read-only afleiding van financiële domeinwaarheid. Alleen Posted JournalEntries voeden de Trial Balance; Balance Sheet en Profit & Loss hergebruiken uitsluitend `TrialBalanceResult`. Money-rekenkunde blijft exact en capability-neutraal in Shared Finance. De review in R1-004 bevestigt de architectuurgrenzen, contextovername, tekenconventies en testdekking.

Niet-blokkerend vervolg omvat Application/Infrastructure-projecties voor schaalbare selectie, expliciete boekjaaropening en resultaatbestemming, General Ledger Card, Open Items/Aging Report, VAT Overview en audit-drill-down. Deze uitbreidingen mogen geen nieuwe financiële waarheid introduceren.

### Batch R2 – Operational Reporting

**Status:** Designed; implementation starts with R2-001

R2 ontwerpt dagelijkse boekhoudkundige rapportages bovenop de read-only grenzen van R1. De General Ledger Report/Grootboekkaart is met de huidige geposte JournalEntry-bron uitvoerbaar en wordt als R2-001 als eerste aanbevolen. Het rapport selecteert expliciet op Administration, inclusieve periode, Currency en optioneel LedgerAccountId en ordent deterministisch op boekingsdatum, JournalEntryId en JournalEntryLineId. De running balance is een berekende `debit - credit`-periodebeweging en wordt niet opgeslagen.

Een huidig Open Items-overzicht kan de actuele OpenItem-toestand tonen, maar een betrouwbaar historisch rapport op een willekeurige peildatum is nog niet mogelijk: OpenItem bevat geen ontstaans-/boekingsdatum en geen gedateerde settlementhistorie. Een prerequisite-story moet eerst minimale temporele bronwaarheid ontwerpen; Reporting mag die historie niet reconstrueren of settlement-logica overnemen.

VAT Overview is eveneens geblokkeerd als implementatiestory. Fiscal kent TaxCode en losse TaxCalculationResult-waarden, maar Sales/Purchasing-documentregels en geposte JournalEntryLines bevatten geen duurzame TaxCodeId, historische TaxRate, fiscale grondslag, taxAmount of trace naar de fiscale bronregel. De huidige invoice-posting maakt bovendien geen fiscaal geclassificeerde btw-regels. R2-003 volgt pas nadat deze fiscale traceerbaarheid capability-eigen en immutable is ontworpen en door de posting chain behouden blijft.

## Releases

- **v0.1 – Platform Foundation**
- **v0.2 – Administration Capability**
- **v0.3 – Relations Capability**
- **v0.4 – Sales Capability**
- **v0.5 – Purchasing Capability**
- **v0.6 – Accounting Capability**
- **v0.7 – Banking + Fiscal**
- **v0.8 – Reporting**
- **v0.9 – Workflow**
- **v1.0 – Production Ready**
