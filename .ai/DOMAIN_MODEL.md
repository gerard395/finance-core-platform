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
Draft → Finalized → Posted → Paid
```

Een PurchaseCreditInvoice doorloopt:

```text
Draft → Finalized → Posted
```

Beide documenten kunnen vanuit Draft of Finalized worden geannuleerd. Statusovergangen verlopen uitsluitend via domeinmethoden van het betreffende aggregate.

#### Businessregels

- Iedere PurchaseInvoice en PurchaseCreditInvoice heeft een leverancier.
- Iedere PurchaseInvoice en PurchaseCreditInvoice bevat minimaal één eigen regel voordat deze wordt gefinaliseerd.
- `PostingEngine` verwerkt alle financiële mutaties.
- PurchaseInvoice en PurchaseCreditInvoice maken nooit zelf JournalEntries.
- Na het posten ontstaat via Accounting een OpenItem; Purchasing maakt of beheert dit OpenItem niet rechtstreeks.

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
| FiscalYear | Een financieel verslagjaar afbakenen | De periode waarover de financiële administratie formeel rapporteert. |
| AccountingPeriod | Een boekingsperiode beheersen | Een afgebakend tijdvak met een eigen open- of geslotenstatus. |
| OpenItem | Een nog te vereffenen bedrag bewaken | Een financieel bedrag dat nog geheel of gedeeltelijk openstaat. |

### Accounting Capability

#### Aggregates en child entities

| Aggregate Root | Child Entity | Verantwoordelijkheid |
| --- | --- | --- |
| LedgerAccount | — | De classificatie van boekingsregels binnen het grootboek beheren. |
| Journal | — | Journaalposten naar de aard van hun financiële gebeurtenis groeperen. |
| JournalEntry | JournalEntryLine | Een gebalanceerde financiële mutatie en haar debet- en creditregels beheren. |
| OpenItem | — | Een uit een verkoop- of inkoopfactuur ontstaan openstaand bedrag en de afsluiting daarvan beheren. |

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

#### Businessregels

- Iedere JournalEntry bevat minimaal twee JournalEntryLines.
- Het totale debetbedrag van een JournalEntry is gelijk aan het totale creditbedrag.
- Een JournalEntry kan alleen worden gepost wanneer deze in balans is.
- Geposte JournalEntries zijn onveranderlijk.
- Correcties op geposte JournalEntries gebeuren via tegenboekingen, nooit door de oorspronkelijke boeking te wijzigen.
- OpenItems ontstaan uit verkoop- en inkoopfacturen.
- Betalingen sluiten OpenItems af.
- Grootboeksaldi worden berekend uit geposte JournalEntries en niet afzonderlijk opgeslagen.

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
- Accounting bevat geen Laravel-, database- of infrastructuurafhankelijkheden.

**Capabilitystatus:** Completed for first domain iteration.

## 5. Fiscal

| Naam | Doel | Korte beschrijving |
| --- | --- | --- |
| TaxCode | Fiscale behandeling classificeren | Een code die een herkenbare fiscale toepassing benoemt. |
| TaxRate | Een belastingpercentage vastleggen | Een percentage dat binnen een fiscale berekening wordt toegepast. |
| TaxCodeCode | Een TaxCode herkenbaar identificeren | De immutable functionele code van een fiscale classificatie. |
| TaxCodeName | Een TaxCode benoemen | De immutable leesbare naam van een fiscale classificatie. |
| TaxCalculation | Fiscale bedragen berekenen | Een frameworkonafhankelijke domain service die met Money een fiscaal bedrag afleidt. |
| TaxPeriod | Een fiscaal aangiftetijdvak afbakenen | De periode waarvoor fiscale bedragen worden vastgesteld. |
| TaxReturn | Een fiscale aangifte representeren | De formele rapportage van verschuldigde en verrekenbare belasting over een tijdvak. |

### Fiscal Capability

#### Aggregate

| Aggregate Root | Verantwoordelijkheid |
| --- | --- |
| TaxCode | Een fiscale classificatie met precies één actief TaxRate beheren. |

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
- `PostingEngine` blijft als enige verantwoordelijk voor het maken en posten van JournalEntries.
- De Fiscal-kern bevat geen land-specifieke fiscale regels; zulke regels worden later buiten de kern gemodelleerd.

#### Architectuurregels

- Fiscal is onafhankelijk van Sales en Purchasing.
- Sales en Purchasing mogen Fiscal gebruiken voor fiscale classificatie en berekening.
- Fiscal muteert geen Sales-, Purchasing- of Accounting-aggregates.
- Financiële gevolgen lopen uitsluitend via Accounting en `PostingEngine`.
- Fiscal bevat geen Laravel-, database-, repository- of infrastructuurafhankelijkheden.

**Capabilitystatus:** Designed; implementation starts with F1-001.

## 6. Banking

| Naam | Doel | Korte beschrijving |
| --- | --- | --- |
| BankAccount | Een financiële rekening identificeren | Een rekening voor het ontvangen, bewaren en uitbetalen van geld. |
| BankStatement | Een aangeleverd rekeningoverzicht representeren | Een overzicht van bankmutaties over een bepaald tijdvak. |
| BankTransaction | Eén bankmutatie vastleggen | Een bij- of afschrijving met bedrag, datum en omschrijving. |
| Payment | Een bankmutatie aan een OpenItem alloceren | Een positief Money-bedrag dat als child entity binnen één BankTransaction naar precies één OpenItem verwijst. |

### Banking Capability

#### Aggregate en child entity

| Aggregate Root | Child Entity | Verantwoordelijkheid |
| --- | --- | --- |
| BankTransaction | Payment | Een bankmutatie als primaire financiële gebeurtenis vastleggen en haar betalingen beheren. |

Een BankTransaction kan nul, één of meerdere Payments bevatten. Payment is een child entity en wordt uitsluitend via de BankTransaction Aggregate Root beheerd.

#### Domain Service

`Matching` koppelt BankTransactions aan `OpenItem` aggregates uit Accounting. De service ondersteunt de domeinbeslissing welke openstaande post door een bankmutatie wordt voldaan, maar maakt geen JournalEntries en muteert geen OpenItems rechtstreeks.

#### Businessregels

- BankTransaction is binnen Banking de primaire financiële gebeurtenis.
- Een BankTransaction kan nul, één of meerdere Payments bevatten.
- Iedere Payment behoort altijd tot precies één OpenItem.
- Payment-bedragen zijn strikt positief, gebruiken dezelfde Currency als de BankTransaction en zijn alleen wijzigbaar zolang de transactie Imported is.
- De BankTransaction-context is immutable; alleen status en de eigen Payment-collectie wijzigen via domeingedrag.
- Matching vereist minimaal één Payment en vergelijkt de exacte Money-som met het absolute BankTransaction-bedrag.
- Mislukte matching wijzigt niets, Matched opnieuw matchen is idempotent en Posted wordt geweigerd.
- Matching maakt geen Payments, JournalEntries of PostingRequests.
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
OpenItem Closed
```

