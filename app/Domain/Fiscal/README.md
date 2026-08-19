# Fiscal Domain

## Doel

Fiscal biedt capabilityneutrale fiscale classificatie, berekening en geposte fiscale trace zonder framework- of infrastructuurafhankelijkheden. `TaxCode` beheert classificatie, `TaxCalculation` berekent fiscale Money-bedragen en `TaxPosting` bewaart de immutable transactiesnapshot.

## TaxCode

TaxCode beheert een immutable identiteit en code, een wijzigbare naam, precies één actueel immutable TaxRate en een actieve of inactieve status.

## TaxPosting

TaxPosting is Fiscal-owned bronwaarheid die zelfstandig TaxCodeId, gebruikte TaxRate, taxable base, taxAmount, Input/Output-richting, bron-document en bron-regel, AdministrationId, PostingDate en de exacte JournalEntry/base-/tax-regelidentiteiten verklaart. De base-regel is altijd verplicht; de tax-regel bestaat uitsluitend bij een positief taxAmount. Alle context is immutable. Een optionele `reversedTaxPostingId` legt een correctierelatie vast zonder het oorspronkelijke feit te muteren.

## Invarianten

- TaxCodeId en TaxCodeCode zijn immutable.
- TaxCodeName is verplicht en kan uitsluitend via `rename()` wijzigen.
- TaxRate is een immutable decimal-stringpercentage tussen 0.0000 en 100.0000 met maximaal vier decimalen.
- `changeRate()` vervangt het actuele tarief; historische tarieven vallen buiten F1-001.
- Activeren en deactiveren zijn idempotent.
- TaxPosting-bedragen zijn niet-negatief en gebruiken exact dezelfde Currency.
- TaxPosting bevat altijd een baseJournalEntryLineId.
- Een positief taxAmount vereist taxJournalEntryLineId; bij canonieke nul moet taxJournalEntryLineId null zijn.
- TaxPosting voert geen TaxCalculation of boekingslogica uit.
- Een reversal is een nieuw immutable feit; de oorspronkelijke TaxPosting blijft ongewijzigd.

## Grenzen

- Fiscal bevat geen landcodes, land-specifieke fiscale regels of btw-aangiftegedrag.
- Fiscal is onafhankelijk van Sales en Purchasing.
- TaxSourceDocumentId en TaxSourceLineId bewaren capabilityneutrale UUID-referenties zonder Sales- of Purchasing-dependency.
- TaxCalculation accepteert uitsluitend een actieve TaxCode en retourneert immutable net-, tax- en gross-bedragen met dezelfde Currency.
- TaxAmount wordt exact berekend als NetAmount × TaxRate; GrossAmount is NetAmount + TaxAmount.
- Er wordt niet afgerond. Een uitkomst die niet exact binnen de Money-precisie past, wordt geweigerd; afrondingsbeleid volgt later expliciet.
- JournalEntries en boekingen blijven uitsluitend de verantwoordelijkheid van Accounting en PostingEngine.
- TaxPosting refereert alleen immutable Accounting-identiteiten; fiscale metadata wordt niet aan PostingRequest of JournalEntryLine toegevoegd.
- Repositories, persistence, Laravel en infrastructuur vallen buiten Fiscal Domain.
