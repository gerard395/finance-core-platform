# Relations Domain

## Doel

Het Relations Domain beheert zakelijke relaties en hun expliciete commerciële classificaties zonder gegevens tussen classificaties te dupliceren.

## Relation

`Relation` is de Aggregate Root voor gedeelde relatiegegevens. Identiteit en code zijn onveranderlijk; de displaynaam en actieve status wijzigen uitsluitend via expliciet domeingedrag.

## Customer

`Customer` classificeert een bestaande Relation als klant. De classificatie bevat uitsluitend een eigen onveranderlijke identiteit, de onveranderlijke RelationId, een onveranderlijk CustomerNumber en een idempotent wijzigbare actieve status.

Alleen een actieve Customer-classificatie betekent dat de Relation momenteel Customer is. Deactiveren verwijdert de huidige classificatie zonder record of identity te wissen; reactiveren gebruikt dezelfde identity en hetzelfde CustomerNumber. Hard delete is geen declassificatie.

Relation-gegevens zoals naam, adres en contactpersonen worden niet in Customer gedupliceerd. Kredietlimieten, betalingscondities, prijsafspraken, adressen en contactpersonen vallen buiten deze story.

## Contact

`Contact` is een child-entity van Relation en geen Aggregate Root. Relation bewaakt het ownership en is de enige grens waarbinnen Contacts worden toegevoegd, opgezocht en verwijderd. Een ContactId komt binnen één Relation maximaal één keer voor; een bestaande Contact wordt nooit stilzwijgend vervangen en het verwijderen van een onbekende ContactId is idempotent.

Customer en toekomstige Supplier-classificaties beheren geen eigen Contacts en dupliceren geen contactgegevens. Beleid voor dubbele e-mailadressen en telefoonnummers volgt in een latere story.

## Supplier

`Supplier` classificeert een bestaande Relation als leverancier. De classificatie bevat uitsluitend een eigen onveranderlijke identiteit, de onveranderlijke RelationId, een onveranderlijk SupplierNumber en een idempotent wijzigbare actieve status.

Alleen een actieve Supplier-classificatie betekent dat de Relation momenteel Supplier is. Deactiveren verwijdert de huidige classificatie zonder record of identity te wissen; reactiveren gebruikt dezelfde identity en hetzelfde SupplierNumber. Customer en Supplier sluiten elkaar niet uit.

Relation-gegevens en Contacts worden niet in Supplier gedupliceerd. Betalingscondities, IBAN, btw-nummer, KvK-nummer, adres, contactpersoon en bankrekening vallen buiten deze story. Uniekheid van SupplierNumber en van de Supplier-classificatie per Relation vereist externe gegevens en wordt later buiten de entity bewaakt.

Customer-/Supplier-status verandert nooit de historische financiële aard van een OpenItem. `OpenItemType::Receivable` en `OpenItemType::Payable` blijven Accounting-owned immutable classificaties, onafhankelijk van latere deactivatie of reactivatie van een commerciële Relation-classificatie.

## Address

`Address` is een child-entity van Relation en geen Aggregate Root. Relation bewaakt ownership, unieke AddressId-waarden en alle toevoeg- en verwijderhandelingen. Een bestaande Address wordt niet stilzwijgend vervangen; verwijderen van een onbekende AddressId is idempotent.

Address ondersteunt bezoek-, post-, factuur- en afleveradressen. Provincie, GPS, BAG, geocoding en inhoudelijke internationale adresvalidatie vallen buiten deze story.

## BankAccount

`BankAccount` is een child-entity van Relation. Relation bewaakt ownership, unieke BankAccountId-waarden en alle toevoeg- en verwijderhandelingen. De identiteit en IBAN zijn onveranderlijk; de rekeningnaam en actieve status wijzigen via expliciet domeingedrag.

IBAN en BIC krijgen uitsluitend structurele validatie. Saldo, transacties, PSD2, CAMT, SEPA, bankvalidatie en betalingsverwerking vallen buiten deze story.

## Architectuurgrenzen

Het Domain gebruikt uitsluitend native PHP en gedeelde domein-value-objects. Laravel, databaseopslag, repositories en infrastructuur vallen buiten deze laag. Uniekheid van CustomerNumber en van de Customer-classificatie per Relation vereist externe gegevens en wordt later buiten de entity bewaakt.
