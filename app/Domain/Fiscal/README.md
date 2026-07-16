# Fiscal Domain

## Doel

Fiscal biedt capabilityneutrale fiscale classificatie en berekening zonder framework- of infrastructuurafhankelijkheden. F1-001 introduceert `TaxCode` als Aggregate Root.

## TaxCode

TaxCode beheert een immutable identiteit en code, een wijzigbare naam, precies één actueel immutable TaxRate en een actieve of inactieve status.

## Invarianten

- TaxCodeId en TaxCodeCode zijn immutable.
- TaxCodeName is verplicht en kan uitsluitend via `rename()` wijzigen.
- TaxRate is een immutable decimal-stringpercentage tussen 0.0000 en 100.0000 met maximaal vier decimalen.
- `changeRate()` vervangt het actuele tarief; historische tarieven vallen buiten F1-001.
- Activeren en deactiveren zijn idempotent.

## Grenzen

- Fiscal bevat geen landcodes, land-specifieke fiscale regels of btw-aangiftegedrag.
- Fiscal is onafhankelijk van Sales en Purchasing.
- TaxCalculation en Money-gebaseerde berekening volgen in een afzonderlijke Story.
- JournalEntries en boekingen blijven uitsluitend de verantwoordelijkheid van Accounting en PostingEngine.
- Repositories, persistence, Laravel en infrastructuur vallen buiten F1-001.
