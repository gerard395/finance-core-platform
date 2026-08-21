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

Iedere line-unit-price gebruikt verplicht de documentcurrency bij add, update en reconstitution. `total()` is uitsluitend de exacte afgeleide som van bestaande line totals in die currency en retourneert nul voor een lege Draft. Het is een pre-tax/netto documenttotaal; fiscale bedragen en snapshots blijven buiten dit contract.

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

De eerste Sales-domeiniteratie bevat geen:

- btw-logica; die hoort bij Tax;
- journaalboekingen of Posting Engine; die horen bij Accounting;
- betalingen of openstaande posten; die horen bij Banking respectievelijk Accounting;
- Laravel-, database-, repository- of infrastructuurafhankelijkheden.

## Nummeruitgifte

De vier documentconstructors vereisen hun businessnummer al bij het ontstaan van een Draft. Application alloceert daarom bij documentcreate een typed nummer uit een Administration-scoped, capability-owned Sales-sequence. V1 gebruikt `Q000001`, `O000001`, `F000001` en `C000001` voor respectievelijk Quotation, Order, SalesInvoice en SalesCreditInvoice. Deze persistenceverantwoordelijkheid verandert de frameworkonafhankelijke Sales-aggregates niet.

Succesvol gecommitteerde nummers worden niet gerecycled. Allocation en toekomstige documentpersistence horen in dezelfde transaction boundary; een volledige rollback draait ook de sequence-increment terug. Configureerbare formaten, jaarreset en productievalidatie van actuele wettelijke/fiscale nummeringseisen zijn expliciet vervolgscope.
