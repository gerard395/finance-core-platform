# Ontwikkelregels

## Algemene regels

- werk altijd binnen de huidige repository;
- wijzig geen applicatiecode zonder expliciete opdracht;
- documenteer nieuwe keuzes en structuren;
- houd wijzigingen klein, traceerbaar en testbaar.
- respecteer ongerelateerde wijzigingen in de working tree;
- voeg geen dependency, externe service of breaking change toe zonder expliciete noodzaak en beoordeling;
- behandel financiële en persoonsgegevens als gevoelig.

## Workflowregels

- voer eerst een analyse uit voordat je een wijziging implementeert;
- gebruik duidelijke commit- en branchnamen;
- blijf in lijn met de projectdoelen en sprintscope;
- houd de codebase consistent met bestaande conventies.
- lees eerst relevante ADR's, architectuur- en moduledocumentatie;
- werk acceptatiecriteria en relevante tests af voordat een taak gereed is;
- actualiseer changelog en documentatie wanneer gedrag, contracten of keuzes wijzigen.

## Kwaliteitsregels

- schrijf tests voor nieuwe kernfunctionaliteit;
- controleer relevante documentatie bij functionaliteitswijzigingen;
- vermijd onnodige afhankelijkheden en technische schulden.
- dwing administratie-isolatie en autorisatie ook af in negatieve tests;
- gebruik transacties, idempotentie en exacte geldrepresentatie waar financieel relevant;
- voer formattering, tests en statische analyse uit die bij de wijziging horen;
- meld controles die niet konden worden uitgevoerd.

## Verboden zonder expliciete opdracht

- bestaande gebruikerswijzigingen verwijderen of overschrijven;
- geheimen, productiegegevens of persoonsgegevens vastleggen;
- destructieve database- of Git-acties uitvoeren;
- definitieve financiële records muteren buiten de Posting Engine;
- scope uitbreiden met opportunistische refactors.
