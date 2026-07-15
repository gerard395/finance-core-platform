# Identity Domain

## Doel

Het Identity Domain beheert de domeinidentiteit en levenscyclus van gebruikers binnen Finance Core Platform. `User` is de Aggregate Root.

## Verantwoordelijkheden

- De onveranderlijke `UserId` bewaken.
- Een geldige displaynaam en een geldig, genormaliseerd e-mailadres garanderen.
- Naams- en e-mailwijzigingen via expliciet domeingedrag uitvoeren.
- De actieve of inactieve gebruikersstatus idempotent beheren.

## Invarianten

- Iedere User heeft precies één onveranderlijke `UserId`.
- De displaynaam bevat 2 tot en met 255 Unicode-tekens zonder omliggende whitespace.
- Het e-mailadres is syntactisch geldig en wordt in lowercase opgeslagen.
- Alleen `Active` en `Inactive` zijn geldige statussen.
- Activeren en deactiveren zijn idempotent.

## Afhankelijkheden en grenzen

Het Domain gebruikt uitsluitend native PHP en de gedeelde `Uuid`. Laravel, Illuminate, Eloquent, databaseopslag, repositories en infrastructuur vallen buiten deze laag. Uniekheid van e-mailadressen vereist externe gegevens en wordt daarom niet door `EmailAddress` gecontroleerd.

## AdministrationMembership

`AdministrationMembership` modelleert de relatie tussen een `User` en een `Administration` zonder authenticatie, rollen of rechten toe te voegen. De relatie heeft een onveranderlijke identiteit, UserId, AdministrationId en geldigheidsperiode. Alleen de actieve status kan via expliciet, idempotent domeingedrag wijzigen.

Een membership is op een moment geldig wanneer het actief is en het moment binnen de inclusieve periode van `validFrom` tot en met `validUntil` valt. De einddatum mag niet voor de begindatum liggen. Een eigen DateRange value object volgt eventueel in een latere story.

## Role

`Role` is een zelfstandige Aggregate Root voor een benoemde rol binnen het Identity Domain. De identiteit en code zijn onveranderlijk; de naam kan via expliciet domeingedrag wijzigen. De optionele beschrijving bevat maximaal 1000 Unicode-tekens en gebruikt `null` wanneer zij ontbreekt. Activeren en deactiveren zijn idempotent.

Permissions, gebruikerskoppelingen en wijzigingen aan AdministrationMembership vallen buiten deze story en worden later afzonderlijk gemodelleerd.
