# Accounting Domain

## Doel

Accounting vormt de frameworkonafhankelijke boekhoudkundige kern van Finance Core Platform. Naast grootboek en boekingen beheert Accounting `OpenItem` en diens append-only settlementhistorie, plus Administration-owned `BookYear`- en `AccountingPeriod`-setup voor transactionele PostingDate-locks.

## Verantwoordelijkheden

`LedgerAccount` beheert de identiteit, rekeningcode, naam, het rekeningtype en de actieve of inactieve status van een grootboekrekening. De Aggregate Root ondersteunt hernoemen, activeren en deactiveren.

`Journal` beheert de identiteit, code, naam, het type en de actieve of inactieve status van een dagboek. De Aggregate Root ondersteunt hernoemen, activeren en deactiveren, maar bevat zelf geen journaalposten.

`OpenItem` bewaart immutable openingscontext en beheert immutable `OpenItemSettlement`-children. `RelationId` identificeert de tegenpartij; `OpenItemType` legt de subledger Receivable/Payable vast en `OpenItemSide` de onafhankelijke Debit/Credit-polariteit. `controlLedgerAccountId` bewaart de historische LedgerAccount waarop de oorspronkelijke debiteur-/crediteurpositie in de source JournalEntry is geboekt. OriginalAmount blijft een positieve magnitude. Applied settlements, Reversals en cross-item `OpenItemMatch`-facts worden uitsluitend toegevoegd. Actueel en historisch open bedrag en status worden steeds afgeleid uit `originalAmount` en deze historie; zij worden niet als zelfstandige financiële waarheid gemuteerd.

Nieuwe `JournalEntry`-businessstate ontstaat via de bestaande constructor en lifecycle; `PostingEngine` valideert de posting, voegt regels toe aan Draft en post daarna. `JournalEntry::reconstitute()` is uitsluitend een hydration-grens voor reeds bestaande feitelijke state. Zij ontvangt alle typed state en regels tegelijk, simuleert geen lifecyclemethoden en behoudt de onveranderlijkheid van een herstelde Posted entry. Posted snapshots worden met de bestaande `PostingValidation` op minimumregels, Currency, unieke regelidentiteiten en balans gecontroleerd.

Nieuwe `OpenItem`-businessstate ontstaat via de constructor; nieuwe settlementfeiten uitsluitend via `applySettlement()` en `reverseSettlement()`. `OpenItem::reconstitute()` herstelt daarentegen bestaande typed basisstate en volledige settlementhistorie in één side-effectvrije stap. Zij sorteert en valideert de auditfeiten zonder commands te replayen; open bedrag en status blijven uitsluitend afleidingen van originalAmount en historie.

`openAmountAt()` en `statusAt()` zijn de enige bron voor historische openstand en status. Reporting leest deze API's en reconstrueert geen settlementlogica.

`BookYear` bezit custom `AccountingPeriod`-children. Code en grenzen van het BookYear en
de periodranges zijn immutable; alleen labels wijzigen expliciet. Een compleet periodplan
dekt het hele BookYear zonder gaps of overlap. Perioden hebben uitsluitend `Open` en
`Closed`; Close/Reopen vereist reason, actor en authoritative timestamp en bewaart
append-only history. `AccountingPeriodPostingGuard` controleert binnen de outer financial
transaction uitsluitend AdministrationId + werkelijke PostingDate. Closed, NoPeriod en
IntegrityFailure falen vóór financiële writes. FiscalReportingDate en VAT-filinglocks
vallen buiten dit accountingcontract.

## Invarianten

