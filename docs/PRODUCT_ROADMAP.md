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

**Status:** In Progress
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

Sales beheert het commerciële traject van offerte tot verkoopfactuur. De capability omvat de Aggregate Roots `Quotation`, `Order`, `SalesInvoice` en `SalesCreditInvoice`, elk met eigen regels, statusovergangen en child lines.

De eerste ontwerpstory is S4-000 – Sales Capability Design. Implementatie volgt in kleine stories per aggregate en workflowstap.

## Capability 05 – Purchasing

**Status:** Planned

## Capability 06 – Accounting

**Status:** Planned

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
