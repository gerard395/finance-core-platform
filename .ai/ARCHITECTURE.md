# Architectuurrichtlijnen

## Doel

Deze richtlijnen helpen bij het behouden van een consistente architectuur voor Finance Core Platform.

## Kernprincipes

- houd businesslogica gescheiden van frameworkcode;
- maak gebruik van duidelijke modules en bounded contexts;
- ontwerp voor testbaarheid, onderhoudbaarheid en auditbaarheid;
- vermijd directe koppelingen tussen UI, domainlogic en infrastructuur.

## Aanbevolen structuur

- domain: bedrijfsregels en modellen;
- application: use cases en workflows;
- infrastructure: database, cache, externe services;
- presentation: controllers, views en API-endpoints.

De afhankelijkheidsrichting is `presentation/infrastructure → application → domain`. De domainlaag is framework-onafhankelijk. Laravel verzorgt delivery en infrastructuur, niet de financiële bedrijfsregels.

## Kritieke invarianten

- iedere administratiegebonden resource draagt en controleert een administratie-identiteit;
- administratiecontext is beschikbaar en afgedwongen in web, API, CLI en queues;
- alleen de Posting Engine maakt definitieve financiële boekingen;
- definitieve journaalposten worden nooit stilzwijgend gewijzigd of verwijderd;
- correcties bestaan uit nieuwe, herleidbare boekingen;
- geldbedragen gebruiken exacte representatie, geen floating-pointberekening;
- workflows met herhaling of retries zijn idempotent waar dubbele verwerking schade veroorzaakt;
- auditrecords bevatten actor, administratie, actie, tijdstip en relevante referentie.

## Verplichtingen

- nieuwe functionaliteit moet worden voorzien van documentatie;
- architecturale keuzes moeten traceerbaar zijn;
- wijzigingen impacteren niet zonder beoordeling de accounting core.

## Besluitvorming

Leg nieuwe database-, module-, integratie-, security- of accountingkeuzes met brede impact vast als [ADR](../docs/adr/README.md). Raadpleeg de uitgebreide [architectuurdocumentatie](../docs/architecture/README.md).
