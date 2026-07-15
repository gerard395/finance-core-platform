# Shared Identity

## Doel

Shared Identity biedt een kleine, frameworkonafhankelijke representatie van UUID-waarden voor het domein.

## Verantwoordelijkheden

- Een aangeleverde UUID-waarde valideren.
- De waarde normaliseren naar de canonieke lowercase notatie.
- Gelijkheid tussen UUID-waarden bepalen.
- De onveranderlijke waarde als string beschikbaar maken.

## UUID-beleid

- De canonieke notatie is `xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx`.
- Geldige invoer wordt naar lowercase genormaliseerd.
- Alleen UUID-versies 1 tot en met 8 worden geaccepteerd.
- Alleen de RFC-variant met variantnibble `8`, `9`, `a` of `b` wordt geaccepteerd.
- De nil-UUID `00000000-0000-0000-0000-000000000000` is niet toegestaan.
- `toString()` en `__toString()` leveren altijd de canonieke waarde.

## Waarom generatie niet in het Domain hoort

Het genereren van een UUID vereist een bron van willekeur, tijd of een externe implementatie. Dat zijn technische keuzes en daarmee verantwoordelijkheden van de applicatie- of infrastructuurlaag. Het Domain ontvangt uitsluitend een waarde en bewaakt dat deze geldig is. Hierdoor blijft het domein deterministisch en onafhankelijk van libraries en runtimevoorzieningen.

## Toekomstige domeinidentiteiten

Specifieke identity value objects, zoals `AdministrationId`, `CustomerId` en andere toekomstige identifiers, kunnen een `Uuid` gebruiken als hun onderliggende waarde. Zij behouden daarbij hun eigen domeintype en voorkomen dat identifiers van verschillende concepten onderling verwisselbaar worden.

`Uuid` kent deze specifieke identity-types niet en bevat geen domeinspecifieke generatie- of persistentieregels.

## Tests

De unit tests verifiëren geldige lowercase- en uppercase-invoer, versies 1 en 7, gelijkheid, ongelijkheid, stringrepresentatie en afwijzing van ongeldige lengte, tekens, versie, variant en de nil-UUID.
