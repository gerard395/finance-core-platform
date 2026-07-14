# Rollen en samenwerking

Deze rollen beschrijven verantwoordelijkheden. Eén persoon kan meerdere rollen vervullen, maar besluitvorming en review blijven expliciet.

## Product Owner

**Mandaat:** bepaalt productwaarde, prioriteit en acceptatie van functionele scope.

- beheert visie, roadmap, epics en backlogvolgorde;
- formuleert en verduidelijkt acceptatiecriteria;
- bewaakt versie-1.0-scope en betrekt inhoudsdeskundigen;
- accepteert of verwerpt het functionele resultaat;
- beslist niet alleen over technische architectuur of het omzeilen van kwaliteits- en beveiligingseisen.

## Chief Architect

**Mandaat:** bewaakt systeembrede architectuur, kwaliteitsattributen en technische risico's.

- beheert architectuurprincipes en het ADR-proces;
- beoordeelt modulegrenzen, tenant-isolatie, security, data-integriteit en integraties;
- bewaakt de framework-onafhankelijke Accounting Engine en centrale Posting Engine;
- keurt brede, moeilijk omkeerbare technische keuzes goed;
- stemt productimpact af met de Product Owner en uitvoerbaarheid met de Technical Lead.

## Technical Lead

**Mandaat:** vertaalt goedgekeurde scope en architectuur naar veilig uitvoerbaar werk.

- verfijnt taken, technische acceptatiecriteria en teststrategie;
- bewaakt codingstandaarden, reviews, CI en Definition of Done;
- coördineert implementatie, dependencies, migraties en releasegereedheid;
- escaleert architectuurkeuzes naar de Chief Architect en scopevragen naar de Product Owner;
- accepteert geen verborgen technische schuld of onbewezen financiële integriteit.

## Codex

**Mandaat:** voert expliciet opgedragen repositorywerk uit binnen de gegeven scope en rapporteert controleerbaar.

- leest eerst projectcontext, relevante instructies, taak en bestaande code/documentatie;
- maakt redelijke, reversibele aannames en meldt materiële aannames;
- behoudt gebruikerswerk en wijzigt geen ongerelateerde bestanden;
- implementeert klein, consistent en testbaar volgens architectuur en standaarden;
- voert passende controles uit en rapporteert resultaten, bestanden en resterende risico's;
- commit, pusht, merget, publiceert of verricht destructieve acties alleen na expliciete opdracht;
- neemt geen product-, architectuur- of compliancebesluit buiten het gedelegeerde mandaat.

## Samenwerkingsvolgorde

1. De Product Owner bepaalt probleem, waarde, prioriteit en acceptatiecriteria.
2. De Chief Architect borgt richting en noodzakelijke ADR's.
3. De Technical Lead maakt het werk uitvoerbaar en bewaakt kwaliteit.
4. Codex voert de expliciete taak uit, verifieert en rapporteert.
5. De verantwoordelijke rollen reviewen en accepteren het resultaat volgens hun mandaat.