`Payment` is in deze conceptuele keten geen zelfstandig Aggregate Root, maar een child entity die uitsluitend binnen `BankTransaction` bestaat. De laatste stap bestaat uit de bestaande `OpenItem::settle()`- en `OpenItem::close()`-methoden; Matching muteert het OpenItem niet rechtstreeks.

#### Verantwoordelijkheden en hand-offs

| Stap | Verantwoordelijke | Bestaande service | Hand-off |
| --- | --- | --- | --- |
| SalesInvoice | SalesInvoice Aggregate Root | — | Sales eindigt bij een geldige, gefinaliseerde factuurcontext. |
| PostingRequest | Accounting request-object | — | Draagt JournalId, PostingDate, JournalEntryReference en JournalEntryLines naar Accounting. |
| PostingValidation | Accounting | PostingValidation | Valideert minimaal twee regels, Currency, unieke regelidentiteiten en debet = credit. |
| PostingEngine | Accounting | PostingEngine | Is als enige verantwoordelijk voor het maken en posten van JournalEntry. |
| JournalEntry | JournalEntry Aggregate Root | — | Bewaart de immutable geposte boeking; maakt zelf geen OpenItem. |
| OpenItem | OpenItem Aggregate Root | — | Bewaakt originalAmount, openAmount en vereffening; verwijst naar JournalEntryId. |
| Payment | Child entity van BankTransaction | — | Verwijst met OpenItemId naar precies één OpenItem en draagt een positief Money-bedrag. |
| BankTransaction | BankTransaction Aggregate Root | — | Bewaakt Payment-ownership, Currency en de Imported → Matched → Posted-statusmachine. |
| Matching | Banking | Matching | Valideert de exacte Payment-som en zet alleen een geldige Imported transactie op Matched. |
| OpenItem Closed | OpenItem Aggregate Root | — | Application-orchestratie roept settle(Money) en daarna close() aan; Banking muteert Accounting niet rechtstreeks. |

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
- De end-to-endtest construeert na de factuurboeking een OpenItem uit expliciete factuur- en boekingscontext, koppelt een Payment via OpenItemId en past een succesvol MatchingResult toe via `settle()` en daarna `close()`.
- Matching sluit geen OpenItem en maakt geen boeking; een BankTransaction gebruikt een afzonderlijke PostingRequest via dezelfde PostingEngine.

#### Acceptance en resterende grenzen

