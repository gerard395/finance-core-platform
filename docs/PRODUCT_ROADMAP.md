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

**Status:** Planned

### Capability Purchasing

Purchasing beheert ontvangen inkoopfacturen en inkoopcreditfacturen. De capability omvat de Aggregate Roots `PurchaseInvoice` en `PurchaseCreditInvoice`, met respectievelijk `PurchaseInvoiceLine` en `PurchaseCreditInvoiceLine` als child entities.

Purchasing modelleert eigen aggregategrenzen en statusgedrag en erft niet van SalesInvoice. Neutrale financiële en regelconcepten worden hergebruikt zonder ze binnen Purchasing te dupliceren: `Money`, `Currency`, `Quantity` en `LineDescription`. Omdat `Quantity` en `LineDescription` momenteel technisch onder Sales staan, worden deze vóór Purchasing-implementatie naar een gedeelde, capabilityneutrale locatie gepromoveerd; Purchasing krijgt geen afhankelijkheid op Sales.

Een PurchaseInvoice doorloopt `Draft → Finalized → Posted → Paid`. Alle financiële mutaties verlopen via Accounting: Purchasing levert een `PostingRequest` aan, `PostingValidation` valideert deze en uitsluitend `PostingEngine` maakt de JournalEntry. Na posten ontstaat via Accounting een OpenItem; PurchaseInvoice maakt nooit zelf JournalEntries of OpenItems.

## Capability 06 – Accounting

**Status:** Planned

### Capability Accounting

Accounting beheert grootboekrekeningen, dagboeken, journaalposten en openstaande posten. De capability omvat de Aggregate Roots `LedgerAccount`, `Journal`, `JournalEntry` en `OpenItem`, met `JournalEntryLine` als child entity van `JournalEntry`.

Alle financiële mutaties verlopen via de `PostingEngine`. Facturen, betalingen en banktransacties leveren daarvoor een `PostingRequest` aan en ontvangen een `PostingResult`; deze application-servicecontracten worden in deze ontwerpstory uitsluitend gedocumenteerd. De `PostingEngine` is de enige component die `JournalEntry`-aggregates mag aanmaken.

De eerste implementatiestories starten bij A5-001. Het Accounting-domein blijft frameworkonafhankelijk en krijgt geen Laravel-, database- of infrastructuurafhankelijkheden.

## Capability 07 – Tax

**Status:** Planned

## Capability 08 – Banking

**Status:** Planned

## Capability 09 – Documents

**Status:** Planned

## Capability 10 – Reporting

**Status:** Planned

## Capability 11 – Workflow

**Status:** Planned

## Capability 12 – Platform

**Status:** Planned

## Releases

- **v0.1 – Platform Foundation**
- **v0.2 – Administration Capability**
- **v0.3 – Relations Capability**
- **v0.4 – Sales Capability**
- **v0.5 – Purchasing Capability**
- **v0.6 – Accounting Capability**
- **v0.7 – Banking + Tax**
- **v0.8 – Reporting**
- **v0.9 – Workflow**
- **v1.0 – Production Ready**
