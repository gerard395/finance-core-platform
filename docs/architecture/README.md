# Architectuur

## Doel

De architectuur van Finance Core Platform richt zich op een modulaire, testbare en uitbreidbare implementatie voor financiële processen in Nederland.

## Hoofdprincipes

- multi-administratie is een eerste klasse eis;
- de accounting engine is onafhankelijk van het webframework;
- gegevensisolatie is verplicht per administratie;
- wijzigingen zijn traceerbaar en auditable;
- de applicatie is inzetbaar via Docker en supportbaar in productie.

## Aanbevolen componenten

- Web- en API-laag via Laravel
- Domain- en applicationlaag voor bedrijfslogica
- Accounting engine als kerncomponent voor boekingen en posten
- Data layer met database en cache
- Integratie- en documentlaag voor externe koppelingen

De afhankelijkheidsrichting loopt van presentation en infrastructure via application naar domain. De domainlaag kent Laravel, databases, HTTP en queues niet. Definitieve financiële mutaties lopen uitsluitend via de Posting Engine; terugwerkende wijziging van definitieve journaalposten is niet toegestaan.

## Dwarsdoorsnijdende eisen

- administratiecontext is expliciet en wordt op iedere toegang afgedwongen;
- autorisatie vindt server-side plaats en is standaard gesloten;
- identifiers, geldbedragen, valuta en tijd worden eenduidig gemodelleerd;
- transacties en idempotentie beschermen financiële integriteit;
- persoonsgegevens en financiële gegevens verschijnen niet onnodig in logs;
- relevante acties leveren een onveranderbare, herleidbare auditregistratie op.

Besluiten met blijvende of brede impact worden vastgelegd als [ADR](../adr/README.md). De samenvatting voor AI-assistenten staat in [`.ai/ARCHITECTURE.md`](../../.ai/ARCHITECTURE.md).

## Eerste ontwikkelrichtlijn

Alle nieuwe functionaliteit moet worden ontworpen met aandacht voor:

- autorisatie per administratie;
- testbaarheid;
- traceerbaarheid van financiële mutaties;
- duidelijke boundaries tussen business logic en framework code.