- De succesketen en foutpaden voor ongeldige statussen, ongebalanceerde allocaties, te lage en te hoge settlements en voortijdig sluiten zijn met echte domeinobjecten bewezen.
- Money-totalisatie en -vergelijking gebruiken exacte decimale strings zonder floats.
- De drie PostingRequest-use-cases dupliceren beperkt orchestrationpatroon voor totalisatie, beschrijving en twee boekingsregels. Dit is zichtbare technische schuld, maar abstraheren vóór extra stabiele varianten zou de capabilitytaal verbergen.
- De exclusiviteit van PostingEngine als JournalEntry-factory is in productiecode en architectuurregels aantoonbaar, maar nog niet technisch afgedwongen door modulevisibility.
- Auditmetadata, tegenboekingsorchestration, persistence en Reporting-projecties vallen buiten deze Domain-iteratie. Reporting kan veilig starten door een readmodel over geposte JournalEntries en OpenItems te ontwerpen zonder de bewezen write-side aggregategrenzen te wijzigen.

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
- `OpenItem` is een aanvullende bron voor latere openstaande-postenrapportages.
- Fiscal-data is alleen een aanvullende bron waar een fiscaal rapport dat vereist, zoals een latere VAT Overview.
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

#### Latere rapportages

- General Ledger Card: detail en verloop van geposte boekingsregels per LedgerAccount.
- Open Items Report: openstaande bedragen afgeleid met `OpenItem` als aanvullende bron.
- VAT Overview: fiscaal overzicht afgeleid uit geposte boekingen en relevante Fiscal-data.

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
- General Ledger Card, Open Items/Aging Report, VAT Overview, audit-drill-down en exportcontracten zijn aanbevolen vervolgstories.

**Capabilitystatus:** Reporting Foundation completed (R1-004).

### Operational Reporting (R2 design)

Operational Reporting blijft een read-only afleiding en sluit aan op de R1-context en tekenconventie: Administration is verplicht, datum/periode en Currency zijn expliciet, Money blijft de geldrepresentatie en geen rapportuitkomst wordt teruggeschreven.

#### General Ledger Report / Grootboekkaart

- Bronnen: `JournalEntry`, `JournalEntryLine` en `LedgerAccount`.
- Context: AdministrationId, inclusieve startDate/endDate, Currency en optioneel LedgerAccountId.
- Alleen Posted JournalEntries binnen Administration en periode worden opgenomen.
- Iedere regel bevat minimaal postingDate, JournalEntryId, JournalId, reference, LedgerAccountId, debit, credit en running balance; JournalEntryLineId wordt als stabiele trace/tie-breaker aanbevolen.
- Volgorde is postingDate, JournalEntryId en JournalEntryLineId, alle oplopend op canonieke waarde.
- Running balance is exact de cumulatieve periodebeweging `vorige balance + debit - credit`, start in de eerste iteratie op Money-zero en wordt niet opgeslagen. Een boekhoudkundig openingssaldo vóór startDate vraagt later expliciete openingsbalansinput of een voorafgaande Trial Balance.

#### Open Items Report / Openstaande posten

- Bron: `OpenItem` met AdministrationId, RelationId, JournalEntryId, originalAmount, openAmount en status.
- Context: AdministrationId, peildatum, Currency en optioneel RelationId.
- Closed wordt standaard uitgesloten; open en partially settled worden read-only gerapporteerd.
- `openAmount` is Accounting-domeinwaarheid; Reporting berekent geen settlements en muteert OpenItem niet.
- Systeemgat: OpenItem bevat geen ontstaans-/boekings-/vervaldatum en geen gedateerde settlementhistorie. De huidige status en openAmount representeren alleen de actuele toestand. Een betrouwbaar historisch peildatumrapport vereist eerst capability-eigen temporele broninformatie of een herbouwbare, gedateerde projectie uit brongebeurtenissen.

#### VAT Overview / BTW-overzicht

- Beoogde bronnen: Fiscal-classificatie en uitsluitend geposte financiële gegevens met duurzame fiscale trace.
- Vereiste context: AdministrationId, inclusieve periode en Currency; aanvullende jurisdictie-/aangiftecontext volgt pas uit fiscaal ontwerp.
- Huidige Fiscal-objecten (`TaxCode`, `TaxCalculationResult`) zijn niet gekoppeld aan Sales/Purchasing-documentregels of `JournalEntryLine`.
- Systeemgat: geposte regels missen minimaal TaxCodeId, historische TaxRate/effectieve classificatie, taxable base, taxAmount, verkoop-/inkooprichting en trace naar de fiscale brondocumentregel. De huidige invoice-posting bevat geen afzonderlijk fiscaal geclassificeerde btw-regels.
- Conclusie: R2-003 is met de huidige domeinwaarheid niet betrouwbaar implementeerbaar. Reporting mag geen TaxCode-koppeling afleiden uit LedgerAccount, description of actuele TaxCode-rate.

#### Aanbevolen implementatievolgorde

1. R2-001 General Ledger Report, omdat alle minimale immutable bronvelden beschikbaar zijn.
2. Prerequisite voor Open Items: temporele OpenItem-bronwaarheid en peildatumsemantiek ontwerpen; daarna Open Items Report.
3. Prerequisite voor VAT: fiscale classificatie en bedragen immutable door document- en posting chain traceerbaar maken; pas daarna R2-003 VAT Overview.

**R2-status:** Operational Reporting designed; General Ledger is implementation-ready, Open Items en VAT hebben expliciete prerequisites.
