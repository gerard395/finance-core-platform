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

## 4. Accounting

| Naam | Doel | Korte beschrijving |
| --- | --- | --- |
| LedgerAccount | Financiële waarden classificeren | Een grootboekrekening waarop boekhoudkundige mutaties worden geclassificeerd. |
| Journal | Boekingen naar aard groeperen | Een dagboek voor een herkenbare categorie financiële gebeurtenissen. |
| JournalEntry | Een boekhoudkundige gebeurtenis vastleggen | Een gebalanceerde boeking met een datum, omschrijving en status. |
| JournalLine | Eén boekingsregel representeren | Een debet- of creditbedrag binnen een journaalpost. |
| FiscalYear | Een financieel verslagjaar afbakenen | De periode waarover de financiële administratie formeel rapporteert. |
| AccountingPeriod | Een boekingsperiode beheersen | Een afgebakend tijdvak met een eigen open- of geslotenstatus. |
| OpenItem | Een nog te vereffenen bedrag bewaken | Een financieel bedrag dat nog geheel of gedeeltelijk openstaat. |

## 5. Tax

| Naam | Doel | Korte beschrijving |
| --- | --- | --- |
| TaxCode | Fiscale behandeling classificeren | Een code die een herkenbare fiscale toepassing benoemt. |
| TaxRate | Een belastingpercentage vastleggen | Een percentage dat binnen een fiscale berekening wordt toegepast. |
| TaxPeriod | Een fiscaal aangiftetijdvak afbakenen | De periode waarvoor fiscale bedragen worden vastgesteld. |
| TaxReturn | Een fiscale aangifte representeren | De formele rapportage van verschuldigde en verrekenbare belasting over een tijdvak. |

## 6. Banking

| Naam | Doel | Korte beschrijving |
| --- | --- | --- |
| BankAccount | Een financiële rekening identificeren | Een rekening voor het ontvangen, bewaren en uitbetalen van geld. |
| BankStatement | Een aangeleverd rekeningoverzicht representeren | Een overzicht van bankmutaties over een bepaald tijdvak. |
| BankTransaction | Eén bankmutatie vastleggen | Een bij- of afschrijving met bedrag, datum en omschrijving. |
| PaymentAllocation | Een betaling administratief verdelen | De vastlegging van de bestemming van een betaald of ontvangen bedrag. |

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
