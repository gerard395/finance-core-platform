# Accounting Domain

## Doel

Accounting vormt de frameworkonafhankelijke boekhoudkundige kern van Finance Core Platform. De foundation bevat `LedgerAccount` voor de classificatie van financiële mutaties en `Journal` voor de groepering van journaalposten naar hun aard.

## Verantwoordelijkheden

`LedgerAccount` beheert de identiteit, rekeningcode, naam, het rekeningtype en de actieve of inactieve status van een grootboekrekening. De Aggregate Root ondersteunt hernoemen, activeren en deactiveren.

`Journal` beheert de identiteit, code, naam, het type en de actieve of inactieve status van een dagboek. De Aggregate Root ondersteunt hernoemen, activeren en deactiveren, maar bevat zelf geen journaalposten.

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

## Geen opgeslagen saldo

Een LedgerAccount bevat geen saldo. Een grootboeksaldo wordt later berekend uit geposte `JournalEntry`-regels, zodat er geen tweede, mogelijk afwijkende bron van financiële waarheid ontstaat.

## Uniciteit van rekeningcodes

De Aggregate Root kan uitsluitend zijn eigen toestand bewaken en kent andere LedgerAccounts niet. Uniciteit van rekeningcodes wordt daarom later buiten het aggregate afgedwongen, op een grens waar de volledige verzameling kan worden geraadpleegd.

## Grenzen

- `Journal` groepeert journaalposten en is geen onderdeel van LedgerAccount.
- `JournalEntry` legt gebalanceerde financiële mutaties vast en is geen child entity van LedgerAccount.
- Journal bevat geen JournalEntries; JournalEntry verwijst later uitsluitend naar het relevante dagboek.
- Journal kent geen `NumberSequence`; nummerreeksen en hun koppeling aan dagboeken vallen buiten A5-002.
- De toekomstige `PostingEngine` verwerkt financiële mutaties en mag LedgerAccount niet verantwoordelijk maken voor boekingsregels.
- De huidige foundation bevat geen PostingEngine, boekingen, nummerreeksen, saldi, btw-logica, periodes, repositories, database- of Laravel-afhankelijkheden.
