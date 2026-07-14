# Finance Core Platform — projectoverzicht

## Huidige status

**Fase:** Foundation
**Doelrelease:** 1.0
**Status:** ontwerpbaseline gereed; projectfoundation in uitvoering

Finance Core Platform bevindt zich in de projectfoundationfase. De functionele scope en architectuurprincipes zijn vastgelegd in de [ontwerpbaseline](DESIGN_COMPLETE_v1.0.md). De repository bevat een Laravel-basisproject; productfunctionaliteit is nog niet als afgerond aangemerkt.

## Huidige sprint

- **Sprint:** [Sprint 000 — Foundation](sprint/sprint-000.md)
- **Doel:** een controleerbare organisatorische en technische basis leggen voor de eerste ontwikkelcycli.
- **Focus:** documentatie, architectuurbesluiten, ontwikkelworkflow en backloggereedheid.

## Huidige epic

- **Epic:** Platform Foundation & Delivery Readiness
- **Resultaat:** het team kan vanaf Sprint 001 veilig, herhaalbaar en volgens gedeelde afspraken productfunctionaliteit leveren.

## Technische stack

- PHP `^8.3`; PHP 8.5 in de huidige Laravel Sail-runtime
- Laravel `^13.8`
- MySQL 8.4 en Redis via Docker/Laravel Sail
- PHPUnit `^12.5` voor geautomatiseerde tests
- Laravel Pint voor codestijl
- Tailwind CSS 4 en Vite 8 voor frontendassets
- Composer en npm voor dependencybeheer
- Git en GitHub voor versiebeheer en reviewworkflow

PHPStan, Livewire en Alpine.js maken deel uit van de doelarchitectuur, maar zijn pas onderdeel van de actuele stack nadat ze als dependency zijn toegevoegd.

## Definition of Done

Een werkitem is afgerond wanneer alle toepasselijke punten aantoonbaar zijn afgevinkt:

1. de acceptatiecriteria zijn gerealiseerd en gecontroleerd;
2. relevante tests zijn toegevoegd en alle geautomatiseerde controles slagen;
3. code voldoet aan de [codingstandaarden](../.ai/CODING_STANDARDS.md) en bevat geen bekende kritieke beveiligings- of data-isolatierisico's;
4. administratiecontext, autorisatie, auditbaarheid en financiële integriteit zijn beoordeeld waar relevant;
5. documentatie, API-specificaties en ADR's zijn bijgewerkt waar nodig;
6. de wijziging is gereviewd en alle reviewopmerkingen zijn afgehandeld;
7. de wijziging is via een pull request integreerbaar in een stabiele `main`-branch;
8. er zijn geen onverklaarde fouten, waarschuwingen of openstaande migratiestappen.

## Eerstvolgende taken

1. ADR-001 voor modulaire architectuur en boundaries opstellen.
2. ADR-002 voor administratiecontext en tenant-isolatie opstellen.
3. ADR-003 voor de framework-onafhankelijke Accounting Engine en Posting Engine opstellen.
4. CI-, test- en statische-analysebeleid vaststellen en als taken uitschrijven.
5. Epics voor administratiebeheer, identiteit en autorisatie verfijnen tot taken voor Sprint 001.
6. Lokale ontwikkel- en onboardinginstructies valideren en documenteren.

## Relevante documenten

- [Ontwerpbaseline](DESIGN_COMPLETE_v1.0.md)
- [Roadmap](ROADMAP.md)
- [Architectuur](architecture/README.md)
- [API-overzicht](api/README.md)
- [AI-projectcontext](../.ai/PROJECT_CONTEXT.md)
