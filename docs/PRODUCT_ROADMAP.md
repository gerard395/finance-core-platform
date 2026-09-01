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

**Status:** Completed for first web iteration

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

Een PurchaseInvoice doorloopt `Draft → Finalized → Posted`; paymentstate wordt uit het Payable OpenItem afgeleid. Een PurchaseCreditInvoice doorloopt `Draft → Finalized → Posted`. Beide kunnen vanuit Draft of Finalized worden geannuleerd. Minimaal één eigen regel is vereist vóór finalisatie en regelmutaties zijn daarna geblokkeerd.

De eerste Purchasing-domeiniteratie is voltooid in P1-000 tot en met P1-005. Financiële integratiecontracten blijven eigendom van Accounting: `PostingRequest` en `PostingEngine` staan buiten Purchasing. Purchasing maakt nooit zelf JournalEntries of OpenItems.

## Capability 06 – Accounting

**Status:** Completed for first domain iteration

### Capability Accounting

Accounting beheert grootboekrekeningen, dagboeken, journaalposten en openstaande posten. De capability omvat de Aggregate Roots `LedgerAccount`, `Journal`, `JournalEntry` en `OpenItem`, met `JournalEntryLine` als child entity van `JournalEntry`.

Alle financiële mutaties verlopen via de `PostingEngine`. Facturen, betalingen en banktransacties leveren daarvoor een `PostingRequest` aan en ontvangen een `PostingResult`. De `PostingEngine` is de enige component die `JournalEntry`-aggregates mag aanmaken.

De eerste Accounting-domeiniteratie is geïmplementeerd. Het Accounting-domein blijft frameworkonafhankelijk en bevat geen Laravel-, database- of infrastructuurafhankelijkheden.

## Capability 07 – Fiscal

**Status:** Fiscal posting trace completed for R2

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

B2-000 lijnt de volgende productiteratie uit. Manual BankTransaction wordt de enige
postingbron voor één CustomerReceipt of SupplierPayment met meerdere same-Relation
OpenItem-allocations. Signed EUR Money onderscheidt ontvangst/uitgave; Draft → Finalized
→ Posted bevriest de intent voordat één outer transaction via PostingEngine de Bank
JournalEntry, Applied settlements, linkage en Posted-status bewaart. Partial en meerdere
payments zijn toegestaan; allocation sum moet exact de absolute bankwaarde zijn.

B2-001 voegt typed authorization en productmatig onderhoudbare operationele
AdministrationBankAccounts plus per-rekening BankingPostingConfiguration toe. Die mapt
expliciet naar active Bank Journal en active Asset Bank LedgerAccount. Relation-
bankrekeningen en organisation-IBAN zijn geen Administration-cashaccount; historische
AR/AP-control-accounttruth hoort op het OpenItem. Bankimport, CAMT/MT940, PSD2/API,
reconciliation, FX, unallocated cash en overpayment/suspense blijven vervolgscope.

B2-001A rondt de Accounting-predecessor af: ieder OpenItem bewaart nu verplicht de
immutable AR/AP-control-LedgerAccount van zijn source posting. Historische facts zijn
deterministisch vanuit exact één same-side/exact-amount JournalEntryLine gebackfilld;
latere configuratiewijziging of accountdeactivation verandert die identity niet.

B2-002 maakt manual BankTransaction, exact één Payment en meerdere allocations duurzaam.
Draft kan atomair worden aangepast of geannuleerd; Finalize valideert normale
same-Relation EUR OpenItems, vereist exacte volledige allocatie en bevriest actor/tijd
plus control-accounttruth. Zij maakt nog geen financiële boeking of settlement; row
locks, BankingPostingConfiguration en PostingEngine blijven exclusief B2-003.

B2-003 realiseert de atomische financiële waarheid: een Finalized BankTransaction wordt
onder tenant-scoped BankTransaction- en deterministische OpenItem-locks geboekt via
PostingEngine, met één JournalEntry, één cash Settlement per allocation, één duurzame
postinglinkage en Posted-auditfacts. Actuele open balances worden onder lock herlezen;
historische OpenItem-controlaccounts blijven leidend. Echte MySQL-races borgen
idempotency en bescherming tegen oversettlement. Banking Web blijft B2-004.

B2-004 sluit B2 productmatig af met Bank → Betalingen: permission-onafhankelijke
list/detail, Draftbeheer en financieel Posten, veilige selectors voor operationele
bankrekeningen/Relations/OpenItems en immutable postingresultaten met settlements en
remaining balances. Receipt, supplier, partial, multiple-payment en multi-OpenItem
flows zijn end-to-end gevalideerd; de B2-003 concurrencygaranties blijven intact.
Import, reconciliation, overpayment, suspense, FX en reversal blijven deferred.

## Capability 09 – Documents

**Status:** Planned

## Capability 10 – Reporting

**Status:** Operational Reporting completed

### Capability Reporting

Reporting is een read-only capability ten opzichte van de financiële domeinwaarheid. Zij leidt rapportages af uit uitsluitend geposte `JournalEntry`-aggregates en hun `JournalEntryLine`-regels, aangevuld met `LedgerAccount`, `OpenItem` en fiscale gegevens waar het rapport dat vereist. Draft JournalEntries tellen nooit mee. Iedere rapportvraag bevat een expliciet `Administration`-filter en een expliciete datum- of periodeafbakening.

De eerste rapportages Trial Balance, Balance Sheet en Profit & Loss zijn frameworkonafhankelijk geïmplementeerd. Trial Balance gebruikt exact `balance = debit - credit`; Balance Sheet normaliseert uitsluitend Liability en Equity met `absolute()`, terwijl Profit & Loss uitsluitend Revenue normaliseert. Balance Sheet vergelijkt `totalAssets = totalLiabilities + totalEquity` en Profit & Loss berekent `netResult = totalRevenue - totalExpenses`. AdministrationId, periode en Currency blijven expliciet en immutable behouden.

