# Git-workflow

## Branches

- `main` blijft stabiel, reviewbaar en releasebaar.
- Werk vanuit een actuele `main` in een kortlevende branch.
- Gebruik `feature/<taak>-<omschrijving>`, `fix/<taak>-<omschrijving>`, `docs/<taak>-<omschrijving>` of `chore/<taak>-<omschrijving>`.
- Combineer geen ongerelateerde wijzigingen in één branch of pull request.

## Commits

- Maak kleine, logisch complete commits in gebiedende wijs.
- Aanbevolen vorm: `type(scope): korte omschrijving`, bijvoorbeeld `docs(project): voeg roadmap toe`.
- Gebruik minimaal de types `feat`, `fix`, `docs`, `test`, `refactor`, `chore` en `ci`.
- Commit geen geheimen, gegenereerde artefacten, lokale configuratie of onbedoelde formatteringswijzigingen.
- Herschrijf of verwijder werk van anderen niet zonder afstemming.

## Pull requests

Een pull request bevat:

- aanleiding, scope en gekoppelde taak;
- uitgevoerde controles en testresultaten;
- risico's, migratie- en rollbackimpact;
- screenshots of contractvoorbeelden waar relevant;
- documentatie- en changelogimpact;
- expliciete aandacht voor tenant-isolatie, autorisatie en financiële integriteit waar relevant.

Minimaal één bevoegde reviewer keurt de wijziging goed. Architectuur-, security- en accountingwijzigingen vereisen review door de passende rol uit [`AGENTS.md`](AGENTS.md). Openstaande reviewopmerkingen zijn opgelost voordat wordt gemerged.

## Integratie en releases

- Merge alleen wanneer CI slaagt en de [Definition of Done](../docs/PROJECT.md#definition-of-done) is behaald.
- Gebruik de door het team gekozen merge-strategie consequent; behoud een leesbare geschiedenis.
- Releases volgen Semantic Versioning en worden vastgelegd in [`docs/CHANGELOG.md`](../docs/CHANGELOG.md).
- Hotfixes volgen dezelfde review- en testregels; urgentie verlaagt de kwaliteitsnorm niet.
