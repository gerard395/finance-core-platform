# Roadmap naar versie 1.0

## Doel van versie 1.0

Finance Core Platform 1.0 is een productiegeschikt Nederlands financieel administratieplatform voor meerdere strikt geïsoleerde administraties. De release ondersteunt de volledige administratieve cyclus, een framework-onafhankelijke Accounting Engine, een centrale Posting Engine en een controleerbare audittrail.

De fasen zijn richtinggevend. Een fase gaat pas door wanneer de exitcriteria zijn behaald; beveiliging, tenant-isolatie, testen, documentatie en operationele gereedheid lopen door alle fasen heen.

## Fase 0 — Project Foundation (huidig)

**Doel:** een herhaalbare en controleerbare deliverybasis.

- project-, architectuur-, API- en sprintdocumentatie;
- AI-context, ontwikkelregels, codingstandaarden en Git-workflow;
- ADR-proces en eerste architectuurbesluiten;
- lokale Docker/Sail-omgeving en dependencybeleid;
- teststrategie, CI-kwaliteitspoorten en backlogstructuur.

**Exitcriteria:** foundationdocumenten zijn onderling consistent, Sprint 001 is refinement-ready en kernbesluiten hebben een ADR.

## Fase 1 — Platformkern en toegang

**Doel:** veilige toegang tot strikt gescheiden administraties.

- authenticatie, sessiebeheer en wachtwoordherstel;
- gebruikers, rollen en permissions per administratie;
- administratiebeheer, administratiekeuze en centrale administratiecontext;
- tenant-isolatie voor web, API, CLI en queues;
- basisinstellingen, logging en audittrail;
- geautomatiseerde isolatie-, autorisatie- en beveiligingstests.

**Mijlpaal M1:** een gebruiker kan uitsluitend toegewezen administraties veilig beheren.

## Fase 2 — Stamgegevens en commerciële cyclus

**Doel:** verkoop- en inkoopprocessen voorbereiden met consistente stamgegevens.

- relaties, contactpersonen, klanten en leveranciers;
- offertes met versies, statusflow, PDF en omzetting naar orders;
- orders, planning en levering;
- urenregistratie, goedkeuring en factuurvoorstellen;
- documentbeheer met metadata, versiebeheer en veilige toegang.

**Mijlpaal M2:** de commerciële cyclus van relatie tot factureerbaar voorstel werkt binnen één administratie.

## Fase 3 — Financiële kern

**Doel:** financiële transacties correct, traceerbaar en onveranderlijk verwerken.

- rekeningschema, dagboeken, boekjaren en periodes;
- framework-onafhankelijke Accounting Engine;
- centrale Posting Engine voor alle definitieve mutaties;
- verkoop-, credit- en inkoopfacturen;
- betalingen, deelbetalingen, aflettering en openstaande posten;
- correctieboekingen zonder definitieve posten stilzwijgend te wijzigen;
- grootboekkaart, proef- en saldibalans en volledige audittrail.

**Mijlpaal M3:** alle financiële mutaties zijn dubbel geboekt, reproduceerbaar en via de Posting Engine verwerkt.

## Fase 4 — Btw, bank en rapportage

**Doel:** de Nederlandse financiële cyclus completeren.

- btw-codes, tijdvakken, rapportage en correcties;
- aansluiting van btw-rapportage op het grootboek;
- handmatige bankimport en CAMT/MT940 waar relevant;
- herkenningsregels, matching, voorstellen en definitieve bankverwerking;
- dashboards en financiële/operationele rapportages;
- afsluitingscontroles en exportmogelijkheden.

Actuele fiscale eisen worden vóór productiegebruik door een bevoegde inhoudsdeskundige gevalideerd.

**Mijlpaal M4:** een administratie kan een volledige boekingsperiode verwerken, controleren en rapporteren.

## Fase 5 — API en integraties

**Doel:** stabiele, veilige toegang voor interne en externe integraties.

- versieerbare API-contracten en consistente foutafhandeling;
- authenticatie, autorisatie en administratiecontext;
- idempotentie, rate limiting en auditlogging waar relevant;
- documentatie en contract-/integratietests;
- integratieaccounts en gecontroleerde import- en exportflows.

**Mijlpaal M5:** ondersteunde kernprocessen zijn via gedocumenteerde en geteste API-contracten beschikbaar.

## Fase 6 — Release readiness

**Doel:** Finance Core Platform verantwoord als versie 1.0 uitbrengen.

- volledige regressie-, acceptatie- en end-to-endtest;
- security-, privacy-, tenant-isolatie- en dependencyreview;
- performance-, queue- en foutafhandelingstests;
- back-up, herstelproef, monitoring, logging en incidentprocedure;
- migratie-, deployment-, rollback- en releasehandleiding;
- onboarding-, gebruikers- en beheerdocumentatie;
- bekende beperkingen en supportproces vastleggen.

**Mijlpaal M6 / 1.0:** alle kritieke acceptatiecriteria zijn behaald, operationele controles zijn bewezen en de release is formeel goedgekeurd.

## Niet in versie 1.0

Automatische bankkoppelingen, OCR, elektronische facturatie, consolidatie, budgettering, cashflowprognoses, periodieke facturatie, projectadministratie en een accountantportal worden na 1.0 geprioriteerd.

## Voortgang en wijzigingsbeheer

- De actuele status staat in [PROJECT.md](PROJECT.md).
- Sprintdoelen en resultaten staan in [`sprint/`](sprint/).
- Belangrijke technische keuzes worden vastgelegd in [`adr/`](adr/).
- Released wijzigingen worden bijgehouden in [CHANGELOG.md](CHANGELOG.md).
- Scopewijzigingen vereisen beoordeling door Product Owner en Chief Architect.
