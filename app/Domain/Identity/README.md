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
