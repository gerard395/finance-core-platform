# Relations Domain

## Doel

Het Relations Domain beheert zakelijke relaties en hun expliciete commerciële classificaties zonder gegevens tussen classificaties te dupliceren.

## Relation

`Relation` is de Aggregate Root voor gedeelde relatiegegevens. Identiteit en code zijn onveranderlijk; de displaynaam en actieve status wijzigen uitsluitend via expliciet domeingedrag.

Nieuwe Relations ontstaan via de constructor en nieuwe children worden uitsluitend via de bestaande `addContact()`, `addAddress()` en `addBankAccount()`-lifecycle toegevoegd. `Relation::reconstitute()` is uitsluitend de side-effectvrije hydrationgrens voor reeds bestaande feitelijke state. Zij ontvangt Relation-state en alle drie complete childcollecties ineens, behoudt identities en statussen exact en weigert dubbele childidentiteiten zonder `add*()`- of statusgedrag te replayen.

Contact, Address en BankAccount dragen zelf geen RelationId. Hun ownership ontstaat binnen precies één Relation-aggregate en wordt door de persistencecontext met RelationId en AdministrationId en door samengestelde databaseconstraints afgedwongen. Collection `remove*()` betekent alleen verwijderen uit de in-memory aggregatecollectie en impliceert geen database-delete. De v1-duurzame lifecycle gebruikt voor alle drie children `deactivate()` en `activate()` met behoud van dezelfde identity. Een wijziging van de Relation-root bewaart de drie volledig gehydrateerde childcollecties ongewijzigd; iedere childwrite muteert uitsluitend het geadresseerde childrecord.

## Customer

`Customer` classificeert een bestaande Relation als klant. De classificatie bevat uitsluitend een eigen onveranderlijke identiteit, de onveranderlijke RelationId, een onveranderlijk CustomerNumber en een idempotent wijzigbare actieve status.

Alleen een actieve Customer-classificatie betekent dat de Relation momenteel Customer is. Deactiveren verwijdert de huidige classificatie zonder record of identity te wissen; reactiveren gebruikt dezelfde identity en hetzelfde CustomerNumber. Hard delete is geen declassificatie.

Relation-gegevens zoals naam, adres en contactpersonen worden niet in Customer gedupliceerd. Kredietlimieten, betalingscondities, prijsafspraken, adressen en contactpersonen vallen buiten deze story.

## Contact

`Contact` is een child-entity van Relation en geen Aggregate Root. Relation bewaakt het ownership en is de enige grens waarbinnen Contacts worden toegevoegd, opgezocht en verwijderd. Een ContactId komt binnen één Relation maximaal één keer voor; een bestaande Contact wordt nooit stilzwijgend vervangen en het verwijderen van een onbekende ContactId is idempotent.

Customer en Supplier-classificaties beheren geen eigen Contacts en dupliceren geen contactgegevens. Alleen ContactId is uniek; gelijke namen, e-mailadressen en telefoonnummers zijn bewust toegestaan zolang geen expliciete businessregel anders bepaalt.

## Supplier

`Supplier` classificeert een bestaande Relation als leverancier. De classificatie bevat uitsluitend een eigen onveranderlijke identiteit, de onveranderlijke RelationId, een onveranderlijk SupplierNumber en een idempotent wijzigbare actieve status.

Alleen een actieve Supplier-classificatie betekent dat de Relation momenteel Supplier is. Deactiveren verwijdert de huidige classificatie zonder record of identity te wissen; reactiveren gebruikt dezelfde identity en hetzelfde SupplierNumber. Customer en Supplier sluiten elkaar niet uit.

Relation-gegevens en Contacts worden niet in Supplier gedupliceerd. Betalingscondities, IBAN, btw-nummer, KvK-nummer, adres, contactpersoon en bankrekening vallen buiten deze story. Uniekheid van SupplierNumber en van de Supplier-classificatie per Relation vereist externe gegevens en wordt later buiten de entity bewaakt.

Customer-/Supplier-status verandert nooit de historische financiële aard van een OpenItem. `OpenItemType::Receivable` en `OpenItemType::Payable` blijven Accounting-owned immutable classificaties, onafhankelijk van latere deactivatie of reactivatie van een commerciële Relation-classificatie.

## Address

`Address` is een child-entity van Relation en geen Aggregate Root. Relation bewaakt ownership, unieke AddressId-waarden en alle toevoeg- en verwijderhandelingen. Een bestaande Address wordt niet stilzwijgend vervangen; verwijderen van een onbekende AddressId is idempotent.

Address ondersteunt bezoek-, post-, factuur- en afleveradressen. Provincie, GPS, BAG, geocoding en inhoudelijke internationale adresvalidatie vallen buiten deze story.

AddressId en AddressType blijven onveranderlijk. `changeDetails()` wijzigt de twee adresregels, postcode, plaats en landcode als één expliciete businessoperatie met bestaande immutable value objects. De operatie is idempotent en staat los van `activate()`/`deactivate()`: contentwijziging verandert de status niet en lifecyclewijziging verandert de content niet. Een Relation beheert de bestaande Address via `address(AddressId)`; een gewone edit vervangt identity of ownership niet en maakt geen tweede Address.

Address is mutable masterdata zonder ingebouwde versiehistorie. V1 gebruikt geen hard delete voor duurzame removal; deactivatie behoudt identity en huidige content. Mutation-auditlogging en eventuele historische documentsnapshots blijven afzonderlijke vervolgscope.

## BankAccount

`BankAccount` is een child-entity van Relation. Relation bewaakt ownership, unieke BankAccountId-waarden en alle toevoeg- en verwijderhandelingen. De identiteit en IBAN zijn onveranderlijk; de rekeningnaam en actieve status wijzigen via expliciet domeingedrag.

IBAN en BIC krijgen uitsluitend structurele validatie. Saldo, transacties, PSD2, CAMT, SEPA, bankvalidatie en betalingsverwerking vallen buiten deze story.

Alleen BankAccountId is uniek. Gelijke IBAN-, BIC- en rekeningnaamwaarden zijn bewust toegestaan zolang geen expliciete businessregel anders bepaalt. Duurzame deactivation verwijdert geen bankrekeningrecord en reactivation gebruikt dezelfde identity.

## Architectuurgrenzen

Het Domain gebruikt uitsluitend native PHP en gedeelde domein-value-objects. Laravel, databaseopslag, repositories en infrastructuur vallen buiten deze laag. Uniekheid van CustomerNumber en van de Customer-classificatie per Relation vereist externe gegevens en wordt later buiten de entity bewaakt.
