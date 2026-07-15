# Administration Domain

## Doel

Het Administration Domain vormt de frameworkonafhankelijke kern voor administratieve concepten binnen Finance Core Platform.

## Verantwoordelijkheden

- De identiteit van een Administration representeren.
- De Administration als domeinentity modelleren.
- Domeininvarianten bewaken binnen entities en value objects.

## Wat niet in het Domain hoort

- Framework- en transportcode, zoals controllers en HTTP-afhandeling.
- Persistentiemechanismen en infrastructurele implementaties.
- Presentatielogica en configuratie van externe systemen.

## Afhankelijkheden

- Alleen PHP en de eigen Administration-domeintypen zijn toegestaan.
- Het domein is niet afhankelijk van Laravel, Eloquent, HTTP, een database of andere infrastructuur.

## Architectuurregels

- Alle PHP-bestanden gebruiken strict types.
- Entities en value objects blijven frameworkonafhankelijk.
- Entities zijn niet readonly, zodat hun toestand binnen domeinregels kan evolueren; hun identiteit blijft onveranderlijk.
- Value objects zijn immutable en bewaken hun eigen geldigheid.
- Deze laag bevat geen Eloquent Models, migrations of databasecode.
- Deze skeleton bevat geen repositories, services of events.
