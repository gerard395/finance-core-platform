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

Alle vier headers kunnen een immutable customersnapshot met CustomerId, RelationId, CustomerNumber en DisplayName bewaren. Quotation en Order hebben in v1 geen address- of taxsnapshot. SalesInvoice bewaart een expliciet geselecteerde Invoice-addresssnapshot en een taxsnapshot per regel. SalesCreditInvoice neemt customer/addresscontext van zijn verplichte source SalesInvoice over en gebruikt voor taxreversal uitsluitend historische TaxPosting-snapshots.

Snapshotselectie gebeurt bij create; er bestaat geen live Relation-, Address- of TaxCode-reference en geen Draft-reselectiemutatie. Application accepteert alleen een actieve same-tenant Customer, een expliciet actieve Invoice-address zonder typefallback en een via de tenantcatalogus resolved actieve Output-TaxCode. Latere rename, deactivation of ratewijziging verandert historische snapshots niet.

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

Voor W4-v1 maakt de generieke `CreateSalesInvoice` uitsluitend directe facturen en zet zij `sourceOrderId` altijd op null. Alleen een afzonderlijke toekomstige `CreateSalesInvoiceFromOrder` mag een bron-Order vastleggen. Confirmed en PartiallyInvoiced zijn daarvoor noodzakelijke maar niet voldoende statusvoorwaarden: factureerbaarheid vereist per OrderLine immutable, traceerbare waarheid over ordered, reeds gefactureerde en resterende Quantity. Draft, FullyInvoiced en Cancelled zijn niet factureerbaar. De toekomstige orchestration moet allocations, nummering, invoicewrites en Orderstatus atomair en concurrency-safe bewaren; tot die capability bestaat is er geen Order→Invoice-conversie of source-input in Web.

De eerste Sales-domeiniteratie bevat geen:

- een eigen btw-rekenengine; berekening blijft bij Fiscal `TaxCalculation`;
- journaalboekingen of Posting Engine; die horen bij Accounting;
- betalingen of openstaande posten; die horen bij Banking respectievelijk Accounting;
- Laravel-, database-, repository- of infrastructuurafhankelijkheden.

## Nummeruitgifte

De vier documentconstructors vereisen hun businessnummer al bij het ontstaan van een Draft. Application alloceert daarom bij documentcreate een typed nummer uit een Administration-scoped, capability-owned Sales-sequence. V1 gebruikt `Q000001`, `O000001`, `F000001` en `C000001` voor respectievelijk Quotation, Order, SalesInvoice en SalesCreditInvoice. Deze persistenceverantwoordelijkheid verandert de frameworkonafhankelijke Sales-aggregates niet.

Succesvol gecommitteerde nummers worden niet gerecycled. Allocation en toekomstige documentpersistence horen in dezelfde transaction boundary; een volledige rollback draait ook de sequence-increment terug. Configureerbare formaten, jaarreset en productievalidatie van actuele wettelijke/fiscale nummeringseisen zijn expliciet vervolgscope.

## Quotation persistence

Quotations worden Administration-scoped duurzaam opgeslagen met hun immutable customer snapshot en aggregate-owned lines. Create alloceert nummer en insert in één transaction; updates laden tenant-scoped en zijn nooit upsert. Listreads filteren/sorteren/pagineren headers SQL-side en detail/aggregate hydration gebruikt uitsluitend `Quotation::reconstitute()`.

De database bewaakt tenant-unieke quotationnummers en same-tenant Customer- en line→Quotation-relaties. Algemene optimistic locking bestaat nog niet. Quotation Web UI, PDF/e-mail, automatische expiry en Order-conversie blijven vervolgscope.
