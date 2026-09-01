# Finance Core Platform Domain Model

Dit document definieert de eerste ubiquitous language van Finance Core Platform. Het beschrijft uitsluitend zelfstandige domeinconcepten; relaties, opslagmodellen en technische implementaties vallen buiten deze versie.

## Business Invariants

### Administration

- Heeft altijd precies één `AdministrationId`, één `AdministrationCode`, één `AdministrationName` en één `BaseCurrency`.
- Heeft maximaal één `Organisation`.
- Identiteit en AdministrationCode veranderen nooit.
- BaseCurrency mag alleen wijzigen zolang geen boekhoudkundige transacties bestaan.
- `Active` en `Inactive` zijn de enige geldige statussen.
- `attachOrganisation()` accepteert maximaal één Organisation en vervangt een bestaande koppeling nooit stilzwijgend.
- `removeOrganisation()`, `activate()` en `deactivate()` zijn idempotent.

### Organisation

- Bestaat uitsluitend binnen een Administration en is geen Aggregate Root.
- Heeft altijd precies één `OrganisationId`.
- DisplayName is verplicht en LegalName is optioneel.
- Overige juridische gegevens zijn optioneel.
- `Address`, `ChamberOfCommerceNumber`, `VatNumber`, `Iban` en `Bic` worden in toekomstige stories als afzonderlijke value objects gemodelleerd.

## 1. Core Concepts

| Naam | Doel | Korte beschrijving |
| --- | --- | --- |
| Administration | Financiële activiteiten afbakenen | De zelfstandige administratieve eenheid waarbinnen financiële gegevens worden gevoerd. |
| AdministrationId | Een Administration uniek identificeren | De immutable domeinidentiteit van een Administration. |
| Party | Een zakelijke of persoonlijke deelnemer benoemen | Een persoon of organisatie die binnen het financiële domein optreedt. |
| Money | Een geldbedrag eenduidig uitdrukken | Een bedrag met een valuta, bedoeld voor financiële berekeningen. |
| Address | Een fysiek of postaal adres vastleggen | Een gevalideerde adresrepresentatie voor domeingebruik. |

## 2. Master Data

| Naam | Doel | Korte beschrijving |
| --- | --- | --- |
| Customer | Een afnemer administreren | Een partij aan wie goederen of diensten worden aangeboden of geleverd. |
| Supplier | Een leverancier administreren | Een partij die goederen of diensten aanlevert. |
| Product | Een verkoopbaar of inkoopbaar artikel beschrijven | Een tastbaar aanbod met een herkenbare commerciële identiteit. |
| Service | Een verkoopbare of inkoopbare prestatie beschrijven | Een niet-tastbaar aanbod dat als prestatie wordt geleverd. |
| Currency | Een munteenheid benoemen | De valuta waarin bedragen worden uitgedrukt. |
| PaymentTerm | Een betalingstermijn standaardiseren | Een commerciële afspraak over het moment waarop betaling verschuldigd is. |

## 3. Commercial

| Naam | Doel | Korte beschrijving |
| --- | --- | --- |
| Quotation | Een commercieel voorstel vastleggen | Een aanbod van goederen of diensten onder bepaalde voorwaarden. |
| Order | Een geaccepteerde verkoopopdracht vastleggen | Een opdracht voor te leveren goederen of diensten. |
| SalesInvoice | Een verkoopvordering formaliseren | Een financieel document waarmee een geleverd bedrag in rekening wordt gebracht. |
| PurchaseInvoice | Een inkoopverplichting vastleggen | Een ontvangen financieel document voor geleverde goederen of diensten. |
| CreditNote | Een eerder gefactureerd bedrag corrigeren | Een commercieel document dat een factuurbedrag geheel of gedeeltelijk vermindert. |
| Payment | De voldoening van een financieel bedrag representeren | Een verrichte of ontvangen betaling met een eigen status en bedrag. |

### Sales Capability

#### Aggregates en child entities

| Aggregate Root | Child Entity | Verantwoordelijkheid |
| --- | --- | --- |
| Quotation | QuotationLine | Een commercieel aanbod en de aangeboden regels beheren. |
| Order | OrderLine | Een directe of uit een offerte ontstane verkoopopdracht beheren. |
| SalesInvoice | SalesInvoiceLine | Een verkoopfactuur en haar factureerbare regels beheren. |
| SalesCreditInvoice | SalesCreditInvoiceLine | Een correctie op eerder gefactureerde verkoop beheren. |

Sales-documentoutput voegt één immutable evidence-object toe: `DocumentArtifact`.
Het artifact bevat metadata, tenantownership, concrete Sales-bronsoort/-identiteit,
monotone artifactversion, semantic renderfingerprint en SHA-256 van de private PDF;
binary data hoort niet in Domain of database. Aparte linktabellen dwingen harde
same-tenant FK's naar Quotation, SalesInvoice en SalesCreditInvoice af. Het artifact
muteert geen bronaggregate, is geen child dat de bron beheert en bewijst op zichzelf
geen verzending. Exact dezelfde source+renderinput mag hetzelfde integere ArtifactId
hergebruiken; gewijzigde zichtbare input creëert een nieuwe immutable artifactversie.

Een nieuwe Quotation legt naast de customersnapshot exact één expliciet geselecteerd,
actief same-Relation adres met doel `Quotation` als immutable documentsnapshot vast.
Andere adresdoelen en adresvolgorde zijn geen fallback. Bestaande legacy Quotations
zonder deze snapshot blijven hydrateerbaar; Draft-editing ververst de snapshot niet.

Relation bewaart per Sales-documentpurpose maximaal één preferred Contact-recipient via
same-tenant persistence. Administration is eigenaar van current issuerpresentation,
paymentdata en afzonderlijke mail-senderidentity. Artifactgeneratie snapshot deze
current presentationdata; SalesInvoice/Credit historical fiscal snapshots worden nooit
door current Administration-data vervangen.

#### Workflows

Een Quotation doorloopt een van de volgende paden:

```text
Draft → Sent → Accepted
Draft → Sent → Rejected
```

Accepted is een eindstatus van Quotation. De Application-laag kan op basis van een geaccepteerde Quotation een Order aanmaken. `OrderCreated` is daarbij een event van het ontstane Order, geen QuotationStatus of Quotation-overgang.

Een Order doorloopt:

```text
Draft → Confirmed → PartiallyInvoiced → FullyInvoiced
```

Een SalesInvoice doorloopt:

```text
Draft → Finalized → Posted → Paid
```

Sales-documentdelivery is een afzonderlijke immutable workflow. De Weblaag maakt een
caller-owned DeliveryRequest met exact artifact en recipient/sendersnapshots; attempts
en OutcomeUnknown-resolutions zijn append-only. `AcceptedByTransport` of expliciet
`HandledExternally` coördineert idempotent Quotation Draft→Sent. Failure,
OutcomeUnknown en AuthorizeResend muteren de Quotation niet. Invoice-/Creditdelivery
heeft nooit financiële side-effects. Private artifactdownload en history vereisen
Sales View; sendrechten blijven per documenttype onafhankelijk.

Een SalesCreditInvoice doorloopt:

```text
Draft → Finalized → Posted
Draft → Cancelled
Finalized → Cancelled
```

Order kan vanuit Draft of Confirmed worden geannuleerd. SalesInvoice kan vanuit Draft of Finalized worden geannuleerd. De eindstatussen en aanvullende Quotation-paden zijn volledig beschreven in `app/Domain/Sales/README.md`.

#### Businessregels

