# Relations Domain

## Doel

Het Relations Domain beheert zakelijke relaties en hun expliciete commerciële classificaties zonder gegevens tussen classificaties te dupliceren.

## Relation

`Relation` is de Aggregate Root voor gedeelde relatiegegevens. Identiteit en code zijn onveranderlijk; de displaynaam en actieve status wijzigen uitsluitend via expliciet domeingedrag.

## Customer

`Customer` classificeert een bestaande Relation als klant. De classificatie bevat uitsluitend een eigen onveranderlijke identiteit, de onveranderlijke RelationId, een onveranderlijk CustomerNumber en een idempotent wijzigbare actieve status.

Relation-gegevens zoals naam, adres en contactpersonen worden niet in Customer gedupliceerd. Kredietlimieten, betalingscondities, prijsafspraken, adressen en contactpersonen vallen buiten deze story.

## Contact

`Contact` is een child-entity van Relation en geen Aggregate Root. Relation bewaakt het ownership en is de enige grens waarbinnen Contacts worden toegevoegd, opgezocht en verwijderd. Een ContactId komt binnen één Relation maximaal één keer voor; een bestaande Contact wordt nooit stilzwijgend vervangen en het verwijderen van een onbekende ContactId is idempotent.

Customer en toekomstige Supplier-classificaties beheren geen eigen Contacts en dupliceren geen contactgegevens. Beleid voor dubbele e-mailadressen en telefoonnummers volgt in een latere story.

## Architectuurgrenzen

Het Domain gebruikt uitsluitend native PHP en gedeelde domein-value-objects. Laravel, databaseopslag, repositories en infrastructuur vallen buiten deze laag. Uniekheid van CustomerNumber en van de Customer-classificatie per Relation vereist externe gegevens en wordt later buiten de entity bewaakt.
