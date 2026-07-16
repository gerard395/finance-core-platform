# Architecture Guard

Deze harde grenzen zijn blokkerend voor implementatie en review.

## Domain

- Domain bevat geen Laravel, Illuminate, Eloquent of infrastructuurcode.
- Repositories en persistence horen niet in Domain.
- Aggregates muteren andere Aggregates niet.
- Child entities worden via hun Aggregate Root beheerd.
- Aggregate-identiteiten zijn immutable.
- Documentstatussen wijzigen alleen via domeinmethoden.
- Shared Domain is niet afhankelijk van capabilities.

## Financiële waarden

- Gebruik geen floats voor geld of hoeveelheden.
- Geldbedragen gebruiken `Money`.
- `Money` kent geen btw, UI-formattering of Sales-concepten.

## Accounting

- Geposte JournalEntries zijn onveranderlijk.
- Correcties verlopen via tegenboekingen.
- Alle financiële mutaties verlopen via `PostingEngine`.
- Facturen, betalingen en banktransacties maken niet rechtstreeks JournalEntries.
- Grootboeksaldi worden berekend en niet als domeinwaarheid opgeslagen.

## Afwijkingen

Een afwijking mag uitsluitend via een expliciete ADR in `.ai/ARCHITECTURE_DECISIONS.md` worden ingevoerd. De ADR beschrijft aanleiding, besluit, alternatieven, gevolgen en migratiepad en wordt vóór de afwijkende implementatie geaccepteerd.