- Iedere Quotation bevat minimaal één QuotationLine.
- Iedere Order bevat minimaal één OrderLine voordat deze wordt bevestigd.
- Iedere SalesInvoice en SalesCreditInvoice bevat minimaal één eigen regel voordat deze wordt gefinaliseerd.
- Documentregels zijn eigendom van hun Aggregate Root en kunnen na de relevante definitieve status niet meer worden gewijzigd.
- Line totals gebruiken centrale exacte Money-vermenigvuldiging met decimale strings en zonder floats.
- Een Order ontstaat uit een geaccepteerde Quotation of wordt direct aangemaakt.
- Een SalesInvoice ontstaat uit een Order.
- SalesInvoice en SalesCreditInvoice krijgen hun definitieve nummer uit NumberSequence.
- Boekhoudkundige boekingen volgen later via de Posting Engine.

#### Domain Events

- `QuotationCreated`
- `QuotationSent`
- `QuotationAccepted`
- `QuotationRejected`
- `OrderCreated`
- `OrderConfirmed`
- `SalesInvoiceCreated`
- `SalesInvoiceFinalized`
- `SalesInvoicePosted`
- `SalesInvoicePaid`

#### Architectuurregels

- Aggregates wijzigen elkaars status nooit rechtstreeks.
- Statusovergangen verlopen uitsluitend via domeinmethoden van het betreffende aggregate.
- Orchestratie tussen aggregates vindt later plaats in de Application-laag.
- Btw behoort tot Tax; Posting Engine en openstaande posten behoren tot Accounting; betalingen behoren tot Banking.
- Sales bevat geen Laravel-, database-, repository- of infrastructuurafhankelijkheden.
- Shared Finance heeft geen afhankelijkheid op Sales.

**Capabilitystatus:** Completed for first domain iteration.

### Purchasing Capability

#### Aggregates en child entities

| Aggregate Root | Child Entity | Verantwoordelijkheid |
| --- | --- | --- |
| PurchaseInvoice | PurchaseInvoiceLine | Een ontvangen leveranciersfactuur en haar inkoopregels beheren. |
| PurchaseCreditInvoice | PurchaseCreditInvoiceLine | Een ontvangen leverancierscreditfactuur en haar correctieregels beheren. |

#### Workflow

Een PurchaseInvoice doorloopt:

```text
Draft → Finalized → Posted
```

Een PurchaseCreditInvoice doorloopt:

```text
Draft → Finalized → Posted
```

Beide documenten kunnen vanuit Draft of Finalized worden geannuleerd. Statusovergangen verlopen uitsluitend via domeinmethoden van het betreffende aggregate.

#### Businessregels

- Iedere PurchaseInvoice en PurchaseCreditInvoice heeft een leverancier.
- Iedere PurchaseInvoice en PurchaseCreditInvoice bevat minimaal één eigen regel voordat deze wordt gefinaliseerd.
- P3 PurchaseInvoice gebruikt het verplichte externe supplier invoice number; Administration, Supplier en het case-sensitive canonieke nummer zijn samen uniek. Er bestaat geen interne Purchase-numbersequence.
- P3 finaliseert met immutable Domain UserId en timestamp. Finalized bevriest supplier/address/date-, line/account-, TaxCode- en bedragtruth maar maakt nog geen financiële feiten.
- P3 bewaart SupplierInvoiceDate, ReceivedDate, nullable SupplyDate, een afzonderlijke FiscalReportingDate en later de expliciete Accounting PostingDate. Supplier-, VAT/jurisdiction- en documentaddressdata worden immutable gesnapshot; live Relationdata is geen historische waarheid.
- P3 ondersteunt uitsluitend EUR en volledig aftrekbare domestic Input VAT. Standard/reduced kunnen positieve tax hebben; zero/exempt/outside-scope bewaren zero-tax fiscal truth zonder VAT-journalregel. International/reverse-charge en non-/partial-deductible VAT zijn uitgesloten.
- Iedere P3 line kiest expliciet een active same-tenant Expense/Asset-account en active same-tenant Input TaxCode. Er is geen first-account-, first-TaxCode- of rateheuristiek.
- `PostingEngine` verwerkt alle financiële mutaties.
- PurchaseInvoice en PurchaseCreditInvoice maken nooit zelf JournalEntries.
- P3-posting bewaart atomair JournalEntry, Input TaxPostings, één Payable/Credit OpenItem, append-only linkage en Posted-status. Paymentstatus wordt uit OpenItem afgeleid; PurchaseInvoice heeft geen handmatige Paid/PartiallyPaid-status.
- Na het posten ontstaat via Accounting een OpenItem; Purchasing maakt of beheert settlement/matching niet rechtstreeks.
- PC V1 definieert PurchaseCreditInvoice als ontvangen leverancierscreditnota tegen exact één same-tenant/same-supplier Posted PurchaseInvoice. Zij selecteert één of meer volledige source PurchaseInvoiceLines uit uitsluitend die invoice; partial line amounts/quantities/tax en source-less credits zijn niet toegestaan.
- Iedere PurchaseCreditLine heeft duurzame source-line linkage. Een source line kan maximaal eenmaal gepost volledig worden gecrediteerd; een bij Post geappende unieke reversal-claim en de unieke Original→Reversal TaxPosting-link bewaken dit ook onder concurrency zonder Draft/Cancelled-selecties permanent te laten claimen.
- PurchaseCredit gebruikt de historische supplier/address/account/tax snapshots en de daadwerkelijk geboekte source JournalEntry-/TaxPosting-/Payable-rekeningen. De huidige Supplier, TaxCode of PurchasePostingConfiguration mag reversaltruth niet herinterpreteren.
- PurchaseCredit-posting maakt een positieve Payable/Debit en matcht die atomisch via OpenItemMatch tot het actuele open source Payable/Credit-bedrag. Bestaande OpenItemSettlements/cash blijven staan; een remainder is een open supplier credit balance.
- `PurchasePostingConfiguration` is Administration-owned en verwijst met harde tenant-FK's naar exact een active Purchase Journal, active Liability/AP en active Asset/Input VAT. De typed reader kent Missing, Success en exacte InvalidReference; er bestaat geen default Expense-account of heuristische replacement.
- De domestic Input-catalogus bevat uitsluitend typed Input standard/reduced/zero/exempt/outside-scope codes en wordt expliciet create-missing-only via Administration-settings beschikbaar gemaakt. TaxCodekeuze blijft later line-owned.

#### Hergebruik

- `Money` en `Currency` worden uit Shared Finance hergebruikt.
- `Quantity` en `LineDescription` worden uit Shared Commerce hergebruikt en niet in Purchasing gedupliceerd.
- `PostingRequest`, `PostingValidation` en `PostingEngine` blijven eigendom van Accounting en staan buiten Purchasing.

#### Architectuurregels

- Purchasing dupliceert geen Sales-aggregates of Sales-gedrag.
- PurchaseInvoice erft niet van SalesInvoice; PurchaseCreditInvoice erft niet van SalesCreditInvoice.
- Purchasing beheert eigen aggregates en child entities met eigen ubiquitous language.
- Child entities worden uitsluitend via hun eigen Purchasing Aggregate Root beheerd.
- Financiële gevolgen lopen uitsluitend via Accounting; Purchasing bevat zelf geen `PostingRequest` of `PostingEngine`.
- Purchasing bevat geen Laravel-, database-, repository- of infrastructuurafhankelijkheden.

**Capabilitystatus:** Completed for first domain iteration.

## 4. Accounting

