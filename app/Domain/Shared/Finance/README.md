# Shared Finance

## Doel

`Currency` biedt een kleine, frameworkonafhankelijke representatie van een valutacode voor de functionele basisvaluta en transactievaluta binnen het financiële domein.

## Verantwoordelijkheden

- Een aangeleverde valutacode valideren.
- Een geldige code naar hoofdletters normaliseren.
- De canonieke code beschikbaar maken.
- Gelijkheid tussen valutacodes bepalen.

## Invarianten

- Een code bestaat uit exact drie ASCII-letters.
- Interne en externe whitespace is niet toegestaan en wordt niet stilzwijgend verwijderd.
- Een geldige code wordt altijd in hoofdletters bewaard.
- Een `Currency` is volledig immutable.

## Wat niet in Currency thuishoort

`Currency` bevat geen geldbedragen, valutatekens, wisselkoersen, conversielogica, persistentieregels of volledige lijst met toegestane valuta's. Het value object bevestigt alleen dat een waarde de vorm van een valutacode heeft.

## Relatie met Money en Administration

Een toekomstig `Money` value object kan `Currency` gebruiken om de munteenheid van een bedrag expliciet te maken. Een `Administration` kan in een toekomstige story een functionele basisvaluta vastleggen. `Currency` blijft daarbij een zelfstandig gedeeld value object en kent deze domeinconcepten niet.

## Money

`Money` combineert een canonieke decimale bedragstring met precies één bestaande `Currency`. Bedragen gebruiken nooit floating-pointwaarden, ondersteunen maximaal acht decimalen en worden genormaliseerd door overbodige decimale nullen te verwijderen. `zero()` maakt een expliciete nulwaarde voor een Currency.

Money is immutable en biedt in deze story uitsluitend constructie, uitlezen, nulcontrole en gelijkheid. Rekenen, formatteren, btw, afrondingsbeleid en wisselkoersen vallen buiten deze story.

## Officiële valutalijst

Validatie tegen een officiële valutalijst kan later als afzonderlijk beleid worden toegevoegd. Die catalogus hoort niet hardcoded in dit value object en verandert de huidige vormvalidatie niet.
