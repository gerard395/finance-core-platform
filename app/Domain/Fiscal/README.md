# Fiscal Domain

**Status:** Fiscal posting trace and reversal contract completed for R2

## Doel

Fiscal biedt capabilityneutrale fiscale classificatie, berekening en geposte fiscale trace zonder framework- of infrastructuurafhankelijkheden. `TaxCode` beheert classificatie, `TaxCalculation` berekent fiscale Money-bedragen en `TaxPosting` bewaart de immutable transactiesnapshot.

## TaxCode

TaxCode beheert een immutable identiteit, code en Input/Output-richting, een wijzigbare naam, precies één actueel immutable TaxRate en een actieve of inactieve status. Administration ownership ligt buiten de Domain entity en wordt door Application en persistence bewaakt.

Internationale fiscale betekenis kan niet door TaxRate worden gedragen: zero-rated,
EU B2B reverse charge, exempt en outside-scope kunnen alle nul Nederlandse btw
berekenen maar blijven afzonderlijke treatments. W4B-004B ontwerpt daarom een typed
`TaxTreatment` plus afzonderlijke VAT-return- en ICP-classificatie als toekomstige
immutable catalogus- en postingtruth. Geen treatment wordt uit code, naam, rate, land
of btw-ID afgeleid.

## TaxPosting

TaxPosting is Fiscal-owned bronwaarheid die zelfstandig TaxCodeId, gebruikte TaxRate, taxable base, taxAmount, Input/Output-richting, Original/Reversal-type, bron-document en bron-regel, AdministrationId, PostingDate en de exacte JournalEntry/base-/tax-regelidentiteiten verklaart. De base-regel is altijd verplicht; de tax-regel bestaat uitsluitend bij een positief taxAmount. Alle context is immutable. Een Reversal verwijst via `reversedTaxPostingId` naar het oorspronkelijke feit zonder dat feit te muteren.

De geplande treatment/reportinguitbreiding behoudt ook bij tax amount nul een
TaxPosting met taxable base, fiscal/supply date en expliciete VAT/ICP-classificatie.
VAT-return en ICP worden later uit dezelfde append-only facts opgebouwd; een credit
neemt de oorspronkelijke classificatie exact over.

## Invarianten

- TaxCodeId en TaxCodeCode zijn immutable.
- TaxCode-richting gebruikt `TaxPostingDirection`, is immutable en voorkomt selectie via code- of naamheuristiek.
- TaxCodeName is verplicht en kan uitsluitend via `rename()` wijzigen.
- TaxRate is een immutable decimal-stringpercentage tussen 0.0000 en 100.0000 met maximaal vier decimalen.
- `changeRate()` vervangt het actuele tarief; historische tarieven vallen buiten F1-001.
- TaxCodes hebben in v1 geen effective-dated rate history. Een wijziging beïnvloedt toekomstige selectie, nooit bestaande TaxPostings.
- Activeren en deactiveren zijn idempotent.
- TaxPosting-bedragen zijn niet-negatief en gebruiken exact dezelfde Currency.
- TaxPosting bevat altijd een baseJournalEntryLineId.
- Een positief taxAmount vereist taxJournalEntryLineId; bij canonieke nul moet taxJournalEntryLineId null zijn.
- TaxPosting voert geen TaxCalculation of boekingslogica uit.
- Original vereist een null reversedTaxPostingId; Reversal vereist een target-ID.
- Reversalbedragen blijven niet-negatief en Input/Output-direction wordt niet omgekeerd.
- `TaxPostingReversalPolicy::assertCanReverseOriginal()` valideert vóór financiële posting uitsluitend Original en aangeleverde historie; toekomstige Accounting-identiteiten zijn niet nodig.
- `TaxPostingReversalPolicy::assertValidReversal()` valideert na succesvolle posting het volledig geconstrueerde Reversal-record, inclusief target en exacte snapshotgelijkheid.
- De historyguard bevat geen persistence of globale state. Concurrency-safe afdwinging volgt later via een persistenceconstraint en transactie.
- `TaxPostingIdentityPolicy` bewaakt dat iedere nieuwe TaxPostingId vrij is binnen de aangeleverde consistente history; Application bewaakt daarnaast duplicaten binnen dezelfde orchestration.
- Alle vier fiscale invoice- en credit-orchestrators voeren deze identiteitscontroles uit vóór `PostingEngine`. Daardoor kan een geweigerde identiteit geen financiële boeking veroorzaken.

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
- De duurzame catalogus is Administration-scoped. Alleen actieve codes met de gevraagde direction worden aangeboden. De expliciete Nederlandse bootstrap maakt create-missing-only de actuele 21%-, 9%- en 0%-Outputcodes; zij kiest nooit een default of productclassificatie en wijzigt bestaande codes niet.
- Het 0%-tarief is niet hetzelfde als vrijstelling. Het huidige model heeft geen aparte vrijstellingssemantiek; de Nederlandse bootstrap maakt daarom geen vrijstellingscode.
- Zonder effective dating is bootstrap geen rate-updater. Wettelijke tariefwijzigingen vereisen expliciete catalogusversioning en veranderen nooit historische TaxPosting-snapshots.
- Globale, concurrency-safe uniciteit van TaxPostingId en dubbele-reversalpreventie worden later door persistenceconstraints en transacties afgedwongen; de huidige policies bewaken een volledig en consistent aangeleverde historie.
