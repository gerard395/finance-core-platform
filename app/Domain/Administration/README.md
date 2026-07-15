# Administration Domain

## Aggregate Root

`Administration` is de frameworkonafhankelijke Aggregate Root voor een zelfstandige administratieve eenheid binnen Finance Core Platform. Een Administration is nadrukkelijk geen juridische organisatie.

## Verantwoordelijkheden

- De onveranderlijke identiteit en code van de administratieve eenheid bewaken.
- Naam, optionele omschrijving, functionele basisvaluta en status beheren.
- Toestandswijzigingen uitsluitend via expliciet domeingedrag uitvoeren.
- Lokale invarianten binnen de Aggregate Root en haar value objects afdwingen.

## Invarianten

- `AdministrationId` en `AdministrationCode` veranderen niet na constructie.
- Code en naam zijn uitsluitend geldig via hun eigen value objects.
- Een basisvaluta is verplicht.
- Een omschrijving is `null` of een niet-lege waarde zonder omliggende whitespace van maximaal 1000 Unicode-tekens.
- Status is altijd actief of inactief.

## Domeingedrag

- `rename()` wijzigt de naam.
- `changeDescription()` wijzigt of verwijdert de omschrijving.
- `changeBaseCurrency()` wijzigt de functionele basisvaluta.
- `activate()` en `deactivate()` wijzigen de status idempotent.

De basisvaluta mag in deze story worden gewijzigd. Beperkingen na het ontstaan van boekhoudkundige transacties worden later buiten dit aggregate afgedwongen.

## Buiten het Aggregate

Organisation, klanten, leveranciers, facturen, boekingen, opslag, repositories, controllers, API's en frameworkcode vallen buiten dit aggregate en buiten deze story.

Een Organisation is een optioneel toekomstig concept en maakt nog geen deel uit van S1-006.

## Relatie met gedeelde Value Objects

`AdministrationId` biedt de domeinspecifieke identiteit op basis van de gedeelde `Uuid`. `Currency` representeert de functionele basisvaluta zonder dat Administration valutacodes zelf valideert.
