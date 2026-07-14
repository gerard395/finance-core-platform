# API-documentatie

## Doel

Deze map bevat de eerste structuur voor API-documentatie van Finance Core Platform.

## Richting

De API wordt ontworpen als een beheersbare interface voor:

- webapplicatiefunctionaliteit;
- interne integraties;
- toekomstige externe koppelingen.

## Eerste afspraken

- publieke API-endpoints krijgen een expliciete versie, bijvoorbeeld `/api/v1`;
- authenticatie, autorisatie en administratiecontext zijn verplicht;
- requestvalidatie faalt voorspelbaar en veldgericht;
- JSON-responses en foutmeldingen zijn consistent en machineleesbaar;
- paginering, filtering, sortering en datum-/geldnotatie zijn uniform;
- muterende acties zijn idempotent waar dubbele verwerking financiële impact kan hebben;
- breaking changes vereisen een nieuwe API-versie en migratieadvies;
- gevoelige gegevens worden niet onnodig gelogd of teruggestuurd.

## Aanbevolen inhoud

- endpoint-overzicht;
- request- en response-structuren;
- authenticatieflow;
- foutcodes en statussen;
- voorbeelden voor belangrijke flows.

Er zijn nog geen stabiele API-contracten gepubliceerd. Voeg een endpoint pas aan deze documentatie toe wanneer contract, autorisatie, administratie-isolatie en tests zijn vastgesteld.
