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

Een PurchaseInvoice doorloopt `Draft → Finalized → Posted → Paid`; een PurchaseCreditInvoice doorloopt `Draft → Finalized → Posted`. Beide kunnen vanuit Draft of Finalized worden geannuleerd. Minimaal één eigen regel is vereist vóór finalisatie en regelmutaties zijn daarna geblokkeerd.

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
basis. Production worker supervision, health/readiness en operationele failed-job/
OutcomeUnknown-resolutie blijven deploymentblockers vóór live Web-delivery in W4E-004.

W4E-003B kiest een portable single-host production Docker Compose-runtime met database-
queue, bewaakte worker/scheduler, durable MySQL/artifactvolumes en applicationheartbeat.
W4E-003C legt vervolgens de operationele resolutionpermission vast; daarna hervat
W4E-003A readiness/recovery. W4E-004 blijft tot voltooiing van W4E-003A geblokkeerd.

W4E-003C levert `DELIVERY.OUTCOME_RESOLVE` via de smalle canonieke
`DELIVERY_OPERATOR`-rol, zonder automatische membershipassignment. Daarmee zijn de
deployment- en authorizationpredecessors voor hervatting van W4E-003A expliciet.

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
