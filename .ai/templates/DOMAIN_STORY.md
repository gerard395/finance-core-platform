# <STORY-ID> – <Titel>

## Story ID
`<STORY-ID>`

## Capability
`<Capability>`

## Batch
`<Batch>`

## Doel
<Concreet en toetsbaar doel.>

## Scope
- <Wat wordt toegevoegd of gewijzigd.>

## Buiten scope
- <Wat bewust niet wordt geïmplementeerd.>

## Invarianten
- <Regel die altijd waar blijft.>

## Bestanden
- `<expliciet/toegestaan/pad>`

## Tests
- Gericht: `./vendor/bin/sail artisan test tests/Unit/Domain/<Capability>`
- Volledig: `./vendor/bin/sail artisan test`
- Pint: `./vendor/bin/sail pint --test`
- Diff: `git diff --check`

## Commitbericht
`<STORY-ID> <Commitbericht>`

## Definition of Ready
- Branch en batch zijn benoemd.
- Werkmap is schoon.
- Scope, buiten-scope, invarianten, bestanden en tests zijn expliciet.

## Definition of Done
- Uitsluitend toegestane bestanden zijn gewijzigd.
- Alle validaties slagen.
- Staged scope en whitespace zijn gecontroleerd.
- De Story heeft één lokale commit en is niet tussentijds gepusht.
