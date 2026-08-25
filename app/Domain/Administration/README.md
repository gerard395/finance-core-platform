# Administration Domain

Administration is de authoritative current bron voor Sales-documentpresentation. De
interne Administrationnaam blijft onderscheiden van Organisation-handelsnaam en
juridische naam. Structured documentadres, zakelijke contactdata, rekeninghouder en
mail-senderidentity zijn nullable masterdata; bestaande VAT-ID/jurisdictie en
Organisation-KvK/IBAN/BIC worden hergebruikt. Senderidentity is niet de juridische
issuer en bevat geen transportcredentials. Settings blijven transactioneel
last-write-wins; generieke mutation audit en optimistic locking zijn deferred.

## Aggregate Root

`Administration` is de frameworkonafhankelijke Aggregate Root voor een zelfstandige administratieve eenheid binnen Finance Core Platform. Een Administration is nadrukkelijk geen juridische organisatie.

## Verantwoordelijkheden

- De onveranderlijke identiteit en code van de administratieve eenheid bewaken.
- Naam, optionele omschrijving, functionele basisvaluta en status beheren.
- De optionele eigen typed VAT-identiteit en expliciete fiscale jurisdictie beheren.
- Toestandswijzigingen uitsluitend via expliciet domeingedrag uitvoeren.
- Lokale invarianten binnen de Aggregate Root en haar value objects afdwingen.

## Aggregate Invariants

### Administration

- Een Administration heeft altijd precies één `AdministrationId`.
- Een Administration heeft altijd precies één `AdministrationCode`.
- Een Administration heeft altijd precies één `AdministrationName`.
- Een Administration heeft altijd precies één `BaseCurrency`.
- Een Administration heeft maximaal één `Organisation`.
- De identiteit verandert nooit.
- De AdministrationCode verandert nooit.
- De BaseCurrency mag alleen wijzigen zolang er geen boekhoudkundige transacties bestaan.
- `Active` en `Inactive` zijn de enige geldige statussen.
- `attachOrganisation()` accepteert uitsluitend een Organisation wanneer nog geen Organisation gekoppeld is.
- `removeOrganisation()` is idempotent.
- `activate()` is idempotent.
- `deactivate()` is idempotent.
- Een omschrijving is `null` of een niet-lege waarde zonder omliggende whitespace van maximaal 1000 Unicode-tekens.

### Organisation

- Een Organisation bestaat uitsluitend binnen een Administration.
- Organisation is geen Aggregate Root.
- Een Organisation heeft altijd precies één `OrganisationId`.
- DisplayName is verplicht.
- LegalName is optioneel.
- De overige juridische gegevens zijn optioneel.
- Adres, KvK-nummer, btw-nummer, IBAN en BIC worden later door afzonderlijke value objects gemodelleerd.

## Domeingedrag

- `rename()` wijzigt de naam.
- `changeDescription()` wijzigt of verwijdert de omschrijving.
- `changeBaseCurrency()` wijzigt de functionele basisvaluta.
- `activate()` en `deactivate()` wijzigen de status idempotent.

De Aggregate Root staat een verzoek tot wijziging van de basisvaluta toe. De Application- en Accounting-lagen moeten voorkomen dat dit gedrag wordt aangeroepen zodra boekhoudkundige transacties bestaan.

## Organisation binnen het Aggregate

Een `Organisation` is een optionele child entity binnen Administration. De Aggregate Root bewaakt technisch de 0..1-relatie: per Administration kan maximaal één Organisation gekoppeld zijn. Organisation is geen Aggregate Root en wordt uitsluitend via de Administration-grens benaderd.

Een Organisation kan bij constructie worden meegegeven of later expliciet worden gekoppeld. Een bestaande koppeling wordt nooit stilzwijgend vervangen; iedere tweede koppelpoging wordt geweigerd. Verwijderen zonder aanwezige Organisation is idempotent.

Organisation bevat juridische en contactgegevens. Het bestaande VAT-opslagveld wordt door Administration als typed `VatIdentificationNumber` ontsloten; fiscale jurisdictie is afzonderlijke expliciete masterdata en wordt niet uit het vrije adres afgeleid. Fiscale-eenheid- en reportingidentitycontext blijven uitbreidingspunten.

## Buiten het Aggregate

Klanten, leveranciers, facturen, boekingen, opslag, repositories, controllers, API's en frameworkcode vallen buiten dit aggregate.

## Relatie met gedeelde Value Objects

`AdministrationId` biedt de domeinspecifieke identiteit op basis van de gedeelde `Uuid`. `Currency` representeert de functionele basisvaluta zonder dat Administration valutacodes zelf valideert.

## AccountingSettings

`AccountingSettings` groepeert uitsluitend administratiebrede instellingen die invloed hebben op boekhoudkundige verwerking en presentatie: standaardtaal, datumindeling, getalnotatie, decimale precisie en tijdzone.

UI-, dashboard- en persoonlijke gebruikersvoorkeuren horen niet in dit value object. AccountingSettings bevat evenmin een afrondingsengine of Money-implementatie.

De decimale precisie ligt tussen 0 en 8. De productstandaard is 2, maar de aanroeper levert deze waarde altijd expliciet aan; `DecimalPrecision` neemt intern geen standaard aan. Precisie bepaalt hoeveel decimalen worden gebruikt en is niet hetzelfde als afrondingsbeleid.

Beleid voor het wijzigen van AccountingSettings nadat financiële transacties bestaan, wordt in een latere story vastgelegd.

## NumberSequence

`NumberSequence` is een afzonderlijke Aggregate Root voor opeenvolgende documentnummers. De onveranderlijke `NumberSequenceId` biedt de domeinspecifieke identiteit op basis van de gedeelde `Uuid`.

Een actieve nummerreeks genereert een nummer uit uitsluitend prefix, zero-padding en suffix en verhoogt daarbij de teller. Een inactieve nummerreeks weigert generatie zonder de teller te wijzigen. De volgende tellerwaarde blijft via `peekNextNumber()` ook bij een inactieve reeks uitleesbaar. Activeren en deactiveren zijn idempotent.

Jaartallen, datums, uitvoering van het resetbeleid, opslag en concurrencybeheer vallen buiten deze story.