| Naam | Doel | Korte beschrijving |
| --- | --- | --- |
| LedgerAccount | Financiële waarden classificeren | Een grootboekrekening waarop boekhoudkundige mutaties worden geclassificeerd. |
| Journal | Boekingen naar aard groeperen | Een dagboek voor een herkenbare categorie financiële gebeurtenissen. |
| JournalEntry | Een boekhoudkundige gebeurtenis vastleggen | Een gebalanceerde boeking met een datum, omschrijving en status. |
| JournalEntryLine | Eén boekingsregel representeren | Een debet- of creditbedrag binnen een journaalpost. |
| BookYear | Een accountingjaar afbakenen | Een Administration-owned expliciete inclusieve datumrange met tenant-unieke code. |
| AccountingPeriod | Een boekingsperiode beheersen | Een BookYear-owned afgebakend tijdvak met actuele Open/Closed-status en append-only statushistorie. |
| OpenItem | Een nog te vereffenen bedrag bewaken | Een financieel bedrag dat nog geheel of gedeeltelijk openstaat. |

### Accounting Capability

#### Aggregates en child entities

| Aggregate Root | Child Entity | Verantwoordelijkheid |
| --- | --- | --- |
| LedgerAccount | — | De classificatie van boekingsregels binnen het grootboek beheren. |
| Journal | — | Journaalposten naar de aard van hun financiële gebeurtenis groeperen. |
| BookYear | AccountingPeriod, PeriodStatusHistory | Boekjaargrenzen, volledige periodedekking en audited Close/Reopen beheren. |
| JournalEntry | JournalEntryLine | Een gebalanceerde financiële mutatie en haar debet- en creditregels beheren. |
| OpenItem | OpenItemSettlement | Een uit een geposte verkoop- of inkoopboeking ontstaan openstaand bedrag en de append-only vereffeninghistorie beheren. |

#### Shared Value Objects

- `Money` representeert een bedrag in een valuta.
- `Currency` representeert de valuta van een bedrag.
- Accounting gebruikt deze gedeelde value objects en definieert er geen capabilityspecifieke varianten van.

#### Domain Service

`PostingEngine` verwerkt alle financiële mutaties. De service valideert een aangeleverde postingopdracht, maakt als enige component een `JournalEntry` aan en post deze uitsluitend wanneer de journaalpost in balans is.

#### Application Services

- `PostingRequest` beschrijft de door een factuur, betaling of banktransactie aangeleverde opdracht aan de `PostingEngine`.
- `PostingResult` beschrijft de uitkomst van de verwerking door de `PostingEngine`.

`PostingRequest` en `PostingResult` vormen de bestaande frameworkonafhankelijke input- en outputcontracten van PostingEngine.

`AccountingPeriodPostingGuard` is de frameworkonafhankelijke Application-port die
binnen een financiële transaction voor AdministrationId + PostingDate exact `Open`,
`Closed`, `NoPeriod` of `IntegrityFailure` oplevert en de gevonden periode shared-lockt.

#### Businessregels

- Iedere JournalEntry bevat minimaal twee JournalEntryLines.
- Het totale debetbedrag van een JournalEntry is gelijk aan het totale creditbedrag.
- Een JournalEntry kan alleen worden gepost wanneer deze in balans is.
- Geposte JournalEntries zijn onveranderlijk.
- Correcties op geposte JournalEntries gebeuren via tegenboekingen, nooit door de oorspronkelijke boeking te wijzigen.
- OpenItems ontstaan pas na de succesvolle geposte JournalEntry voor een verkoop- of inkoopfactuur; `openedOn` is diens PostingDate.
- OpenItem bewaart naast `OpenItemType` (Receivable/Payable) een immutable `OpenItemSide` (Debit/Credit). OriginalAmount is een positieve magnitude. Receivable/Credit modelleert een customer credit balance en is geen Payable.
- OpenItem bewaart immutable Applied- en Reversal-settlementfeiten; append-only OpenItemMatch-facts verbinden same-tenant, same-Relation, same-Currency OpenItems van hetzelfde type en tegengestelde zijde. Open bedrag en status worden uit originalAmount plus deze gedateerde historie afgeleid en nooit los gemuteerd.
- Betalingen sluiten OpenItems via Application-orchestratie pas nadat de veroorzakende financiële boeking succesvol is gepost.
- Grootboeksaldi worden berekend uit geposte JournalEntries en niet afzonderlijk opgeslagen.
- BookYears zijn Administration-owned, hebben immutable inclusieve start/eindgrenzen en
  overlappen niet binnen één Administration. Gaps tussen BookYears zijn toegestaan,
  maar iedere PostingDate in zo'n gap levert `NoPeriod`.
- AccountingPeriods zijn children van exact één same-Administration BookYear. Hun
  immutable custom dateranges overlappen niet en dekken gezamenlijk het hele BookYear
  zonder gaps; maandperioden en kalenderjaren zijn niet verplicht.
- AccountingPeriod V1 heeft uitsluitend `Open` en `Closed`; `SoftClosed` bestaat niet.
  Close en Reopen vereisen een reason/actor/authoritative tijd en appenden immutable
  PeriodStatusHistory. Reopen zet Closed terug naar Open zonder eerdere audit te wissen.
- BookYear heeft in V1 geen zelfstandige close-action of durable status. Een volledig
  gesloten jaar is alleen een afleiding uit alle Closed periods.
- Iedere nieuwe PostingRequest-datum moet exact één Open AccountingPeriod vinden.
  Closed, NoPeriod en IntegrityFailure schrijven geen financiële feiten.
- De guard gebruikt uitsluitend Accounting PostingDate. FiscalReportingDate, TaxPeriod
  en VAT/ICP filing locks zijn afzonderlijke toekomstige Fiscal-contracten.

#### Domain Events

- `JournalEntryCreated`
- `JournalEntryValidated`
- `JournalEntryPosted`
- `OpenItemCreated`
- `OpenItemClosed`

#### Architectuurregels

- Alle financiële mutaties verlopen via de `PostingEngine`.
- Geen Aggregate maakt zelf JournalEntries.
- Facturen, betalingen en banktransacties leveren `PostingRequest`-objecten aan.
- De `PostingEngine` is de enige component die JournalEntries mag aanmaken.
- Iedere duurzame PostingEngine-caller voert de periodguard binnen dezelfde outer
  transaction uit en houdt de shared period lock tot commit; Web/preflight is nooit
  authoritative. Close/Reopen gebruiken een exclusive lock op dezelfde periodrow.
- Accounting bevat geen Laravel-, database- of infrastructuurafhankelijkheden.

**Capabilitystatus:** Posting foundation completed; AP-001 authorization/persistence,
AP-002 transactionele PostingDate-lock enforcement over alle zes duurzame postingflows
en AP-003 permission-scoped management-Web zijn completed. AP-003 gebruikt uitsluitend
de bestaande Application-contracten voor expliciete custom periodsetup, readiness,
Close/Reopen en ordered history. Create is insert-only; duplicate tenant-code muteert
geen bestaand BookYear en labelwijziging loopt uitsluitend via `UpdateBookYearLabel`.
Er is geen automatische setup/bootstrap. AP-004 verzorgt review en manual acceptance.

AP-003R staat vóór de eerste Close/history een atomische vervanging van uitsluitend het
Open periodplan toe. Een expected-plan fingerprint, BookYear/periodrow-locks, volledige
coveragevalidatie en historische PostingDate-dekking voorkomen stale of gedeeltelijke
replacement. Closed/history-bearing periods en alle financiële facts blijven immutable.
AP-004 heeft model, authorization, zes-flow enforcement, concurrency, Web/security en
manual acceptance gezamenlijk groen beoordeeld. De AP-capability is merge-ready; de
expliciet deferred scope blijft buiten V1.

## 5. Fiscal

