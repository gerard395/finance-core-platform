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

Sales gebruikt gedeelde `Money` en `Currency` value objects en de Sales-value objects `Quantity` en `LineDescription`. Line totals worden afgeleid met `Money::multiply(string $multiplier)`:

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
