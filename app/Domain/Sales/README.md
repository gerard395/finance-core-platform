# Sales Capability

**Status:** Completed for first domain iteration

Sales beheert het commerciële documenttraject van offerte tot verkoopfactuur en verkoopcreditfactuur. De capability bevat uitsluitend framework-onafhankelijke domeinlogica.

## Aggregate boundaries

| Aggregate Root | Child entity | Verantwoordelijkheid |
| --- | --- | --- |
| `Quotation` | `QuotationLine` | Een aanbod en de aangeboden regels beheren. |
| `Order` | `OrderLine` | Een directe of uit een offerte ontstane verkoopopdracht beheren. |
| `SalesInvoice` | `SalesInvoiceLine` | Een verkoopfactuur en haar regels beheren. |
| `SalesCreditInvoice` | `SalesCreditInvoiceLine` | Een verkoopcreditfactuur en haar correctieregels beheren. |

Iedere Aggregate Root bewaakt de identiteit en ownership van zijn eigen regels. Regels worden uitsluitend via de Aggregate Root toegevoegd en verwijderd, en dubbele line-identiteiten worden geweigerd. Een document kan zijn relevante definitieve overgang alleen maken wanneer minimaal één regel bestaat. Na die overgang zijn line-mutaties geblokkeerd.

`reconstitute()` is voor ieder aggregate de side-effectvrije hydrationgrens. Zij ontvangt alle bestaande headerstate, eindstatus en volledige linecollectie tegelijk, replayt geen lifecycle- of `addLine()`-commands en weigert duplicate line-identities, mixed currency en onmogelijke niet-Draft state zonder regels. Reconstitution veroorzaakt geen events en biedt geen mutatiebackdoor.

In Draft kunnen bestaande lines via aggregate-owned `updateLine()` met behoud van line identity worden vervangen. Identity, businessnummer, AdministrationId, CustomerId, documentcurrency en bronverwijzingen blijven immutable. Alleen bestaande datumvelden zijn via samenhangende Draft-methoden wijzigbaar: Quotation-datum/expiry en Invoice-datum/due date worden atomair gevalideerd; Order- en CreditInvoice-datum hebben elk één expliciete mutatie. Na de eerste lifecycle-lock zijn ook headerwijzigingen verboden.

Iedere line-unit-price gebruikt verplicht de documentcurrency bij add, update en reconstitution. `total()` is uitsluitend de exacte afgeleide som van bestaande line totals in die currency en retourneert nul voor een lege Draft. Het is een pre-tax/netto documenttotaal. SalesInvoiceLine kan daarnaast een immutable output-taxsnapshot dragen; exacte tax- en gross-totalen worden in Application via de bestaande Fiscal `TaxCalculation` afgeleid.

## Historische snapshots

Sales-documentoutput gebruikt daarnaast expliciete readiness readers. Recipient is een
purpose-scoped preferred Contact zonder fallback. Current issuer legal/trade name,
structured address, contact- en paymentdata worden pas bij artifactgeneratie gesnapshot;
sender From/Reply-To is afzonderlijke Administration-masterdata. Voor Invoice en Credit
blijven immutable historical supplier/customer VAT-ID, jurisdiction, SupplyDate en tax
snapshots altijd leidend boven current Administration-fiscal data.

W4E-002 legt de gebruikte commerciële/fiscale en issuer/paymentpresentatie vast als
een canoniek immutable Sales-render model en bewaart de daadwerkelijke PDF vervolgens
private als `DocumentArtifact`. Een artifact hoort via een type-specifieke harde
same-tenant FK bij exact één Quotation, SalesInvoice of SalesCreditInvoice. De Domain-
metadata bevat geen bytes, filesystempad of public URL. Semantisch gelijke input wordt
per source via een SHA-256 renderfingerprint hergebruikt; iedere gewijzigde zichtbare
input krijgt een nieuwe monotone artifactversion. De afzonderlijke SHA-256 over de
opgeslagen PDF bewaakt byte-integriteit. Artifactgeneratie muteert geen Sales-
aggregate of status en is geen bewijs van delivery.