| Naam | Doel | Korte beschrijving |
| --- | --- | --- |
| TaxCode | Fiscale behandeling classificeren | Een code die een herkenbare fiscale toepassing benoemt. |
| TaxRate | Een belastingpercentage vastleggen | Een percentage dat binnen een fiscale berekening wordt toegepast. |
| TaxCodeCode | Een TaxCode herkenbaar identificeren | De immutable functionele code van een fiscale classificatie. |
| TaxCodeName | Een TaxCode benoemen | De immutable leesbare naam van een fiscale classificatie. |
| TaxCalculation | Fiscale bedragen berekenen | Een frameworkonafhankelijke domain service die met Money een fiscaal bedrag afleidt. |
| TaxPosting | Een gepost fiscaal feit traceerbaar vastleggen | Een immutable TaxCode/rate/base/tax-snapshot koppelen aan bron-documentregel en geposte financiële regel. |
| TaxPeriod | Een fiscaal aangiftetijdvak afbakenen | De periode waarvoor fiscale bedragen worden vastgesteld. |
| TaxReturn | Een fiscale aangifte representeren | De formele rapportage van verschuldigde en verrekenbare belasting over een tijdvak. |

### Fiscal Capability

#### Aggregate

| Aggregate Root | Verantwoordelijkheid |
| --- | --- |
| TaxCode | Een fiscale classificatie met precies één actief TaxRate beheren. |
| TaxPosting | Een append-only fiscaal feit met bron-, posting- en correctietrace bewaren. |

#### Value Objects

- `TaxRate` representeert een immutable fiscaal tarief.
- `TaxCodeCode` representeert de immutable functionele code van een TaxCode.
- `TaxCodeName` representeert de immutable naam van een TaxCode.

#### Domain Service

`TaxCalculation` berekent fiscale bedragen met het gedeelde `Money` value object. De service classificeert of boekt geen financiële mutaties en maakt geen JournalEntries.

#### Businessregels

- Iedere TaxCode heeft precies één actief TaxRate.
- TaxRate is immutable.
- TaxCalculation gebruikt Money en geen floats of primitieve geldbedragen.
- TaxCalculation maakt geen JournalEntries.
- TaxPosting bewaart de gebruikte TaxRate, taxable base, taxAmount, TaxTreatment en VAT-return-/ICP-classificatie als transactiesnapshot; de actuele TaxCode-state is nooit historische rapportagewaarheid.
- SalesInvoice bewaart document-level immutable customer/supplier VAT-ID- en jurisdictionsnapshots plus een expliciete nullable SupplyDate. InvoiceDate en OrderDate zijn geen impliciete prestatiedatum; SalesCreditInvoice erft de oorspronkelijke fiscale context uit haar source invoice.
- TaxPostings zijn immutable en append-only. Het definitieve correctiemodel gebruikt een expliciet type `Original` of `Reversal`; bedragen blijven positief en Input/Output-direction blijft gelijk aan het origineel.
- Een Reversal verwijst naar precies één Original, neemt TaxCode/rate/base/tax/Currency/direction exact over en is in v1 altijd volledig. Een Original mag maximaal eenmaal worden gereversed en een Reversal kan niet zelf reversal-target zijn.
- Correcties verwijderen geen feiten. Application laat de financiële tegenboeking door PostingEngine maken en creëert pas daarna het Reversal-TaxPosting met de werkelijke correctie-JournalEntry/Line-identiteiten.
- `PostingEngine` blijft als enige verantwoordelijk voor het maken en posten van JournalEntries.
- De Fiscal-kern bevat geen land-specifieke fiscale regels; zulke regels worden later buiten de kern gemodelleerd.

#### Architectuurregels

- Fiscal is onafhankelijk van Sales en Purchasing.
- Application vertaalt Sales-/Purchasing-documentregelidentiteiten naar een capability-neutrale Fiscal-bronreferentie en orkestreert fiscale classificatie en berekening.
- Fiscal muteert geen Sales-, Purchasing- of Accounting-aggregates.
- TaxPosting mag immutable JournalEntryId en JournalEntryLineId refereren zonder JournalEntry te maken of muteren; Accounting draagt geen fiscale metadata.
- Financiële gevolgen lopen uitsluitend via Accounting en `PostingEngine`.
- Fiscal bevat geen Laravel-, database-, repository- of infrastructuurafhankelijkheden.

**Capabilitystatus:** Designed; implementation starts with F1-001.

## 6. Banking

| Naam | Doel | Korte beschrijving |
| --- | --- | --- |
| BankAccount | Een financiële rekening identificeren | Een rekening voor het ontvangen, bewaren en uitbetalen van geld. |
| BankStatement | Een aangeleverd rekeningoverzicht representeren | Een overzicht van bankmutaties over een bepaald tijdvak. |
| BankTransaction | Eén bankmutatie vastleggen | Een bij- of afschrijving met bedrag, datum en omschrijving. |
| Payment | Een bankmutatie interpreteren | Exact één positief EUR Payment-child per manual BankTransaction, met meerdere OpenItem-allocations. |

### Banking Capability

#### Aggregate en child entity

| Aggregate Root | Child Entity | Verantwoordelijkheid |
| --- | --- | --- |
| BankTransaction | Payment, PaymentAllocation | Een bankmutatie als primaire financiële gebeurtenis en haar allocatie-intent beheren. |

Een manual BankTransaction bevat exact één Payment. Payment en diens allocations zijn
children en worden uitsluitend via de BankTransaction Aggregate Root beheerd.

#### Domain Service

`Matching` koppelt BankTransactions aan `OpenItem` aggregates uit Accounting. De service ondersteunt de domeinbeslissing welke openstaande post door een bankmutatie wordt voldaan, maar maakt geen JournalEntries en muteert geen OpenItems rechtstreeks.

#### Businessregels

- BankTransaction is binnen Banking de primaire financiële gebeurtenis.
- Een manual BankTransaction bevat exact één Payment met nul of meer Draft-allocations.
- Iedere allocation behoort tot precies één OpenItem; targets zijn uniek binnen Payment.
- Payment- en allocationbedragen zijn strikt positief EUR; het transactionbedrag is signed.
- Draft is wijzigbaar; Finalized bevriest movement, Payment en allocations.
- Finalize vereist minimaal één allocation en vergelijkt de exacte som met het absolute BankTransaction-bedrag.
- Finalize valideert OpenItem-readiness maar maakt geen JournalEntry of Settlement.
- Alle financiële boekingen verlopen uitsluitend via Accounting en `PostingEngine`.
- Banking bevat geen UI-, import-, reconciliation- of PSD2-logica.

#### Architectuurregels

- Banking is onafhankelijk van Sales en Purchasing.
- Banking mag uitsluitend afhankelijk zijn van Shared, Administration, Accounting en Relations.
- Iedere BankTransaction hoort bij precies één Administration; AdministrationId en BankAccountId worden beide immutable in het aggregate vastgelegd.
- Consistentie tussen BankAccountId en AdministrationId wordt later buiten het aggregate gecontroleerd, omdat daarvoor externe gegevens nodig zijn.
- OpenItem blijft een Accounting Aggregate Root; Banking neemt geen ownership over.
- Banking maakt geen JournalEntries en bevat geen `PostingEngine`-implementatie.
- Banking bevat geen Laravel-, database-, repository- of infrastructuurafhankelijkheden.

**Capabilitystatus:** Banking Foundation first domain iteration completed.

### B2 manual Bank Payments

B2 definieert BankTransaction als het Administration-owned factual bankmovement en de
enige aggregate/use-casebron voor financiële posting. Signed EUR Money is positief voor
CustomerReceipt en negatief voor SupplierPayment. Exact één Payment-child per manual
BankTransaction bezit meerdere positieve allocations naar same-Administration,
same-Relation, same-type compatible OpenItems; Payment heeft geen zelfstandige lifecycle.

De lifecycle wordt Draft → Finalized → Posted en Draft → Cancelled. Finalized bevriest
movement/interpretatie/allocations; één outer Application-transaction lockt targets op
gesorteerde OpenItemId, maakt via PostingEngine de Bank JournalEntry, append-only Applied
settlements en postinglinkage en markeert pas daarna Posted. OpenItem originalAmount
blijft immutable. Settlement is cashrealisatie; Match blijft een opposite-side
documentbalanceverbinding.

