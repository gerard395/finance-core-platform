# Fiscal Domain

## Doel

Fiscal biedt capabilityneutrale fiscale classificatie en berekening zonder framework- of infrastructuurafhankelijkheden. `TaxCode` beheert classificatie en `TaxCalculation` berekent fiscale Money-bedragen.

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
- TaxCalculation accepteert uitsluitend een actieve TaxCode en retourneert immutable net-, tax- en gross-bedragen met dezelfde Currency.
- TaxAmount wordt exact berekend als NetAmount × TaxRate; GrossAmount is NetAmount + TaxAmount.
- Er wordt niet afgerond. Een uitkomst die niet exact binnen de Money-precisie past, wordt geweigerd; afrondingsbeleid volgt later expliciet.
- JournalEntries en boekingen blijven uitsluitend de verantwoordelijkheid van Accounting en PostingEngine.
- Repositories, persistence, Laravel en infrastructuur vallen buiten F1-001.
