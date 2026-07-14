# Codingstandaarden

## PHP en Laravel

- Gebruik de PHP-versie en dependencies uit `composer.json`; schrijf moderne, strikt getypeerde PHP waar de codebase dat ondersteunt.
- Volg PSR-12 en Laravel-conventies. Laravel Pint is de formatterende bron van waarheid.
- Gebruik betekenisvolle Engelse namen voor code-elementen; gebruik Nederlands voor projectdocumentatie.
- Houd controllers, commands, jobs en listeners dun. Plaats use-cases in de applicationlaag en bedrijfsregels in de domainlaag.
- Gebruik dependency injection en expliciete contracten op architectuurgrenzen.
- Vermijd globale state, verborgen side-effects en generieke helperfuncties voor domeinlogica.

## Financiële domeinregels

- Gebruik geen `float` voor geld; modelleer bedrag, valuta, schaal en afronding expliciet.
- Verwerk definitieve boekingen uitsluitend via de Posting Engine.
- Maak definitieve journaalposten onveranderlijk; corrigeer met nieuwe posten.
- Dwing administratiecontext en autorisatie af bij iedere administratiegebonden operatie.
- Maak herhaalbare financiële opdrachten idempotent en bescherm multi-recordwijzigingen met transacties.

## Database en API

- Maak migrations veilig, voorwaarts uitvoerbaar en waar realistisch omkeerbaar.
- Voeg indexen, constraints en foreign keys toe die integriteit en tenant-isolatie ondersteunen.
- Valideer invoer aan de systeemgrens en geef consistente, niet-lekkende fouten terug.
- Behandel API-contracten als versieerbare publieke interfaces; documenteer breaking changes.

## Tests

- Gebruik unit tests voor bedrijfsregels en feature-/integratietests voor Laravel-, database- en API-gedrag.
- Test happy paths, grensgevallen, foutpaden, autorisatie en administratie-isolatie.
- Reproduceer iedere bug met een falende test voordat de correctie als gereed geldt, indien praktisch mogelijk.
- Tests zijn deterministisch en mogen niet afhangen van volgorde, echte tijd, netwerk of productiegegevens.

## Documentatie en kwaliteit

- Leg het waarom vast; vermijd commentaar dat alleen de code herhaalt.
- Werk relevante documentatie en het [changelog](../docs/CHANGELOG.md) bij wanneer gedrag verandert.
- Voer minimaal relevante tests en Pint uit. Voer statische analyse uit zodra die als projecttool is ingericht.
- Volg voor afronding de [Definition of Done](../docs/PROJECT.md#definition-of-done).