Een operationele AdministrationBankAccount is niet de Relation BankAccount en niet het
organisation-IBAN voor documentdisplay. Per AdministrationBankAccount mapt
BankingPostingConfiguration expliciet naar active Bank Journal en active Asset Bank
LedgerAccount. AR/AP is immutable `controlLedgerAccountId`-openingstruth van het
OpenItem; Banking herleest geen actuele Sales/Purchase-config en gebruikt geen
accountheuristiek.

B2 V1 is EUR-only, vereist volledige allocation van het absolute bankbedrag en kent
geen unallocated remainder, overpayment/suspense, import, reconciliation of FX.
TransactionDate en expliciete PostingDate zijn verschillende feiten.

B2-001A maakt `OpenItem.controlLedgerAccountId` verplichte immutable Accounting-truth.
SalesInvoice, SalesCredit en PurchaseInvoice leveren de werkelijk geboekte AR/AP-
LedgerAccount uit dezelfde postingtransactie. Bestaande facts zijn uitsluitend via één
same-side, exact-amount source JournalEntryLine gebackfilld; nul of meerdere kandidaten
zijn een harde fout. Een same-tenant RESTRICT-FK bewaakt de identity. Actuele posting-
configuratie, rekeningcode/naam/type of active status herschrijven deze historie nooit.

### Integrated Financial Flow – I1

#### Flow

```text
SalesInvoice
        ↓
PostingRequest
        ↓
PostingValidation
        ↓
PostingEngine
        ↓
JournalEntry
        ↓
OpenItem
        ↓
Payment
        ↓
BankTransaction
        ↓
Matching
        ↓
OpenItem append-only settlement
```

`Payment` is in deze conceptuele keten geen zelfstandig Aggregate Root, maar een child entity die uitsluitend binnen `BankTransaction` bestaat. Na succesvolle bankposting past Application een immutable Accounting-settlement toe met settlement-ID, PostingDate en de werkelijk geposte JournalEntry als bron. Matching en Banking muteren het OpenItem niet rechtstreeks.

#### Verantwoordelijkheden en hand-offs

| Stap | Verantwoordelijke | Bestaande service | Hand-off |
| --- | --- | --- | --- |
| SalesInvoice | SalesInvoice Aggregate Root | — | Sales eindigt bij een geldige, gefinaliseerde factuurcontext. |
| PostingRequest | Accounting request-object | — | Draagt JournalId, PostingDate, JournalEntryReference en JournalEntryLines naar Accounting. |
| PostingValidation | Accounting | PostingValidation | Valideert minimaal twee regels, Currency, unieke regelidentiteiten en debet = credit. |
| PostingEngine | Accounting | PostingEngine | Is als enige verantwoordelijk voor het maken en posten van JournalEntry. |
| JournalEntry | JournalEntry Aggregate Root | — | Bewaart de immutable geposte boeking; maakt zelf geen OpenItem. |
| OpenItem | OpenItem Aggregate Root | — | Bewaart immutable openingscontext en append-only settlementchildren; openAmount en status zijn afleidingen. |
| Payment | Child entity van BankTransaction | — | Verwijst met OpenItemId naar precies één OpenItem en draagt een positief Money-bedrag. |
| BankTransaction | BankTransaction Aggregate Root | — | Bewaakt Payment-ownership, Currency en de Imported → Matched → Posted-statusmachine. |
| Matching | Banking | Matching | Valideert de exacte Payment-som en zet alleen een geldige Imported transactie op Matched. |
| OpenItem settlement | OpenItem Aggregate Root | — | Application roept na succesvolle posting `applySettlement(...)` of `reverseSettlement(...)` aan met de geposte JournalEntry als bron; Banking muteert Accounting niet rechtstreeks. |

#### Dragende Value Objects

- `Money` en `Currency` dragen alle bedragen en exacte berekeningen door de volledige flow.
- `AdministrationId` bewaakt de expliciete administratiescheiding in Sales, Accounting en Banking.
- `SalesInvoiceId`, `JournalEntryId`, `OpenItemId`, `PaymentId` en `BankTransactionId` dragen identiteit binnen hun eigen aggregategrens.
- `CustomerId` en `RelationId` verbinden de debiteurcontext zonder aggregate-ownership over te nemen.
- `JournalId`, `PostingDate`, `JournalEntryReference`, `JournalEntryLineId` en `LedgerAccountId` dragen de boekingsopdracht.
- `BankAccountId`, `BankTransactionReference` en `TransactionDescription` dragen de banktransactiecontext.

#### Bewezen application-koppelingen

- `CreateSalesInvoicePostingRequest` vertaalt Finalized, Posted en Paid SalesInvoices naar een expliciete Accounting PostingRequest; Draft en Cancelled worden geweigerd.
- `CreatePurchaseInvoicePostingRequest` doet hetzelfde voor PurchaseInvoice zonder een Purchasing→Accounting-dependency in Domain te introduceren.
- `CreateBankTransactionPostingRequest` vertaalt uitsluitend een Matched BankTransaction met Payments naar een bankboeking; Imported en Posted worden geweigerd.
- De Application-laag kiest JournalId, LedgerAccountIds, JournalEntryLineIds, PostingDate en JournalEntryReference en bevat daarmee de capability-overstijgende orchestration.
- PostingValidation accepteert de gebalanceerde requests en uitsluitend PostingEngine maakt de geposte JournalEntries.
- De end-to-endtest moet in R2-001B migreren naar OpenItem-constructie met de PostingDate van de geposte factuurboeking en na bankposting `applySettlement(...)` gebruiken met settlement-ID, effectieve PostingDate en de geposte bank-JournalEntry als bron.
- Matching sluit geen OpenItem en maakt geen boeking; een BankTransaction gebruikt een afzonderlijke PostingRequest via dezelfde PostingEngine.

#### Acceptance en resterende grenzen

- De succesketen en foutpaden voor ongeldige statussen, ongebalanceerde allocaties, te lage en te hoge settlements en voortijdig sluiten zijn met echte domeinobjecten bewezen.
- Money-totalisatie en -vergelijking gebruiken exacte decimale strings zonder floats.
- De drie PostingRequest-use-cases dupliceren beperkt orchestrationpatroon voor totalisatie, beschrijving en twee boekingsregels. Dit is zichtbare technische schuld, maar abstraheren vóór extra stabiele varianten zou de capabilitytaal verbergen.
- De exclusiviteit van PostingEngine als JournalEntry-factory is in productiecode en architectuurregels aantoonbaar, maar nog niet technisch afgedwongen door modulevisibility.
- Generieke auditmetadata, persistence en Reporting-projecties vallen buiten deze Domain-iteratie. R2-001B0 legt wel vast dat Accounting settlement en reversal append-only en brontraceerbaar maakt; Reporting blijft hiervan uitsluitend read-only consument.

**Milestonestatus M5:** Integrated Financial Flow accepted; Reporting kan starten zonder fundamentele architectuurwijziging.

## 7. Documents

| Naam | Doel | Korte beschrijving |
| --- | --- | --- |
| Document | Een bedrijfsdocument als domeinobject benoemen | Een bestand of vastgelegde inhoud met zakelijke betekenis en metadata. |
| DocumentType | Documenten functioneel classificeren | Een categorie die het doel en de behandeling van een document benoemt. |
| Attachment | Aanvullende inhoud vastleggen | Een bestand dat als ondersteunend bewijs of bijlage wordt bewaard. |
| ArchiveRecord | Bewaring van een document registreren | Een vastlegging van de duurzame archivering van zakelijke informatie. |