R2 levert daarnaast General Ledger Report, historisch Open Items Report en VAT Overview. General Ledger leest alleen Posted JournalEntries; Open Items gebruikt de temporele Accounting-API; VAT Overview leest uitsluitend immutable Fiscal-owned TaxPostings. Reporting maakt geen JournalEntries en wijzigt geen Accounting-, Sales-, Purchasing-, Banking- of Fiscal-aggregates. Grootboeksaldi blijven berekende uitkomsten en worden niet als domeinwaarheid opgeslagen. Latere read models en projecties mogen in Application/Infrastructure worden toegevoegd, maar vormen geen nieuwe financiële waarheid.

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
OpenItem::applySettlement(...)
```

Sales blijft verantwoordelijk tot en met de gefinaliseerde SalesInvoice. Accounting neemt de boekingsopdracht over bij PostingRequest; uitsluitend PostingEngine maakt de geposte JournalEntry. OpenItem blijft een Accounting Aggregate Root. Banking beheert BankTransaction en Payment en Matching valideert uitsluitend de allocaties. Na succesvolle matching voegt Application-orchestratie een gedateerd settlementfeit met de werkelijk geposte JournalEntry als bron toe.

I1-001 tot en met I1-004 bewijzen de application-koppelingen voor SalesInvoice, PurchaseInvoice en BankTransaction naar PostingRequest en de volledige verkoopfactuur-tot-afwikkelingketen. De Application-laag kiest expliciet JournalId, LedgerAccountIds, regelidentiteiten, boekingsdatum en referentie. PostingValidation bewaakt de boekingsopdracht en uitsluitend PostingEngine maakt JournalEntries. Matching valideert Payment-allocaties zonder OpenItems te muteren; sinds R2-001B gebruikt application-orchestratie `OpenItem::applySettlement()` met immutable bron- en datumcontext.

De acceptance review in I1-005 bevestigt dat geen fundamentele architectuurwijziging nodig is voordat Reporting start. Reporting moet nog een expliciet read-/projectiemodel, periode- en administratieafbakening en audit-/correctietrace ontwerpen. Dit is vervolgscope, geen blocker voor het starten van de Reporting-milestone. De exclusiviteit van PostingEngine wordt momenteel door architectuurregels en productiecode bewaakt en nog niet door een technische modulegrens afgedwongen.

## Milestone M6 – Financial Insight

**Status:** Completed

### Batch R1 – Reporting Foundation

**Status:** Completed

R1 levert een frameworkonafhankelijke Trial Balance, Balance Sheet en Profit & Loss als read-only afleiding van financiële domeinwaarheid. Alleen Posted JournalEntries voeden de Trial Balance; Balance Sheet en Profit & Loss hergebruiken uitsluitend `TrialBalanceResult`. Money-rekenkunde blijft exact en capability-neutraal in Shared Finance. De review in R1-004 bevestigt de architectuurgrenzen, contextovername, tekenconventies en testdekking.

Niet-blokkerend vervolg omvat Application/Infrastructure-projecties voor schaalbare selectie, expliciete boekjaaropening en resultaatbestemming, General Ledger Card, Open Items/Aging Report, VAT Overview en audit-drill-down. Deze uitbreidingen mogen geen nieuwe financiële waarheid introduceren.

### Batch R2 – Operational Reporting

**Status:** Completed

R2 levert dagelijkse boekhoudkundige rapportages bovenop de read-only grenzen van R1. General Ledger selecteert Posted JournalEntries op Administration, inclusieve periode, Currency en optioneel LedgerAccountId, sorteert deterministisch en berekent een niet-opgeslagen `debit - credit`-periodebeweging.

Accounting bewaart OpenItem-openingscontext en append-only Applied/Reversal-settlements. Open Items rapporteert historische openstand en status uitsluitend via `openAmountAt()` en `statusAt()` en dupliceert geen settlementlogica.

Fiscal bewaart immutable Original/Reversal-TaxPostings met historische TaxRate, taxable base, taxAmount en volledige bron- en boekingstrace. Sales- en Purchasing-orchestrators laten uitsluitend PostingEngine boeken, bewaken TaxPosting-identiteiten vóór posting en creëren fiscale feiten pas na een succesvolle boeking. VAT Overview houdt Input en Output gescheiden, verwerkt correcties in hun eigen PostingDate-periode, behoudt 0%-classificaties en berekent exact `Output tax - Input tax`.

Niet-blokkerend vervolg: schaalbare queryprojecties, autorisatie, persistenceconstraints voor concurrency-safe fiscale uniciteit, Aging, typed VAT-auditgetters en export-/presentatiecontracten. Symmetrische fiscale orchestration kan bij groei capabilityneutraal worden geconsolideerd.

## Milestone M7 – Product / Presentation

**Status:** W1 completed and security/architecture reviewed

### Batch W1 – Web Foundation

W1 levert de eerste veilige, responsive webdoorsnede: login, selectie van een geautoriseerde actieve Administration, een autorisatiebewuste application shell, echte read-only dashboardwaarden en logout. De Presentation-laag gebruikt server-rendered Laravel Blade, Tailwind CSS en alleen beperkte native JavaScript-interactie. Zij roept Application-use-cases aan en bevat geen boekhoudkundige logica of geldberekeningen.

De actieve Administration wordt server-side in de sessie bewaard als onvertrouwde selectie en bij iedere administration-scoped request opnieuw getoetst aan een op dat moment geldig `AdministrationMembership`. Rollen en permissions worden eveneens server-side vóór uitvoering van de use case gecontroleerd. De algemene architectuur staat in `.ai/stories/W1-000.md`; de Identity-/authbridge en noodzakelijke persistencevolgorde in `.ai/stories/W1-000A.md`.

- W1-001A – User & Administration persistence foundation
- W1-001B – Membership & authorization persistence foundation
- W1-001C – Auth account bridge & provisioning
- W1-001D – Web authentication foundation
- W1-002 – Administration access and active administration selection
- W1-003 – Responsive application shell and navigation
- W1-004 – Dashboard presentation foundation
- W1-005 – Web foundation security and architecture review

W1-005 heeft de volledige flow zonder mergeblockers geaccepteerd. De standaard PHPUnit-command omvat nu ook Integration. Niet-blokkerend vervolg omvat auditlogging, password reset/MFA en productie-sessionbeleid, typed VAT-auditgetters, fiscale orchestrationconsolidatie en autoritatieve permissionmapping voordat nieuwe webmodules worden geactiveerd.

### Batch W2 – Relations Web Module

W2 realiseert de eerste administration-scoped beheerfunctionaliteit voor Relations en hun expliciete Customer-/Supplier-classificaties. De batch is afgerond met backend-afgedwongen permissions, tenant-veilige database-reads en writes, immutable RelationCode, active-only classificaties en transactioneel gelockte `C000001`-/`S000001`-nummerreeksen. Contacten, adressen en bankrekeningen blijven bewust buiten W2 zolang hun Relation-child persistence ontbreekt. Niet-blokkerend vervolg bestaat uit optimistic locking voor Relation-edits en mutation-auditlogging.

- W2-000 – Relations web module design
- W2-000T – Safe Git workflow automation
- W2-000A0 – Relations permission identity and provisioning
- W2-000A – Relations permission contracts
- W2-000B – Relations web read contracts
- W2-000C – Classification active/removal semantics
- W2-001 – Relations index
- W2-002 – Relation detail
- W2-003A – Relation write contracts
- W2-003 – Relation create/edit
- W2-004A – Customer/Supplier number provisioning
- W2-004 – Customer/Supplier classification UI
- W2-005 – Relations web module review

De aanbevolen vervolgbatch is Relations Child Data: tenant-veilige, aggregate-owned persistence en webflows voor Contacts, Addresses en BankAccounts. Deze basis voorkomt tijdelijke of gedupliceerde contact- en factuuradresmodellen wanneer daarna Sales Web wordt gebouwd.

### Batch W3 – Relations Child Data

W3 maakt de bestaande Relation-owned Contacts, Addresses en BankAccounts duurzaam en tenant-veilig beschikbaar op Relation detail. Volledige aggregate-reconstitutie en expliciete loaded-childveiligheid gaan vooraf aan childwrites. De huidige immutable adresinhoud vereist daarnaast een expliciet mutationcontract voordat adresbewerking kan worden gebouwd. Childverwijdering gebruikt in v1 de bestaande deactivate/reactivate-lifecycle en geen hard delete; duplicate- en primarybeleid worden niet verzonnen.

- W3-000 – Relations child data design
- W3-000A – Relation child reconstitution & lifecycle contracts
- W3-000B – Address mutation contract
- W3-001 – Contact persistence & application contracts
- W3-002 – Contact web UI
- W3-003 – Address persistence & application contracts
- W3-004 – Address web UI
- W3-005 – BankAccount persistence & application contracts
- W3-006 – BankAccount web UI
- W3-007 – Relations child data review

W3 is afgerond met volledige Relation-aggregate-reconstitutie en tenant-veilige persistence en webflows voor Contacts, Addresses en BankAccounts. Alle childverwijdering is een deactivate/reactivate-lifecycle met behoud van identity; cross-tenant en cross-Relation ownership wordt in Application én database afgedwongen. De afsluitende review corrigeerde bovendien Relation-edit voor volledig gehydrateerde aggregates en bevestigde dat parent- en childwrites elkaars state behouden. Het aanbevolen vervolg is Sales Web, dat deze duurzame Relation-masterdata nu zonder tijdelijke of gedupliceerde klantmodellen kan gebruiken.

### Batch W4 – Sales Web Module

**Status:** Domestic implementation completed; merge blocked by international VAT predecessors

W4 levert de eerste veilige administration-scoped verkoopdoorsnede voor Quotations,
Orders, SalesInvoices en volledige SalesCreditInvoices. De batch omvat onafhankelijke
businesspermissions, transactionele nummering, historische klant-/adres-/taxsnapshots,
tenant-veilige persistence, responsive webflows en financiële posting uitsluitend via
`PostingEngine`. Invoiceposting maakt een Receivable/Debit; creditposting maakt een
Receivable/Credit en matcht uitsluitend het actuele open sourcebedrag. Beide flows
zijn transactioneel, duurzaam idempotent en concurrency-safe.

- W4-000 – Sales web module design
- W4-000A0 – Sales business permission catalogue
- W4-000A – Sales permission identity & provisioning
- W4-000B – Sales numbering persistence
- W4-000C – Sales reconstitution & mutation contracts
- W4-000D0 – TaxCode persistence readiness
- W4-000D – Sales snapshot & tax readiness
- W4-001 – Quotation persistence & application contracts
- W4-002 – Quotation web UI
- W4-003 – Order persistence & application contracts
- W4-004 – Order web UI
- W4-005A – Order invoicing source contract
- W4-005 – Sales invoice persistence & application contracts
- W4-006 – Sales invoice web UI
- W4-007A00 – Journal ownership design
- W4-007A0 – Journal persistence and tenant linkage
- W4-007 – Transactional sales invoice posting
- W4-008 – Sales invoice posting web action
- W4-009 – Sales credit invoice persistence contracts
- W4-010A – Eligible credit source read contract
- W4-010 – Sales credit invoice web UI
- W4-011A – Receivable credit balance and matching semantics
- W4-011 – Transactional sales credit posting and reversal
- W4-012 – Sales credit posting web action
- W4-013 – Sales web module review and closure
- W4-NAV-001 – Cross-module Sales navigation

Bewust uitgesteld zijn Order→Invoice-allocation/conversie, document-PDF/e-maildelivery,
refund/customer-credituitbetaling en toepassing van customer credit op toekomstige
facturen. De aanbevolen volgende productbatch is **Sales Document Delivery / PDF &
Email**. Platformvervolg moet daarnaast centrale Administration-bootstrap voor
permissions, nummerreeksen en postingconfiguratie ontwerpen; Draft optimistic locking
blijft een belangrijke afzonderlijke hardeningstory.

### Batch W4A – Quotation to Order Conversion

**Status:** Completed; reviewed and merge-ready

W4A sluit de bestaande commerciële workflow tussen Accepted Quotation en Draft Order.
V1 converteert iedere Accepted Quotation maximaal één keer, kopieert immutable
customer snapshot, Currency en alle commerciële regels exact, gebruikt nieuwe Order-
en OrderLine-identiteiten en laat de Quotation ongewijzigd Accepted. Een nullable
tenant/source unique index, source-row locking en transactionele Ordernummering maken
double conversion database-safe; directe Orders met null source blijven onbeperkt.

- W4A-000 – Quotation → Order conversion design
- W4A-001 – Quotation → Order persistence/application conversion
- W4A-002 – Quotation → Order web action
- W4A-003 – Review & regression

Order→SalesInvoice blijft een afzonderlijke toekomstige allocation/conversie-
capability. Line-level Quotation→Order-traceability wordt pas toegevoegd als partial
conversion of allocation een feitelijke requirement wordt.

De W4A-003-review bevestigt de volledige Draft → Sent → Accepted → Draft Order-flow,
tenant- en concurrencyveiligheid, transactionele nummering, directe Orders zonder
bronofferte en exacte permissionhandhaving. Er zijn geen mergeblockers. De enige
reviewaanvulling is een expliciete regressieassertie dat **Offerte verzenden** geen
Order creëert. Order→SalesInvoice blijft deferred en valt nadrukkelijk buiten W4A.

### Batch W4B – Order Invoicing & Conversion

**Status:** Completed; reviewed and merge-ready

W4B converteert Confirmed en PartiallyInvoiced Orders veilig naar één of meerdere
Draft SalesInvoices met partial quantities per OrderLine. Draft-reservations bewaken
beschikbare quantity; Finalize schrijft immutable allocations en leidt uitsluitend
daaruit PartiallyInvoiced/FullyInvoiced af. Een append-only release maakt een
geannuleerde Draft-reservation vrij. De Order-headerlock, tenant-scoped constraints,
transactionele SalesInvoice-nummering en duurzame request-idempotency voorkomen
over-invoicing en dubbele browser-submits.

- W4B-000 – Order invoicing allocation & conversion design
- W4B-001 – Order invoicing facts, quantity ledger & persistence
- W4B-002 – Create SalesInvoice from Order contracts
- W4B-003 – Finalize/cancel allocation synchronization
- W4B-004 – Order invoicing Web flow
- W4B-004A – Dutch Tax Catalogue Bootstrap & Development Provisioning
- W4B-004B – International VAT Treatment & ICP Readiness Design
- W4B-004B0 – Administration Settings Authorization & Management Foundation (verplichte predecessor van W4B-004B1)
- W4B-004B1 – VAT Identification & Jurisdiction Master Data (typed nullable Relation/Administration masterdata en snapshot-readers gereed)
- W4B-004B2 – Tax Treatment & Reporting Classification (typed catalogus-, snapshot-, posting- en reversaltruth gereed)
- W4B-004B3 – Sales International Fiscal Snapshots & Dates (document-level partytruth, SupplyDate en typed readiness gereed)
- W4B-004B4 – Sales International Tax Selector Integration (expliciete tenantcatalogus en Webselectie gereed)
- W4B-004B5 – International Fiscal Posting & Credit Reversal
- W4B-004B6 – International VAT & ICP Readiness Review
- W4B-005 – Review & regression

Customersnapshot en Currency komen exact uit Order; Invoice-address en actieve Output
TaxCode worden expliciet bij invoice creation geselecteerd. Source-derived Draft-lines
zijn in v1 commercieel immutable. Orderstatus verandert bij invoice Finalize, niet bij
Draft create of financial Post. Credits en annulering na Finalize heropenen Order-
quantity niet automatisch.

W4B-005 heeft de volledige quantity-, idempotency-, concurrency-, snapshot-,
tenant-, authorization-, schema- en regressieketen beoordeeld. Er zijn geen
mergeblockers of reviewfixes. Allocation reversal/reopen bij finalized cancel en
credits blijft bewust deferred totdat de productpolicy en append-only facts daarvoor
zijn ontworpen. De aanbevolen volgende productbatch is **Sales Document Delivery /
PDF & Email**. Centrale Administration-bootstrap en verdere Draft-concurrency-
hardening blijven afzonderlijke platformvervolgen.

W4B-004B1–B6 sluiten de internationale fiscale predecessor af. BTW21, BTW9 en BTW0
blijven afzonderlijke domestic treatments; BTW0 is nooit een shortcut voor
verlegging, vrijstelling of outside-scope. EU-diensten, intracommunautaire goederen,
outside-scope en vrijstelling hebben eigen expliciete TaxTreatments, VAT-return-/ICP-
classificaties en selectorlabels. Customer/supplier VAT-ID, jurisdiction en
prestatiedatum worden immutable gesnapshot; posting en credits bewaren ook bij nul-btw
de taxable base en classificaties als duurzame TaxPosting-truth.

De W4B-004B6-review bevestigt dat domestic NL Sales VAT, expliciete EU B2B-service-
verlegging en de internationale invoice-/posting-/creditclassificaties end-to-end
veilig zijn. Er zijn geen fiscale mergeblockers. Automatische treatmentselectie,
VIES, PDF/e-mail en officiële VAT-/ICP-rapportage blijven afzonderlijke capabilities.
Niet-EUR officiële reporting vereist eerst een expliciete FX→EUR-policy en historische
koersfacts.

### Geparkeerde batch W4C – Dutch VAT & ICP Reporting

Dutch VAT & ICP Reporting is na PROJECT-GAP-001 bewust geparkeerd. De immutable
TaxPostings dragen de benodigde VAT-/ICP-classificaties en vormen een betrouwbare
toekomstige basis, maar officiële reporting vereist nog reportingperioden,
btw-aangifterubrieken, ICP-aggregatie per klant-btw-ID en goods/services,
creditcorrecties, rounding, reconciliatie, audit-drill-down en validatie. Niet-EUR
reporting vereist bovendien eerst historische FX→EUR-policy en koersfacts. Export en
elektronische indiening blijven latere afzonderlijke compliancegrenzen; rapportage
mag bestaande fiscale facts nooit achteraf herclassificeren.

### Batch W4D – Accounting Configuration (complete)

W4D-000 levert een expliciete, transactionele en idempotente development-entrypoint
waarmee uitsluitend een aangewezen Demo Administration een Sales Journal, Debiteuren-,
Omzet- en Output-VAT-rekening plus SalesPostingConfiguration krijgt. Deze kleine set
is demo-masterdata en nadrukkelijk geen production default chart of accounts.

- W4D-000 – Development Accounting Master Data Provisioning
- W4D-001 – Sales Posting Configuration Settings UI
- W4D-002 – Journal & Ledger Account Master Data Management
- W4D-003 – Review, Regression & Merge Readiness

W4D-001 laat een Administration user via Beheer → Instellingen expliciet het Sales
Journal en de Accounts Receivable-, Revenue- en Output VAT-rekening selecteren uit
geldige masterdata van dezelfde Administration.

W4D-001 kiest geen accounts heuristisch en maakt geen financiële defaults; het
onderhoudt alleen expliciete tenant-owned references. W4D-002 levert de productmatige
lifecycle voor tenant-owned Journal- en LedgerAccount-masterdata: list, create, rename,
activate en deactivate zonder delete. Code en type blijven immutable; tenant-uniciteit
en historische RESTRICT-references blijven leidend.

W4D is compleet. Deferred blijven chart-of-accounts-templates, opening balances,
handmatige JournalEntry-UI, Purchase accounting configuration, optimistic settings-
locking en uitgebreide mutation-auditlogging.

### Aanbevolen volgende batch W4E – Sales Document Delivery (PDF & Email)

PROJECT-GAP-001 bevestigt dat de commerciële en financiële Sales-kern operationeel is,
maar dat **Offerte verzenden** momenteel uitsluitend de lifecycle-overgang naar
`Sent` uitvoert. Er bestaan nog geen PDF-rendering, artifactopslag, e-maildelivery,
deliveryhistorie of retries. Relation Contacts hebben bruikbare e-maildata maar geen
primary/purposebeleid; Administration heeft evenmin complete tenant-owned issuer- en
sender-readiness. W4E ontwerpt deze semantics en predecessors expliciet en levert
daarna immutable Sales-documentrendering en auditable delivery voor Quotations,
SalesInvoices en SalesCreditInvoices. Purchasing Web en W4C blijven afzonderlijke,
geparkeerde vervolgcapabilities.

W4E-000 kiest voor v1 een precieze grens: Quotation `Sent` betekent uitsluitend dat
het geconfigureerde transport het bericht met het immutable artifact heeft
geaccepteerd, niet dat de ontvanger het aantoonbaar heeft ontvangen. Businessstatus,
PDF-artifact en append-only deliveryhistory blijven afzonderlijke waarheden. De batch
gebruikt installation-level mailtransport, Administration-scoped senderidentity,
private artifactstorage, een transactional outbox en queued delivery. Recipient-
purpose, issuer/payment/sender-readiness, Chromiumdeployment en een operationele
queueworker zijn verplichte predecessors binnen de batch.

W4E-003 levert de duurzame DeliveryRequest/Attempt/outbox- en installation-mailtransport-
basis. W4E-003A/B/C leveren production runtime, health/readiness, veilig herstel en
operationele OutcomeUnknown-resolutie.

W4E-003B kiest een portable single-host production Docker Compose-runtime met database-
queue, bewaakte worker/scheduler en durable MySQL/artifactvolumes. W4E-003C legt de
operationele resolutionpermission vast. W4E-003A levert vervolgens workerheartbeat,
typed queue/mail/storage-readiness, durable transport-startmarkering, veilig pre-send
leaseherstel, append-only OutcomeUnknown-resolution en CLI-health. Daarmee is de
operationspredecessor voor W4E-004 gereed; Web-delivery zelf blijft W4E-004-scope.

W4E-003C levert `DELIVERY.OUTCOME_RESOLVE` via de smalle canonieke
`DELIVERY_OPERATOR`-rol, zonder automatische membershipassignment. Daarmee zijn de
deployment- en authorizationpredecessors voor hervatting van W4E-003A expliciet.

W4E-004 integreert dit in Quotation-, Invoice- en Creditdetails: queued initial/resend,
private PDF-download, escaped deliveryhistory, readinessfeedback en minimale
permission-scoped OutcomeUnknown-resolution. Quotation `Sent` volgt niet langer uit de
legacy klik maar uitsluitend uit transportacceptatie of HandledExternally, met veilige
schedulerreconciliation. Invoice-/Creditdelivery laat financiële waarheid ongemoeid.

W4E-005 heeft de capability end-to-end gereviewd en de volledige regressie-, security-,
tenant-, concurrency- en operationsmatrix gevalideerd. De review heeft document-
readiness gelijkgetrokken met de bestaande typed render-modelvalidatie, zodat Web nooit
`Ready` toont wanneer artifactpreparation inhoudelijk zou blokkeren. W4E is compleet en
merge-ready. Deferred blijven bounce/delivery-webhooks, inboxbevestiging, open/click-
tracking, tenant SMTP-credentials, template-editing, arbitrary attachments, bulk of
scheduled delivery, membership-rolebeheer, generieke mutation-audit en optimistic
settings locking.

- W4E-000 – Sales Document Delivery Design
- W4E-001A – Quotation Document Address Semantics
- W4E-001 – Recipient, Issuer & Sender Readiness
- W4E-002 – Immutable Sales Render Models, PDF & Artifact Persistence
- W4E-003 – Durable Delivery Requests, Outbox & Mail Transport
- W4E-003B – Production Runtime & Worker Deployment Design
- W4E-003C – Delivery Operations Authorization
- W4E-003A – Delivery Operations Readiness
- W4E-004 – Quotation, Invoice & Credit Delivery Web Flows
- W4E-005 – Sales Document Delivery Review & Regression

W4E-001 is na de afzonderlijke address-predecessor W4E-001A gereed: recipientpurposes,
Administration-owned issuer/payment/sender-masterdata en typed readiness readers zijn
beschikbaar zonder first-contact-, address- of senderheuristiek. W4E-002 is eveneens
gereed: typed immutable render models, gepinde Chromium/Puppeteer-rendering, private
immutable artifacts, concrete same-tenant source-FK's, canonical fingerprints,
SHA-256-integriteit en concurrency-safe reuse bestaan voor Quotation, SalesInvoice en
SalesCreditInvoice. Er is nog geen Web-download, mail, deliveryrequest, outbox of
queue-delivery; dat begint bij W4E-003 en W4E-004.

Dutch VAT & ICP Reporting blijft tijdens W4E geparkeerd. W4E introduceert geen
Purchasing Web, generiek documentmanagement, e-invoicing of ontvangstbewijsclaim.

### Aanbevolen volgende hoofdproductbatch – Purchasing Persistence & Web

Na de operationele Sales-kern en W4E documentdelivery is Purchasing de grootste
productflow zonder persistence- en Webbediening. De volgende batch hoort daarom de
bestaande PurchaseInvoice- en PurchaseCreditInvoice-domeincontracten tenant-safe naar
persistence en Application/Web te brengen en de vereiste Accounting-configuratie
expliciet te ontwerpen. VAT/ICP-reporting blijft geparkeerd totdat deze primaire
inkoopflow en haar financiële afhankelijkheden productmatig beschikbaar zijn.

PROJECT-GAP-002 bevestigt dat Purchasing Domain-aggregates en in-memory fiscale
postingprototypes bestaan, maar persistence, Application-lifecycle, permissions,
postingconfiguration, Input TaxCodes, Payable-orchestratie en Web ontbreken. Supplier-
classificatie en W4D Journal/LedgerAccount-masterdata zijn bruikbare predecessors;
historische supplier/address snapshots, external supplier-invoice-numbering,
received/supply/fiscal-datebeleid en at-most-once postinglinkage moeten vóór duurzame
Purchase persistence worden uitgelijnd.

De exact aanbevolen volgende implementatiebatch is **P3 – Domestic Purchase Invoice
to Payable**: vijf sequentiële stories voor contractalignment, authorization en Purchase
postingconfiguration, PurchaseInvoice persistence/Application, transactionele domestic
Input-VAT-posting plus Payable OpenItem, en PurchaseInvoice Web/review. Purchase credits,
supplier payments, incoming attachments/OCR, non-EUR VAT-policy en internationale
reverse-charge purchases volgen afzonderlijk. Het huidige fiscale model kan
purchase-side reverse charge nog niet veilig als gelijktijdige verschuldigde Output VAT
en mogelijke Input VAT representeren. VAT/ICP-reporting blijft daarom geparkeerd.

P3-000 heeft de productcontracten definitief uitgelijnd. P3 gebruikt Draft → Finalized
→ Posted, bewaart finalization actor/tijd plus immutable supplier/address/date- en
line-fiscalsnapshots, gebruikt een case-sensitive externe supplier invoice identity en
leidt paymentstatus uitsluitend uit Payable af. Domestic V1 is EUR-only en ondersteunt
volledig aftrekbare Input standard/reduced plus zero/exempt/outside-scope fiscal truth;
non-/partial-deductible en international/reverse-charge blijven geblokkeerd.

De vervolgsplit blijft: P3-001 authorization, create-missing-only Input catalogue en
PurchasePostingConfiguration; P3-002 PurchaseInvoice persistence/Application;
P3-003 atomische domestic posting met TaxPostings en Payable; P3-004 Web plus review.
De designblockers voor start van P3-001 zijn opgelost. PurchaseCreditInvoice, payments,
attachments/OCR, FX-reporting en VAT/ICP blijven vervolgscope.

P3-001 levert typed Purchasing-authorization en canonieke rollen zonder automatische
membershiptoekenning, tenant-safe PurchasePostingConfiguration/readiness en een
productmatige create-missing-only domestic Input-TaxCode-catalogusactie in Beheer →
Instellingen. W4D blijft de productroute voor Purchase Journal en vereiste
LedgerAccounts. Internationale/reverse-charge en gedeeltelijk/niet-aftrekbare VAT zijn
niet gefaket en blijven vervolgscope. Daarmee kan P3-002 op de foundation starten.

P3-002 levert nu de duurzame tenant-safe PurchaseInvoice-header/regels, case-sensitive
externe supplier-invoice identity, immutable supplier/documentadres/account/Input-Tax-
snapshots, Draft-mutaties en actor/tijd-geregistreerde Finalize plus pre-post Cancel.
List/detail/selectors zijn Application-contracten zonder Web. Echte MySQL-concurrency
borgt duplicate create en double finalize. Er ontstaan nog geen JournalEntries,
TaxPostings, Payables/OpenItems of postinglinkages; P3-003 blijft exclusief verantwoordelijk
voor expliciete PostingDate, actuele PurchasePostingConfiguration en atomische posting.

P3-003 levert die atomische domestic posting nu via PostingEngine: Expense/Asset en
Input VAT debet, AP gross credit, immutable line-level Input TaxPostings op
FiscalReportingDate, één volledig open Payable/Credit met DueDate, en één tenant-safe
postinglinkage. Configuratie is alleen bij Post vereist. Sequential en concurrent double
post zijn idempotent en iedere persistencefout rolt JournalEntry, tax, OpenItem, linkage
en status gezamenlijk terug.

P3-004 sluit de domestic PurchaseInvoice-productflow af met permission-aware Weblijst en
-detail, expliciete Draft-aanmaak/-wijziging, Cancel, Finalize en Post met PostingDate.
Same-tenant Supplier-, Expense/Asset- en ondersteunde Input-TaxCode-selectors voeren de
bestaande Application-contracten; Posted detail toont het duurzame Payable/Credit
OpenItem. PurchaseCreditInvoice, supplier payments, attachments/OCR, international en
reverse-charge Purchase VAT, partial/non-deductible VAT, multi-step approval en VAT/ICP-
reporting blijven expliciet vervolgscope.

PROJECT-GAP-003 bevestigt P3 als complete domestic PurchaseInvoice → Payable-flow en
kiest exact **B2 – Bank Payments & Open Item Settlement** als volgende productbatch.
De grootste actuele accountinggap is cash settlement: Sales en Purchasing creëren
Receivable/Payable OpenItems, maar de gebruiker kan nog geen duurzame klantontvangst of
leveranciersbetaling boeken. B2 bouwt daarom eerst handmatige BankTransactions met
Payment-allocations, transactionele PostingEngine-boeking en append-only OpenItem-
settlements; CAMT.053/MT940/PSD2, automatische reconciliation en overpayment/suspense
blijven follow-up.

De volgorde daarna is Purchase Credits met uitsluitend volledige source-line reversals,
gevolgd door een International Purchase VAT predecessor voor multi-leg Input/Output-
truth, regime-/datumbeleid, deductibility en eventuele FX→EUR. VAT/ICP reporting blijft
geparkeerd totdat alle materiële Sales- en Purchase-fiscale source streams—including
credits, international/reverse-charge en import/correcties—duurzaam en rapporteerbaar
zijn.

PROJECT-GAP-004 bevestigt B2 als complete handmatige EUR-cashflow en kiest exact
**PC – Purchase Credits** als volgende implementatiebatch. Nu klantontvangsten en
leveranciersbetalingen duurzaam kunnen worden geboekt en gesetteld, is het zwaarste
correctnessgat de ontbrekende leverancierscreditnota. PC hergebruikt P3 immutable
source-/TaxPosting-truth, de bestaande full-reversalprototypes, Sales-creditpatterns en
het bewezen Payable/Debit-versus-Payable/Credit `OpenItemMatch`-model. De eerste batch
ondersteunt uitsluitend volledige source-line reversals; een reeds gedane betaling
blijft historische cashtruth en een creditsurplus blijft open supplier credit balance.

Definitieve split: PC-000 contractalignment; PC-001 authorization, persistence en
Application; PC-002 transactionele posting en Input-VAT-reversal; PC-003 matching en
Web; PC-004 review/regression. Daarna volgt de **International Purchase VAT
predecessor** voor multi-leg Output/Input truth, deductibility, regime-/datumbeleid en
EUR-conversie. **Bank Import & Reconciliation** staat derde: CAMT.053-first via een
afzonderlijk BankStatement/StatementLine-provenancemodel, gevolgd door user-confirmed
promotie naar de bestaande B2 BankTransaction-flow. Het levert hoge automationwaarde,
maar B2 biedt al een correcte handmatige fallback.

Payment reversal, partial credits, accounting/fiscal periods, attachments/OCR en
generic audit blijven expliciete debt. VAT/ICP reporting blijft geparkeerd totdat alle
materiële domestic/international Sales- en Purchase-originals én credits duurzaam als
Original/Reversal-TaxPostings bestaan, reverse-charge/import/deductibility en
EUR-conversie zijn ontworpen en fiscale perioden/locks plus afrondingsbeleid gereed
zijn.

PC-000 heeft de PurchaseCredit-contracten definitief uitgelijnd. Domestic EUR V1
vereist exact één Posted same-supplier source PurchaseInvoice en ondersteunt één of
meer volledige source-line reversals uit uitsluitend die invoice. De credit gebruikt
een eigen externe suppliercreditidentity en bewaart source snapshots; historische
Expense/Asset-, Input-VAT- en AP-rekeningen komen uit de werkelijk geboekte source
facts. Post creëert Input/Reversal TaxPostings en een Payable/Debit, die atomisch tot
het actuele source-openbedrag wordt gematcht. Een betaald deel blijft cashtruth en het
creditsurplus blijft open supplier credit balance.

De bestaande schemafundamenten leveren alle kritieke historische truth en locking;
er is geen designpredecessor nodig. De split PC-001 authorization/persistence/
Application, PC-002 transactionele posting/tax reversal, PC-003 matching/Web en PC-004
review/regression blijft definitief. Partial credits, payment reversal, attachments/OCR,
International Purchase VAT, Bank Import en VAT/ICP reporting blijven deferred.

PC-001 levert nu authorization, tenant-safe aggregate/persistence en de
Draft/Finalize/Cancel Application-laag zonder financiële side effects. PC-002 levert
de transactionele historische journal/net/VAT/AP-reversal, posted audit, unieke
bronregelclaims, een Payable/Debit zonder due date en het PC-003-postingreadmodel.
PC-003 levert de automatische, payment-race-safe source matching en de volledige
permission-scoped PurchaseCredit Webflow. PC-004 heeft de afsluitende review en brede
regressie groen afgerond; de PC-batch is merge-ready. Partial credits, refunds en de
overige expliciet uitgestelde capabilities blijven deferred.

PROJECT-GAP-005 bevestigt PC als **complete, gemergede en handmatig geaccepteerde**
domestic PurchaseCredit-flow. De acceptance `TEST-INK-002 → BET-TEST-INK-002 →
TEST-CREDIT-001` bewijst dat een volledig betaalde PurchaseInvoice open EUR 0 blijft,
dat de historische EUR 121 cashsettlement intact blijft en dat de later geposte EUR 121
PurchaseCredit zonder zero-match als open `Payable/Debit` supplier credit balance
resteert.

Eén kleine regressiefix gaat vooraf aan de volgende batch: de PurchaseCredit-
successmelding moet via het bestaande postingreadmodel onderscheid maken tussen
volledig gematcht, gedeeltelijk gematcht en EUR 0 gematcht. De huidige generieke tekst
is bij EUR 0 misleidende financiële presentatie. Deze fix wijzigt geen accountingtruth.

De exact volgende hoofdproductbatch is **B3 – Bank Payment Reversal**. B2 kan immutable
BankTransactions, JournalEntries en Applied Settlements posten, maar heeft geen veilige
productflow om een foutieve handmatige cashboeking te corrigeren. B3 voegt daarom exact
één volledige, append-only reversal per Posted manual BankTransaction toe: een exact
gespiegelde contra-JournalEntry via PostingEngine, Reversal Settlements voor alle
originele allocations, immutable one-to-one linkage, actor/reason/tijd, onafhankelijke
authorization, tenantlocks, Webflow en concurrentie-/rollbacktests. Partial reversal,
mutatie van originals, refund als nieuwe bankmovement, import, FX en period management
blijven buiten B3.

Definitieve B3-split na contractalignment: B3-000 contractalignment; B3-001 independent
authorization plus reversal- en settlementlinkage-persistence; B3-002 één atomische
historische contra-JournalEntry plus alle append-only settlementreversals; B3-003
permission-scoped Webflow plus echte MySQL-concurrency; B3-004 review, regressie en
development acceptance. De original blijft immutable en status `Posted`; list/detail
toont `Teruggedraaid` afgeleid. De correctie vereist een expliciete postingdatum,
verplichte reason en actor/tijd, gebruikt exact de historische Journal/LedgerAccounts,
raakt geen TaxPosting of OpenItemMatch en ondersteunt geen partial/importreversal.
Vóór acceptance wordt voor
`dev-admin@financecore.local` expliciet gecontroleerd: required/effective permission,
de aparte least-privilege `BANKING_REVERSAL_OPERATOR`-role/assignment zonder duplicate,
navigation/actie, Bank Journal, Bank
LedgerAccount, AdministrationBankAccount, BankingPostingConfiguration-readiness en een
concrete Posted Payment-fixture. Dit is developmentbeleid en geen automatische
productie-role assignment.

B3-001 levert inmiddels de onafhankelijke Reverse-permission en least-privilege role
zonder auto-assignment, de tenant-safe immutable reversal- en settlementlinkages en de
typed historical source/readinesscontracten. Allocation → Applied Settlement en
BankTransaction → posting → JournalEntry/lines zijn exact traceerbaar; inactive
historical Journal/accounts, latere settlements en matches blijven geldige state. Er
zijn nog geen contra-entry of settlementreversals gemaakt. Daarmee is B3-002 voor de
atomische full reversal zonder predecessor gedeblokkeerd.

B3-002 levert nu die atomische full reversal: exact gespiegeld historical contra-journal
via PostingEngine, append-only settlementreversals en volledige Banking-linkage/audit in
één rollbackveilige transaction. Originals blijven Posted en immutable; TaxPosting,
OpenItemMatch en unrelated cashfacts blijven onaangeraakt. Sequential en concurrent
double reversal plus payment-/creditmatchraces zijn MySQL-geserialiseerd. Het typed
Success-readmodel en de bestaande readiness-reader deblokkeren B3-003 voor uitsluitend
de permission-scoped Webflow.

B3-003 levert nu die Webflow onder Bank → Betalingen: tenant-scoped list/detail tonen
afgeleid `Teruggedraaid`, beide JournalEntries, settlementreversals en resulterende open
bedragen. View en Reverse blijven onafhankelijk; een read-only confirmation verzamelt
expliciete postingdatum/reason en uitsluitend POST roept het B3-002-command aan. Web
voegt geen locks of accountingsemantiek toe. De productflow en MySQL-concurrency-
regressies zijn gereed voor B3-004; manual acceptance vereist eerst de expliciete
development-assignment van `BANKING_REVERSAL_OPERATOR`.

De eerdere post-PC-prioriteit verandert door nieuwe acceptance-evidence: Payment
Reversal is nu de directe correctnessprioriteit; daarna volgen **Accounting Periods &
Posting Locks**, vervolgens de **International Purchase VAT predecessor**, daarna
**Bank Import & Reconciliation**. Incoming purchase documents, Purchase Orders,
Reporting Web, opening balance/migration en operations hardening blijven expliciete
follow-ups volgens productionbehoefte. VAT/ICP reporting blijft geparkeerd totdat
international/reverse-charge/import/deductibility, complete correcties, fiscale
perioden/locks, rounding/reconciliation en EUR-conversie duurzaam zijn opgelost.

PROJECT-GAP-006 bevestigt na de gemergede B3-implementatie, handmatige B3-acceptance en
de same-date settlement-orderingcorrectnessfix de eerdere follow-upvolgorde. B3 heeft
de concrete bankreversalgap gesloten; de breedste resterende correctness- en
production-safetygap is nu dat alle bestaande posting- en reversalflows een
`PostingDate` zonder duurzame BookYear/AccountingPeriod of centrale lockguard kunnen
verwerken.

De exact volgende hoofdproductbatch is daarom **AP – Accounting Periods & Posting
Locks**. V1 modelleert Administration-owned BookYears met dekkende AccountingPeriods en
alleen `Open`/`Closed`; `SoftClosed` wordt niet zonder bewezen uitzonderingsworkflow
ingevoerd. Een expliciete, audited reopen met verplichte reden zet Closed terug naar
Open. De authoritative guard gebruikt de uiteindelijke Accounting `PostingDate` en
geldt voor SalesInvoice Post, SalesCredit Post, PurchaseInvoice Post, PurchaseCredit
Post, BankTransaction Post en BankTransaction Reversal. Toekomstige manual journals en
opening balances gebruiken dezelfde grens.

Accounting locks en Fiscal locks blijven afzonderlijk. `FiscalReportingDate`, fiscale
perioden, returnfinalisatie en suppletiebeleid worden niet stilzwijgend in
AccountingPeriod opgenomen. AP V1 levert uitsluitend accounting posting locks.

Definitieve voorgestelde split: AP-000 contractalignment; AP-001 authorization en
persistence; AP-002 transactionele PostingDate-lock enforcement over alle bestaande
mutationpaden; AP-003 permission-scoped periodmanagement-Webflow; AP-004 review,
development readiness en regressie. Development acceptance vereist expliciete
permissions/canonical roles voor `dev-admin@financecore.local`, BookYear-/period-
masterdata en een open → close → blocked post → andere open period → reopen → allowed
post-scenario met zichtbare audit. Er is geen production auto-assignment of impliciete
destructieve year rollover.

Na AP volgt de **International Purchase VAT predecessor**, daarna **Bank Import &
Reconciliation**. Opening Balance/Migration en Backup/Restore hardening blijven
production-critical follow-ups volgens de concrete go-liveplanning; Backup/Restore is
een afzonderlijke operations gate. VAT/ICP reporting blijft geparkeerd totdat
international/multi-leg Purchase VAT, deductibility, fiscale perioden/locks, complete
correcties, rounding/reconciliation en EUR-conversie duurzaam zijn opgelost.

AP-000 heeft de periode- en lockcontracten definitief uitgelijnd. `BookYear` is de
Administration-owned Accounting-root met immutable code/start/eind; BookYears
overlappen niet, maar mogen gaps hebben waarin posting fail-closed `NoPeriod` oplevert.
BookYear bezit custom `AccountingPeriod`-children die samen het hele jaar zonder gaps of
overlap dekken. Perioden hebben in V1 exact `Open`/`Closed`; `SoftClosed` en een aparte
BookYear-close zijn deferred. Close en Reopen vereisen reason/actor/authoritative tijd,
bewaren current status plus append-only history en muteren geen financiële facts.

De AP-002-guard gebruikt uitsluitend de PostingDate die werkelijk naar PostingEngine
gaat. SalesInvoice en SalesCredit leiden die momenteel af uit hun immutable documentdatum;
PurchaseInvoice, PurchaseCredit, BankTransaction Post en BankTransaction Reversal
ontvangen haar expliciet. Iedere duurzame flow checkt en shared-lockt exact één period
binnen dezelfde outer financial transaction. Close gebruikt een exclusive lock op
dezelfde row: posting vóór close is geldig, close vóór posting levert typed Closed met
nul writes. NoPeriod en ambiguity/integrity failure zijn eveneens fail-closed. Web of
preflight is nooit authoritative.

Bestaande JournalEntries worden niet herschreven of automatisch aan perioden gekoppeld.
AP-001 is afgerond met migration `000057`, additive tenant-safe persistence, expliciete
BookYear-/periodsetup,
readiness over bestaande PostingDates, de vier onafhankelijke Period-permissions en
least-privilege manager/reopenerroles zonder production auto-assignment. AP-002 is
afgerond met één centrale typed guard en shared periodrow-lock enforcement in SalesInvoice,
SalesCredit, PurchaseInvoice, PurchaseCredit, BankTransaction Post en BankTransaction
Reversal. AP-003 is afgerond met de permission-scoped management-Webflow onder Beheer →
Grootboek → Perioden: tenant-safe list/create, label-only edit, expliciete custom
periodsetup, authoritative readiness, POST-only Close/Reopen en ordered audit history.
Create is insert-only, zodat een duplicate tenant-code geen bestaand BookYear-label meer
kan overschrijven en typed IntegrityFailure oplevert. Er is geen automatische generatie
of bootstrap. AP-004 heeft de gezamenlijke model-, authorization-, six-flow-, concurrency-,
Web/security- en regressiereview groen afgerond. De handmatige developmentacceptance is
PASS: BookYear 2026 bevat twaalf dekkende maandperioden; augustus doorliep audited Open →
Closed → Open; een gesloten augustusposting werd zonder financiële writes geweigerd, een
septemberposting was succesvol en na Reopen was een augustusposting succesvol. Beide
geslaagde testobjects hebben exact één JournalEntry, TaxPosting en Payable/OpenItem van
bruto EUR 12,10. Database-integriteit en volledige validatie zijn groen; AP is
merge-ready. Er is geen afzonderlijke historische-data-predecessor. De canonical
AP-rollen zijn voor manual acceptance uitsluitend als developmentdata expliciet
toegewezen. AP-003R voegt vóór de eerste
Close/history een atomic, permission-scoped replacement van een Open setupplan toe,
zodat een foutieve jaarperiod na validatie expliciet door maandperioden kan worden
vervangen zonder financiële facts te wijzigen. De handmatige replacement blijft een
acceptancestap en gebeurt niet automatisch. Er wordt geen
perioddata automatisch gebackfilled. Accounting PostingDate-locks blijven strikt
gescheiden van toekomstige FiscalReportingDate/VAT-filinglocks.

Na AP blijft de aanbevolen volgende productstap de **International Purchase VAT
predecessor**, gevolgd door **Bank Import & Reconciliation**. `SoftClosed`, zelfstandige
BookYear-close, fiscale/VAT filing locks, arbitrary JournalEntry period UI en opening-
balance/year-rolloverautomatisering blijven expliciet deferred; VAT/ICP reporting blijft
geparkeerd.

PROJECT-GAP-007 bevestigt dat de predecessor niet direct een international-taxfeature is,
maar eerst **IPV-000 – Tax Treatment & Multi-Leg Architecture Alignment**. De huidige
Purchase-keten is single-leg Input VAT: één line/TaxCode levert één Input TaxPosting en
suppliergross bevat die tax. EU acquisitions en algemene EU/non-EU B2B-services vereisen
daarentegen correlated VAT-payable en mogelijk beperkte deductible Input-legs, terwijl
supplier Payable het factuurbedrag zonder reverse-charge VAT blijft. IPV-000 legt daarom
TaxCode/treatmentselectie, multi-leg calculation, deductibility en non-deductible cost,
reportingclassificatie, VAT-payable accountrol, fiscal-datepolicy, complete historische
PurchaseCredit-reversal en additive legacycompatibiliteit vast.

International Purchase VAT V1 wordt daarna beperkt tot EU-goederen die aantoonbaar in
Nederland aankomen, general-rule EU B2B-services en general-rule non-EU B2B-services,
EUR-only. Importgoods/Artikel 23 blijft deferred tot een afzonderlijk customs/import-
sourcefactscontract; foreign VAT wordt typed geblokkeerd en nooit als Nederlandse Input
VAT behandeld. Domestic reverse charge gebruikt later dezelfde architectuur maar valt
buiten deze International V1. Voorgestelde uitvoering: IPV-001 persistence/config/
calculation, IPV-002 Purchase posting, IPV-003 historical credit reversal/Web en IPV-004
review/manual acceptance/regressie. VAT-return Web en fiscal filing locks blijven
geparkeerd.

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