Alle vier headers kunnen een immutable customersnapshot met CustomerId, RelationId, CustomerNumber en DisplayName bewaren. Quotation bewaart voor nieuwe documenten daarnaast een expliciet geselecteerde Quotation-addresssnapshot; legacy Quotations zonder snapshot blijven leesbaar. Order heeft geen address- of taxsnapshot. SalesInvoice bewaart een expliciet geselecteerde Invoice-addresssnapshot en een taxsnapshot per regel. SalesCreditInvoice neemt customer/addresscontext van zijn verplichte source SalesInvoice over en gebruikt voor taxreversal uitsluitend historische TaxPosting-snapshots.

Snapshotselectie gebeurt bij create; er bestaat geen live Relation-, Address- of TaxCode-reference en geen Draft-reselectiemutatie. Application accepteert alleen een actieve same-tenant Customer, voor Quotation exact een expliciet actief Quotation-adres, voor SalesInvoice exact een expliciet actief Invoice-adres, beide zonder typefallback, en een via de tenantcatalogus resolved actieve Output-TaxCode. Latere rename, deactivation of ratewijziging verandert historische snapshots niet.

SalesInvoice legt bij create document-level immutable customer- en supplier-fiscale
snapshots vast met typed nullable VAT ID en jurisdiction. Een optionele typed
SupplyDate is afzonderlijk van InvoiceDate en wordt nooit uit InvoiceDate of OrderDate
afgeleid. SalesCreditInvoice kopieert partycontext en oorspronkelijke SupplyDate exact
uit de source invoice; haar eigen credit date blijft de correctiedatum.

TaxTreatment en VAT-return/ICP-classificatie blijven immutable per line. Reverse-
charge- en intracommunautaire wording is een typed, treatment-derived
renderinginstructie en geen vrije tekst of rate-heuristiek. Tot de selectorstory gereed
is ondersteunt Sales uitsluitend domestic NL VAT en expliciet BTW0. De selector
beslist nooit automatisch op land, btw-ID, code, naam of nulrate.

De tenant-scoped Output-selector biedt daarnaast expliciet `EUDIENST`,
`ICLGOEDEREN`, `BUITENSCOPE` en `VRIJGESTELD`. Iedere keuze levert rechtstreeks de
immutable treatment/reporting/ICP-snapshot; BTW0 wordt nooit hergebruikt. Directe en
Order-derived create tonen een nullable Prestatiedatum en gebruiken dezelfde typed
readiness. Er is geen default of automatische keuze. Credits blijven volledig
source-derived en hebben geen fiscale selector.

## Statusmachines

```text
Quotation
Draft → Sent → Accepted
Draft → Sent → Rejected
Draft → Expired
Sent → Expired

Order
Draft → Confirmed → PartiallyInvoiced → FullyInvoiced
Draft → Confirmed → FullyInvoiced
Draft → Cancelled
Confirmed → Cancelled

SalesInvoice
Draft → Finalized → Posted → Paid
Draft → Cancelled
Finalized → Cancelled

SalesCreditInvoice
Draft → Finalized → Posted
Draft → Cancelled
Finalized → Cancelled
```

Statusovergangen verlopen uitsluitend via domeinmethoden. Herhaling van dezelfde overgang is idempotent. `Accepted`, `Rejected` en `Expired` zijn eindstatussen van Quotation; `OrderCreated` is geen QuotationStatus.

## Shared value objects en bedragen

Sales gebruikt de gedeelde `Money` en `Currency` value objects uit Shared Finance en de capabilityneutrale `Quantity` en `LineDescription` value objects uit Shared Commerce. Line totals worden afgeleid met `Money::multiply(string $multiplier)`:

- bedragen en aantallen worden als gevalideerde decimale strings verwerkt;
- floating-point-berekeningen zijn uitgesloten;
- de Currency van de unit price blijft behouden;
- Quantity is positief en unit price is niet-negatief;
- btw en korting vallen buiten deze iteratie.

## Orchestratie en capabilitygrenzen

Aggregates muteren elkaar niet. Een geaccepteerde Quotation kan later door de Application-laag naar een Order worden vertaald; dezelfde laag orkestreert Order naar SalesInvoice. Bronidentiteiten leggen alleen herkomst vast.

Voor Quotation→Order geldt in W4A-v1 één volledige conversie: één Accepted Quotation levert maximaal één Draft Order. De Order neemt de immutable customer snapshot, Currency en alle commerciële regelinhoud over, maar krijgt een nieuwe OrderId en nieuwe aggregate-owned OrderLineIds. `Order.sourceQuotationId` is de headertrace; de Quotation blijft Accepted. Directe Orders behouden een null source. Database-uniciteit op de nullable tenant/source-combinatie is de concurrency-safe guard; line-level source-identiteiten worden pas vereist wanneer partial conversion of allocations worden ontworpen.