## 8. Security

| Naam | Doel | Korte beschrijving |
| --- | --- | --- |
| User | Een menselijke gebruiker identificeren | Een persoon die toegang tot Finance Core Platform kan krijgen. |
| Role | Een bundel verantwoordelijkheden benoemen | Een herkenbare functie binnen het autorisatiemodel. |
| Permission | Een toegestane handeling beschrijven | Een expliciet recht om een bepaalde actie uit te voeren. |
| AdministrationMembership | Deelname aan een Administration vastleggen | De domeinrepresentatie van toegang van een gebruiker tot een administratieve eenheid. |
| AuditRecord | Een relevante handeling traceerbaar maken | Een onveranderlijke registratie van een beveiligings- of bedrijfsrelevante actie. |

### Webcontext en domeingrens

Authentication en de actieve websessie zijn Presentation/Infrastructure-verantwoordelijkheden en geen nieuwe Domain-concepten. Het bestaande Identity Domain blijft eigenaar van `User`, `AdministrationMembership`, `MembershipRole`, `Role`, `RolePermission` en `Permission`.

Een administration-scoped Application-use-case mag alleen worden uitgevoerd wanneer de geauthenticeerde gebruiker op het moment van de request een actief en geldig `AdministrationMembership` voor die Administration heeft en via actieve rol- en permissiontoekenningen de vereiste businessautorisatie bezit. Een in de sessie, route, query of form opgenomen `AdministrationId` is uitsluitend invoer en nooit zelfstandig bewijs van toegang. De Presentation-laag selecteert context en formatteert uitkomsten, maar muteert Domain uitsluitend via Application-use-cases. Een toekomstig systeembeheerpad moet expliciet geautoriseerd en geaudit zijn en blijft onderworpen aan dezelfde Administration-afbakening; globale impliciete tenant-bypass bestaat niet.

Accounting-masterdata is Administration-owned. `Journal` en `LedgerAccount` hebben
immutable identity, code en type; naam en Active/Inactive-status zijn expliciet mutable.
Lifecyclebeheer gebruikt geen hard delete. Gedeactiveerde masterdata blijft historische
JournalEntries ondersteunen maar is niet beschikbaar voor nieuwe configuratie/posting.
Een bestaande configuration wordt nooit automatisch vervangen en kan daardoor typed
`InvalidReference` worden totdat een volledige geldige mapping wordt opgeslagen.

Het Laravel-authaccount is een Infrastructure-authenticatierecord en blijft onderscheiden van de zakelijke Domain `User`. Ieder authaccount verwijst in v1 via exact één verplichte, unieke UUID-reference naar één `UserId`; e-mail is geen identiteitskoppeling. Passwordhashes, remember-tokens en resetgegevens blijven buiten Domain. `AdministrationMembership` blijft eigendom van Identity en mag de immutable `AdministrationId` kennen; Administration krijgt geen dependency op Identity. Rollen en permissions zijn systeemwijde definities, terwijl `MembershipRole` hun toekenning per AdministrationMembership afbakent. Directe User-permissions bestaan niet in v1.

W1 concretiseert deze grens met een per request opnieuw gevalideerde actieve Administration-context. Persistence kent tenantownership en samengestelde same-tenant constraints waar het Domain zelf geen AdministrationId draagt. Het read-only dashboard introduceert geen nieuwe financiële waarheid: omzet komt uit TrialBalance/ProfitAndLoss, openstaande debiteuren en crediteuren uit het immutable `OpenItemType` plus OpenItemsReport, en de BTW-positie uit VatOverview. Customer/Supplier-overlap staat los van de financiële OpenItem-classificatie. Presentation formatteert uitsluitend typed Money en verricht geen financiële berekening.

## 9. Reporting

| Naam | Doel | Korte beschrijving |
| --- | --- | --- |
| BalanceSheet | Bezittingen en financiering rapporteren | Een financieel overzicht van activa, passiva en eigen vermogen op een peildatum. |
| ProfitAndLossStatement | Resultaat over een periode rapporteren | Een overzicht van opbrengsten, kosten en resultaat binnen een tijdvak. |
| TrialBalance | Grootboeksaldi controleren | Een overzicht van debet- en creditsaldi per grootboekrekening. |
| AgingReport | Openstaande bedragen naar ouderdom analyseren | Een overzicht dat nog te ontvangen of te betalen bedragen in ouderdomscategorieën toont. |
| DashboardMetric | Een kernwaarde compact presenteren | Een berekende indicator voor operationeel of financieel inzicht. |

### Reporting Capability

Reporting is read-only ten opzichte van de financiële domeinwaarheid. De capability observeert bestaande gegevens, berekent rapportuitkomsten en muteert geen aggregates. Een rapportuitkomst of latere projectie is een reproduceerbare afleiding en geen nieuwe financiële waarheid.

#### Bronnen en selectiegrenzen

- `JournalEntry` en `JournalEntryLine` zijn de boekingsbron voor grootboekrapportages.
- `LedgerAccount` levert de rekeningidentiteit en classificatie die nodig zijn om regels te groeperen en rapportsecties te selecteren.
- `OpenItem` is de Accounting-bron voor historische openstaande-postenrapportages.
- `TaxPosting` is de Fiscal-bron voor VAT Overview.
- Alleen JournalEntries met status `Posted` worden opgenomen; Draft JournalEntries tellen niet mee.
- Iedere rapportage vereist een expliciet `Administration`-filter.
- Iedere rapportage vereist een expliciete datum, balansdatum of periode van/tot, passend bij het rapport.
- Tegenboekingen worden als eigen geposte JournalEntries verwerkt en werken daardoor via dezelfde selectie door in de berekende uitkomst.

#### Eerste rapportages

**Trial Balance**

De Trial Balance groepeert de geselecteerde JournalEntryLines per LedgerAccount en levert minimaal:

- `totalDebit`: de som van de debetbedragen binnen Administration en datum/periode;
- `totalCredit`: de som van de creditbedragen binnen Administration en datum/periode;
- `balance`: het berekende saldo op basis van totalDebit en totalCredit.

Over het volledige rapport bepalen exacte Money-totalen of debit en credit gelijk zijn. De selectie bevat uitsluitend Posted JournalEntries en gebruikt inclusieve periodegrenzen. `balance` is exact `totalDebit - totalCredit`: een normaal debetsaldo is positief en een normaal creditsaldo negatief.

**Balance Sheet**

De Balance Sheet is gebaseerd op Trial Balance-resultaten, bevat uitsluitend LedgerAccounts met classificatie Asset, Liability of Equity en rapporteert op een expliciete balansdatum die gelijk is aan de Trial Balance-einddatum. Asset gebruikt het Trial Balance-saldo direct; Liability en Equity worden uitsluitend voor presentatie met `Money::absolute()` genormaliseerd. Zij introduceert geen zelfstandig opgeslagen saldi en vergelijkt exact `totalAssets = totalLiabilities + totalEquity`.

**Profit & Loss**

De Profit & Loss is gebaseerd op Trial Balance-resultaten, bevat uitsluitend LedgerAccounts met classificatie Revenue of Expense en rapporteert over de overgenomen expliciete periode van/tot. Revenue wordt uitsluitend voor presentatie met `Money::absolute()` genormaliseerd; Expense behoudt het Trial Balance-saldo. `netResult` is exact `totalRevenue - totalExpenses`. Zij introduceert geen zelfstandig opgeslagen resultaat.

#### Operationele rapportages