- De identiteit en rekeningcode zijn onveranderlijk.
- Het rekeningtype verandert niet binnen A5-001.
- De naam is verplicht en bevat 2 tot en met 255 Unicode-tekens zonder leading of trailing whitespace.
- De rekeningcode bevat 2 tot en met 16 ASCII-letters of cijfers en wordt genormaliseerd naar uppercase.
- Activeren en deactiveren zijn idempotent.
- Aparte debiteur-, crediteur- en btw-rekeningtypen vallen buiten A5-001.
- De identiteit, code en het type van een Journal zijn onveranderlijk.
- De Journal-naam is wijzigbaar en bevat 2 tot en met 255 Unicode-tekens zonder leading of trailing whitespace.
- De Journal-code bevat 2 tot en met 16 ASCII-letters of cijfers en wordt genormaliseerd naar uppercase.
- Journal activeren en deactiveren zijn idempotent.
- OpenItem ontstaat na een geposte bron-JournalEntry en gebruikt diens PostingDate als `openedOn`.
- OpenItemType is verplicht en immutable; het wordt nooit afgeleid uit RelationId, bedrag, grootboekrekening of JournalEntry.
- OpenItemSide is verplicht en immutable: Receivable/Debit is een vordering en Receivable/Credit een customer credit balance; dit laatste is geen Payable.
- ControlLedgerAccountId is verplicht, immutable en same-tenant. Creators leveren de daadwerkelijk gebruikte postingaccount; persistence en latere consumers leiden deze nooit af uit actuele configuratie, rekeningcode, naam of typeheuristiek.
- Deactivation of rename van een historische control LedgerAccount verandert het OpenItem niet en veroorzaakt geen automatische replacement.
- Een OpenItemMatch verbindt uitsluitend same-tenant, same-Relation, same-Currency OpenItems van hetzelfde type en tegengestelde zijde. Beide open magnitudes dalen op de matchdatum.
- Settlementbedragen zijn strikt positief, gebruiken dezelfde Currency als het OpenItem en hebben een geposte JournalEntry als bron.
- Een Applied settlement mag de chronologische openstand niet negatief maken.
- Een Reversal draait één bestaand Applied settlement eenmaal en volledig terug; het oorspronkelijke feit blijft ongewijzigd.
- Settlementhistorie is deterministisch geordend op effectiveDate en daarna settlement-ID.
- BookYears overlappen niet binnen één Administration; gaps tussen BookYears zijn toegestaan.
- Een compleet AccountingPeriod-plan dekt exact het BookYear zonder gaps of overlap.
- Close en Reopen muteren current status en appenden audit atomair; financiële feiten blijven immutable.
- Een posting vereist exact één Open AccountingPeriod voor de werkelijke PostingDate.

## Geen opgeslagen saldo

Een LedgerAccount bevat geen saldo. Een grootboeksaldo wordt later berekend uit geposte `JournalEntry`-regels, zodat er geen tweede, mogelijk afwijkende bron van financiële waarheid ontstaat.

## Uniciteit van rekeningcodes

De Aggregate Root kan uitsluitend zijn eigen toestand bewaken en kent andere LedgerAccounts niet. Uniciteit van Journal- en LedgerAccount-codes wordt daarom tenant-scoped in Application gecontroleerd en definitief door database-uniciteit op `(administration_id, code)` afgedwongen.

## Grenzen

- `Journal` groepeert journaalposten en is geen onderdeel van LedgerAccount.
- `JournalEntry` legt gebalanceerde financiële mutaties vast en is geen child entity van LedgerAccount.
- Journal bevat geen JournalEntries; JournalEntry verwijst later uitsluitend naar het relevante dagboek.
- Journal kent geen `NumberSequence`; nummerreeksen en hun koppeling aan dagboeken vallen buiten A5-002.
- `PostingEngine` verwerkt financiële mutaties en mag LedgerAccount niet verantwoordelijk maken voor boekingsregels.
- Accounting bevat geen opgeslagen grootboeksaldi, btw-logica, repositories, database- of Laravel-afhankelijkheden.
- Banking muteert OpenItem niet. Application orkestreert een settlement pas na de succesvolle financiële posting en levert de werkelijke geposte JournalEntry als bron aan.
## Productmatig masterdatabeheer

Journal en LedgerAccount zijn Administration-owned masterdata. Identity, code en type
zijn immutable; naam en status wijzigen uitsluitend expliciet. Active records kunnen
voor nieuwe configuratie en posting worden gebruikt. Inactive records blijven bestaan
voor historische references en worden nooit hard verwijderd.

De product-UI maakt geen rekeningschematemplate, openingsbalans of automatische mapping.
Deactivation van gebruikte configurationmasterdata maakt readiness expliciet ongeldig;
er is geen silent replacement. Mutation-auditlogging blijft een cross-cutting vervolg.