Voor W4-v1 maakt de generieke `CreateSalesInvoice` uitsluitend directe facturen en zet zij `sourceOrderId` altijd op null. W4B ontwerpt de afzonderlijke `CreateSalesInvoiceFromOrder`: Confirmed en PartiallyInvoiced zijn factureerbaar; Draft, FullyInvoiced en Cancelled niet. Immutable Draft-reservations verminderen beschikbare Quantity zonder Orderstatus te wijzigen. Finalize consumeert reservations naar append-only allocations en leidt PartiallyInvoiced/FullyInvoiced uit finalized quantities af; Draft-cancel schrijft append-only releases. Source-derived Draft-lines zijn commercieel immutable. Nummering, invoice, facts en Orderstatus delen hun relevante outer transaction en de tenant-scoped Order-lock serialiseert quantityconsumptie. Directe invoices blijven source null en allocationvrij.

De eerste Sales-domeiniteratie bevat geen:

- een eigen btw-rekenengine; berekening blijft bij Fiscal `TaxCalculation`;
- journaalboekingen of Posting Engine; die horen bij Accounting;
- betalingen of openstaande posten; die horen bij Banking respectievelijk Accounting;
- Laravel-, database-, repository- of infrastructuurafhankelijkheden.

## Nummeruitgifte

De vier documentconstructors vereisen hun businessnummer al bij het ontstaan van een Draft. Application alloceert daarom bij documentcreate een typed nummer uit een Administration-scoped, capability-owned Sales-sequence. V1 gebruikt `Q000001`, `O000001`, `F000001` en `C000001` voor respectievelijk Quotation, Order, SalesInvoice en SalesCreditInvoice. Deze persistenceverantwoordelijkheid verandert de frameworkonafhankelijke Sales-aggregates niet.

Succesvol gecommitteerde nummers worden niet gerecycled. Allocation en toekomstige documentpersistence horen in dezelfde transaction boundary; een volledige rollback draait ook de sequence-increment terug. Configureerbare formaten, jaarreset en productievalidatie van actuele wettelijke/fiscale nummeringseisen zijn expliciet vervolgscope.

## Quotation persistence

Quotations worden Administration-scoped duurzaam opgeslagen met hun immutable customer- en, voor nieuwe documenten, Quotation-addresssnapshot en aggregate-owned lines. Create valideert en lockt het expliciete same-Relation actieve Offerteadres vóór nummerallocatie; adresvolgorde en Postal-/Invoice-fallback bestaan niet. Nummerallocatie en insert blijven één transaction; updates laden tenant-scoped en zijn nooit upsert. Listreads filteren/sorteren/pagineren headers SQL-side en detail/aggregate hydration gebruikt uitsluitend `Quotation::reconstitute()`. Legacy rows met een volledig null addresssnapshot blijven geldig.

De database bewaakt tenant-unieke quotationnummers en same-tenant Customer- en line→Quotation-relaties. Algemene optimistic locking bestaat nog niet. Quotation Web UI, PDF/e-mail, automatische expiry en Order-conversie blijven vervolgscope.

## Sales invoice persistence

Generic `CreateSalesInvoice` maakt uitsluitend directe facturen, heeft geen source Order-input en bewaart `sourceOrderId = null`. Persistence en `SalesInvoice::reconstitute()` behouden wel een nullable feitelijke source voor de toekomstige afzonderlijke Order-conversieflow.

Customer, expliciet geselecteerde Invoice address en Output-taxdata zijn immutable snapshots. Draft header/line-mutaties, finalize en cancel gebruiken uitsluitend de Domain-lifecycle; exacte net/tax/gross-bedragen gebruiken de bestaande Fiscal-berekening zonder Infrastructure-aritmetiek of rounding. Posted/Paid zijn hier alleen feitelijke persistence/read-statussen: transactionele posting en settlement-owned Paid-commands blijven buiten deze grens.
Sales-documentdelivery bewaart een immutable request met recipient/sender/mailcontent-
snapshots en exact één artifact. Fysieke pogingen zijn append-only; een transactionele
outbox en databaseclaim begrenzen concurrency. `AcceptedByTransport` is geen claim van
aflevering. `OutcomeUnknown` wordt nooit blind automatisch opnieuw verzonden.