- General Ledger Report: deterministisch geordende Posted JournalEntry-regels met een berekende period movement balance volgens `debit - credit`.
- Open Items Report: historische openstanden en statussen op peildatum, uitsluitend gelezen via `OpenItem::openAmountAt()` en `statusAt()`.
- VAT Overview: Input en Output VAT, 0%-classificaties, Original/Reversal-feiten en groepering op TaxCodeId, TaxRate-snapshot en expliciete treatment/VAT-return/ICP-classificatie, uitsluitend afgeleid uit `TaxPosting`.

#### Architectuurregels

- Reporting maakt of post geen JournalEntries.
- Reporting wijzigt geen Accounting-, Sales-, Purchasing-, Banking- of Fiscal-aggregates.
- Grootboeksaldi worden berekend uit geposte JournalEntries en niet als domeinwaarheid opgeslagen.
- Reporting bevat geen Laravel-, database-, repository-, infrastructuur- of UI-logica.
- Read models en projecties mogen later in Application/Infrastructure worden geïntroduceerd voor selectie en performance, maar zijn herbouwbare afleidingen en geen nieuwe financiële waarheid.

#### Bekende niet-blokkerende beperkingen en vervolg

- De huidige calculators werken op volledig aangeleverde in-memory domeinobjecten; schaalbare selectie en projecties volgen in Application/Infrastructure.
- Balance Sheet veronderstelt dat de Trial Balance de benodigde openings- en historische saldi bevat; boekjaaropening, carry-forward en resultaatbestemming vragen expliciet vervolgontwerp.
- De presentatie-normalisatie signaleert afwijkende debet-/creditsaldi nog niet als aparte waarschuwing.
- Aging buckets, schaalbare projecties, autorisatie en export-/presentatiecontracten zijn aanbevolen vervolgstories.

**Capabilitystatus:** Reporting Foundation en Operational Reporting completed (R1-004, R2-005).

### Operational Reporting (R2)

Operational Reporting blijft een read-only afleiding en sluit aan op de R1-context en tekenconventie: Administration is verplicht, datum/periode en Currency zijn expliciet, Money blijft de geldrepresentatie en geen rapportuitkomst wordt teruggeschreven.

#### General Ledger Report / Grootboekkaart

- Bronnen: `JournalEntry`, `JournalEntryLine` en `LedgerAccount`.
- Context: AdministrationId, inclusieve startDate/endDate, Currency en optioneel LedgerAccountId.
- Alleen Posted JournalEntries binnen Administration en periode worden opgenomen.
- Iedere regel bevat minimaal postingDate, JournalEntryId, JournalId, reference, LedgerAccountId, debit, credit en running balance; JournalEntryLineId wordt als stabiele trace/tie-breaker aanbevolen.
- Volgorde is postingDate, JournalEntryId en JournalEntryLineId, alle oplopend op canonieke waarde.
- Running balance is exact de cumulatieve periodebeweging `vorige balance + debit - credit`, start in de eerste iteratie op Money-zero en wordt niet opgeslagen. Een boekhoudkundig openingssaldo vóór startDate vraagt later expliciete openingsbalansinput of een voorafgaande Trial Balance.

#### Open Items Report / Openstaande posten

- Bron: `OpenItem` met immutable openingscontext en append-only `OpenItemSettlement`-children.
- Context: AdministrationId, peildatum, Currency en optioneel RelationId.
- Closed wordt standaard uitgesloten; open en partially settled worden read-only gerapporteerd.
- `OpenItem` bewaart `openedOn` plus immutable Applied/Reversal-feiten; `openAmount` en status worden voor iedere peildatum uit Accounting-bronwaarheid afgeleid.
- Reporting berekent of muteert geen settlements en gebruikt `openAmountAt()` en `statusAt()` read-only.

#### VAT Overview / BTW-overzicht

- Bron: immutable Fiscal-owned `TaxPosting`-feiten met duurzame trace naar bron-documentregel en geposte Accounting-regels.
- Vereiste context: AdministrationId, inclusieve periode en Currency; aanvullende jurisdictie-/aangiftecontext volgt pas uit fiscaal ontwerp.
- `TaxPosting` bewaart TaxCodeId, gebruikte TaxRate, taxable base, taxAmount, direction, bron-documentregel, AdministrationId, PostingDate en IDs voor de geboekte base-line en, uitsluitend bij positieve tax, tax-line.
- PostingRequest en JournalEntryLine blijven generiek. Application bouwt netto/VAT/bruto-regels, laat uitsluitend PostingEngine posten en finaliseert daarna TaxPostings met de werkelijk geposte IDs.
- Sales en Purchasing Application-services orkestreren Original-postings en volledige creditreversals zonder Domain-dependencies tussen capabilities.
- Correcties gebruiken `TaxPostingType::Original/Reversal`, positieve bedragen en onveranderde Input/Output-direction. Reversals gebruiken het creditdocument als nieuwe bron, verwijzen naar één Original en vallen via hun eigen PostingDate in de correctieperiode.
- `TaxPostingIdentityPolicy` bewaakt nieuwe IDs tegen consistente aangeleverde historie; iedere orchestration weigert daarnaast duplicaten binnen dezelfde uitvoering vóór PostingEngine. Globale concurrency-safe uniciteit blijft een persistenceverantwoordelijkheid.
- VAT Overview telt Reversal-snapshots tegen, houdt Input en Output gescheiden, behoudt 0%-feiten en groepeert op TaxCodeId plus historische TaxRate-snapshot.

#### Bekende niet-blokkerende beperkingen

- De calculators ontvangen complete in-memory bronnen; selectie, autorisatie en schaalbare projecties volgen in Application/Infrastructure.
- Concurrency-safe TaxPostingId-uniciteit en dubbele-reversalpreventie vereisen toekomstige persistenceconstraints en transacties.
- Symmetrische fiscale orchestration is bewust expliciet maar bevat duplicatie; `VatOverviewLine` gebruikt nog `mixed` returntypes voor gedelegeerde auditgetters.

**R2-status:** Completed (R2-005). General Ledger, historische Open Items en VAT Overview zijn betrouwbaar reproduceerbaar vanuit Accounting- en Fiscal-bronwaarheid.

## 10. Purchasing – duurzame PurchaseInvoice

`PurchaseInvoice` is de aggregate root voor een ontvangen leveranciersfactuur. Zij is
Administration-owned en bezit `PurchaseInvoiceLine` children. De lifecycle is Draft →
Finalized → Posted, met pre-post Cancel vanuit Draft of Finalized. P3-002 biedt geen
Application-transition naar Posted en paymentstate is geen documentstatus.

De aggregate bewaart de case-sensitive externe SupplierInvoiceNumber met harde
Administration + Supplier uniqueness, expliciete supplier-/documentaddress-snapshots,
SupplierInvoiceDate, ReceivedDate, nullable SupplyDate, afgeleide FiscalReportingDate,
DueDate en EUR. Iedere regel bewaart intended Expense/Asset-accounttruth en volledige
ondersteunde domestic Input-Tax truth plus exacte net/tax/gross Money-bedragen. Finalize
maakt alle inhoud immutable en registreert Domain UserId en applicatie-clock timestamp.

Persistence gebruikt tenant-scoped repository/readcontracten, composite same-tenant
foreign keys en RESTRICT delete-policy. P3-002 heeft geen Accounting/Fiscal side effects:
JournalEntry, TaxPosting, OpenItem en PurchaseInvoicePosting ontstaan uitsluitend in de
latere P3-003 postingorchestratie.

P3-003 realiseert die orchestratie nu: een expliciete PostingDate stuurt de Purchase
JournalEntry, terwijl TaxPosting de persisted FiscalReportingDate gebruikt. Expense/
Asset net en positieve Input VAT zijn debet; Accounts Payable gross is credit. Iedere
source line behoudt fiscale trace, inclusief zero-tax zonder fictieve VAT-journalregel.
Het ene OpenItem is Payable/Credit met historische Relation, gross en DueDate. Een
same-tenant append-only linkage en invoice row lock borgen at-most-once; alle facts plus
de Posted-status committen of rollen gezamenlijk terug.

P3-004 maakt de keten productmatig beschikbaar via list/detail, coherente Draft-mutatie,
Finalize en Post. De vier Purchasing-permissions blijven onafhankelijk en worden per
request geëvalueerd. Selectors zijn actief en tenant-scoped; bestaand documentdetail
gebruikt uitsluitend historische snapshots. Presentation levert een expliciete
PostingDate aan de Application-use-case en toont na succes het Payable/Credit OpenItem,
maar berekent of schrijft zelf geen financiële waarheid en biedt geen paymentflow.

## 11. Banking – atomische cash settlement

`BankTransaction` bezit exact één Payment met PaymentAllocation-children. Finalize
bevriest allocaties maar reserveert geen OpenItem-saldo. `PostBankTransaction` lockt de
tenant-scoped transaction en daarna unieke OpenItems deterministisch, herleest actuele
settlements en matches en valideert EUR, Relation, type/side, open saldo en het
historische `controlLedgerAccountId`.

Uitsluitend PostingEngine maakt de ene JournalEntry. CustomerReceipt boekt Bank debet
en historische receivable-controlaccounts credit; SupplierPayment doet het omgekeerde
voor payable-controlaccounts. Per allocation ontstaat één immutable cash
OpenItemSettlement met duurzame allocation- en JournalEntry-trace. OpenItemMatch blijft
document matching. JournalEntry, settlements, append-only postinglinkage en Posted met
actor/tijd committen atomisch; open saldo blijft een afleiding van immutable feiten.

De B2 Webgrens gebruikt tenant-scoped Application-composities voor list/detail en
eligible masterdata. Presentation vertaalt alleen de keuze klantontvangst/
leveranciersbetaling en een positief gebruikersbedrag naar signed Money; zij bepaalt
geen eligibility, controlaccount, saldo of financiële boeking. View, Manage en Post zijn
afzonderlijke effective permissions. Posted blijft immutable en wordt als JournalEntry-
linkage plus Settlement- en remaining-balance-afleidingen gepresenteerd.

### Bank Payment Reversal

Een B3 Bank Payment Reversal corrigeert exact één `Posted` handmatige BankTransaction
volledig en atomisch. De original en al haar Payment-, allocation-, posting-, Journal-
en Applied Settlement-facts blijven immutable en haar status blijft `Posted`;
`Teruggedraaid` is uitsluitend afgeleid uit de unieke tenant-scoped
BankTransactionReversal-linkage. Eén command vereist een expliciete
ReversalPostingDate, verplichte begrensde immutable reason en authoritative
ReversedBy/ReversedAt. Een tweede coherente poging is `AlreadyReversed`; incomplete of
partieel vooraf gereversede sourcefacts zonder linkage zijn `FinancialStateInvalid`.

De ene contra-JournalEntry spiegelt iedere originele line debit/credit op exact de
historische Journal en LedgerAccountIds. Rename/inactive verandert die identity niet;
actuele BankingPostingConfiguration wordt niet gebruikt. Omdat de bankposting geen
fiscale bron is, ontstaat geen TaxPosting. Iedere originele allocation-identiteit leidt
naar precies haar Applied Settlement; per fact ontstaat één full-amount append-only
Reversal. Een Banking-owned reversal-settlementlinkage verbindt reversalbatch,
allocation, OpenItem en beide settlementidentities.

OpenItemMatches en unrelated settlements blijven onaangetast. Daardoor kan een
settlementreversal een source Receivable/Payable heropenen terwijl een latere Sales- of
PurchaseCredit-match historische documenttruth blijft. Open amount blijft zonder clamp
afgeleid uit alle gedateerde facts. De outer transaction gebruikt de B2/PC-resourceorde
en lexicografisch gesorteerde unieke OpenItemIds, zodat reversal, nieuwe payments en
creditmatching serialiseerbaar blijven. `BANKING.PAYMENTS_REVERSE` hoort uitsluitend bij
de aparte least-privilege `BANKING_REVERSAL_OPERATOR`; Web en JavaScript leiden nooit
authorization of financiële eligibility af.

B3-001 maakt de authorization- en persistencebasis concreet. De aparte canonical role
bevat exact View + Reverse en wordt nooit automatisch aan een membership toegewezen.
BankTransactionReversal en de per-allocation settlementlinkage zijn immutable typed
facts met composite same-tenant RESTRICT-relaties en uniqueness op iedere exclusieve
source/reversalclaim. Een Application source-reader levert B3-002 de volledige
historische JournalEntry/linegraph en de unieke `payment_allocation_id`-settlementroute,
zonder active-masterdata- of current-balancefilter. Typed eligibility onderscheidt
Eligible, NotPosted, AlreadyReversed, FinancialStateInvalid en NotFound. De foundation
creëert nog geen JournalEntry of settlementreversal en muteert `Posted` niet.

B3-002 gebruikt deze graph in één atomische Application-command. PostingEngine maakt
een line-for-line debit/creditmirror op de historical Journal en accounts; vervolgens
voegt ieder locked OpenItem exact één full-amount Reversal toe. Reversalrecord en
settlementlinkages worden pas geschreven wanneer hun JournalEntry-/settlementtargets
bestaan, binnen dezelfde transaction. Matches blijven staan en openAmount blijft een
afleiding van alle facts. Sequential/concurrent duplicate, payment- en creditmatchraces
zijn onder sorted OpenItemlocks serialiseerbaar; de gedeelde transaction boundary
herprobeert een door MySQL vastgestelde deadlock begrensd. Success exposeert uitsluitend
typed references, settlementcount en resulterende Money-balances voor de latere Weblaag.

B3-003 ontsluit dit contract onder Bank → Betalingen. List/detail tonen
tenant-scoped readiness en leiden `Teruggedraaid` uitsluitend af uit een coherente
reversallinkage; de original blijft `Posted`. Een View + Reverse confirmation accepteert
alleen expliciete ReversalPostingDate en reason, waarna een Reverse-only POST exact
`ReverseBankTransaction` aanroept. Presentation toont original/contra-Journal,
settlementreversals en door het Application-readmodel geleverde actuele OpenItem-
bedragen zonder financiële herberekening of Weblocks. Development acceptance vereist
nog de expliciete `BANKING_REVERSAL_OPERATOR`-membershipassignment.

## 12. Purchase Credits – duurzame documentlaag

PC-001 maakt `PurchaseCreditInvoice` duurzaam met exact één Posted source invoice,
immutable supplier/address/source-line/account/tax snapshots, Original TaxPosting-IDs,
de exacte source Payable/Credit en Created/Finalized/Cancelled auditfacts. Draft,
Finalize en Cancel veroorzaken geen Accounting- of Fiscal-mutaties; Posted kan worden
gereconstitueerd maar wordt pas door PC-002 gemuteerd.

PC-002 maakt `Posted` atomisch met auditactor/-tijd, expliciete boekingsdatum,
historische net/VAT/AP/journal-reversal, een `Payable/Debit` zonder vervaldatum en
duurzame source-line claims plus posting linkage. Actuele purchase-configuratie wordt
niet geraadpleegd. Match en settlement blijven buiten PC-002; het postingreadmodel
levert PC-003 alle identiteiten om actuele open saldi onder locks te herlezen.

PC-003 neemt matching op in dezelfde postingtransactie. Onder gesorteerde OpenItem-locks
is het bedrag `min(bron open, credit open)`; cashsettlements blijven immutable en een
overschot blijft als Payable/Debit leverancierscredit open. De permission-onafhankelijke
Webflow selecteert uitsluitend volledige regels van een tenant-owned Posted EUR-bron.
