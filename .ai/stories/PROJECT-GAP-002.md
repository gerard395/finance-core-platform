# PROJECT-GAP-002 – Purchasing Foundation & Dependency Review

Reviewdatum: 25 augustus 2026. Type: design/inventarisatie; geen productcode,
migration, database- of permissionwijziging.

## 1. Current baseline

W4E is compleet. Purchasing heeft een frameworkonafhankelijke eerste Domain-iteratie
en enkele in-memory Application-postingprototypes, maar nog geen productflow. Er zijn
geen Purchase migrations, Eloquent records/repositories, write/read use cases,
postingconfiguration, Webroutes/controllers/views of geïmplementeerde permissions.

Demo Administration `534bae11-6f0b-4e07-8d66-8dd81cd3f128` bevat feitelijk alleen:

- journal `VERK`, type Sales;
- Asset `1300 Debiteuren`, Liability `1600 Af te dragen btw` en Revenue `8000 Omzet`;
- zeven actieve **Output** TaxCodes: BTW21, BTW9, BTW0, EUDIENST, ICLGOEDEREN,
  VRIJGESTELD en BUITENSCOPE.

Een Purchase journal, Crediteurenrekening, Voorbelastingrekening, expense/asset-
allocatierekeningen, Input TaxCodes en PurchasePostingConfiguration ontbreken. Dit is
geen datacorrectieverzoek: er is niets geprovisioned.

## 2. Existing Purchasing foundation

`PurchaseInvoice` en `PurchaseCreditInvoice` bestaan als Aggregate Roots met eigen
lines en unit tests. Identiteiten, AdministrationId, SupplierId, Currency en datums
zijn immutable; minimaal één line is vereist vóór Finalized; line-totalen gebruiken
Money en Quantity zonder floats. Statussen zijn Draft/Finalized/Posted/Paid/Cancelled
voor invoices en Draft/Finalized/Posted/Cancelled voor credits.

`CreatePurchaseInvoicePostingRequest`, `PostPurchaseInvoiceWithTax` en
`PostPurchaseCreditInvoiceWithTax` bewijzen in-memory dat PostingEngine een purchase-
boeking kan maken en Input Original/Reversal TaxPostings kan produceren. Zij zijn geen
duurzame productorchestratie: er is geen repository, transactionele linkage,
configurationreader, OpenItem-creatie of concurrencyguard. De oude eenvoudige mapper
boekt bovendien alle netto lines op één expense-account en is niet de toekomstige
productboundary.

### Capability matrix

| Capability | Domain | Application | Persistence | Web | Tests | Status | Dependency |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Supplier classification | Ja | Ja | Ja | Via Relations | Unit/integration/Web | Bruikbaar | Relation masterdata |
| PurchaseInvoice + lines | Ja | Alleen postingprototype | Nee | Nee | Domain + prototype | Foundation | Contractalignment |
| PurchaseCreditInvoice + lines | Ja | Alleen full fiscal reversalprototype | Nee | Nee | Domain + prototype | Foundation | Posted source invoice |
| Purchase journal | JournalType bestaat | Masterdatabeheer bestaat | Journal persistence bestaat | W4D settings | Accounting-tests | Masterdata-capable | Expliciete Purchase setup |
| AP/Crediteuren | Liability type bestaat | Geen Purchase config | Ledger persistence bestaat | W4D settings | Accounting-tests | Masterdata-capable | Purchase config |
| Expense/asset allocation | Expense/Asset types bestaan | Prototype neemt expliciete ID | Ledger persistence bestaat | W4D settings | Prototype-tests | Semantisch bruikbaar | Per-line selector |
| Input VAT account | Asset type bestaat | Prototype neemt expliciete ID | Ledger persistence bestaat | W4D settings | Prototype-tests | Masterdata-capable | Purchase config |
| Purchase posting config | Nee | Nee | Nee | Nee | Nee | Ontbreekt | Journal/accounts |
| Input VAT TaxPosting | Input direction bestaat | Domestic prototype | TaxPosting persistence bestaat | Nee | Integration | Gedeeltelijk | Input catalogue/classification |
| Payable OpenItem | Ja | Generieke stores/matching | Ja | Alleen reports | Unit/integration | Bruikbaar | Atomic Purchase posting |
| Supplier payment | Banking Payment child bestaat | Bank posting foundation | Bank persistence/Web ontbreekt | Nee | Domain/integration | Buiten Purchase | Banking batch |
| Incoming attachment | Alleen generieke ontwerpnamen | Nee | Nee | Nee | Nee | Ontbreekt | Document intake design |
| Purchase permissions | Alleen beschrijvende catalogusregels | Geen typed definitions | Geen provisioning | Nee | Nee | Niet geïmplementeerd | Authorization story |

## 3. Supplier readiness

Supplier is tenant-owned classification van een Relation: immutable SupplierId,
RelationId en SupplierNumber plus actieve status. Relation bezit naam, optioneel VAT ID
en fiscal jurisdiction, typed addresses, contacts en bank accounts. Supplier dupliceert
die masterdata niet. Dit is voldoende als **selectiebron**, maar niet als historische
factuurwaarheid.

Een PurchaseInvoice moet bij registratie immutable snapshotten: SupplierId en
RelationId, leveranciernaam, supplier VAT ID, jurisdiction en het adres zoals het op
het ontvangen document staat. Latere Relation-mutaties mogen posting/reporting niet
wijzigen. Het Sales snapshotpattern (typed immutable scalar/value-object snapshot op
het document, nooit live lookup) is herbruikbaar; Sales-documentclasses zelf niet.

Geen bestaande AddressType is autoritatief voor de adresidentiteit van de **uitreikende
leverancier op een ontvangen factuur**. `Invoice` is bestaande Relation-masterdata,
maar bewijst niet dat dit het issueradres op het brondocument is. V1 vereist daarom
expliciete bevestiging/overname van het supplieradres in de document snapshot; geen
first-address- of first-Invoice-fallback. Een nieuw masterdata-purpose is niet nodig
zolang de invoer expliciet is. Automatische purpose-resolutie is FOLLOW-UP.

## 4. PurchaseInvoice model

De huidige aggregate is bruikbaar voor identity, ownership, Currency, invoice/due date,
line ownership en Draft/Finalized/Posted-guards, maar mist productkritische feiten:

- ondubbelzinnige externe supplier invoice number-semantiek;
- Relation/supplier fiscal en address snapshots;
- received date en optionele supply date;
- TaxCode en expense/asset LedgerAccount per line;
- net/tax/gross snapshot of reproduceerbare line-fiscal truth;
- posting/open-item linkage en approval actor/timestamp;
- original incoming documentlink;
- persistence-safe duplicate protection.

`PurchaseInvoiceNumber` lijkt een intern documentnummer, terwijl een leverancier het
factuurnummer bepaalt; `SupplierReference` is generiek en optioneel. Vóór persistence
moet één expliciete `SupplierInvoiceNumber`-semantiek worden gekozen, zonder interne
SalesNumberSequence. De huidige ASCII-regel (2–32, alleen letter/cijfer/_/-) is te
restrictief voor reële externe nummers en moet gecontroleerd worden verruimd zonder
control characters of onbegrensde input.

De harde v1 duplicate-invariant wordt, na canonieke trim/case-policy:

`AdministrationId + SupplierId + SupplierInvoiceNumber`.

Zij krijgt een database unique guard en een echte MySQL-racetest. InvoiceDate is geen
deel van de harde sleutel: dezelfde externe identity met een andere datum blijft een
verdacht duplicaat. Fuzzy matching is geen v1-regel. Credits hebben een afzonderlijke
unique sleutel op Administration + Supplier + SupplierCreditInvoiceNumber.

## 5. Accounting/posting design

Minimale professionele lifecycle:

`Draft → Finalized → Posted`, met Cancelled alleen vóór Posted.

Finalized is in v1 de expliciete enkelvoudige approvalactie en bewaart actor plus tijd;
er komt geen workflow-engine of four-eyes-model zonder requirement. `Paid` en
`PartiallyPaid` horen niet als handmatig gemuteerde PurchaseInvoice-status: betaling is
afgeleid uit het gekoppelde OpenItem. De bestaande `markAsPaid()` moet daarom vóór
persistence worden uitgelijnd om drift tussen document en Accounting te voorkomen.

Iedere line kiest expliciet één actieve same-tenant LedgerAccount van type Expense of
Asset en één actieve Input TaxCode. Andere bestaande accounttypen worden v1 geweigerd;
uitbreiding vereist een expliciet boekingsscenario. Er is geen rekeningheuristiek of
default “eerste actieve rekening”. Quantity, unit price, Currency en description
blijven linefeiten; net/tax/gross worden exact met Money berekend.

`PurchasePostingConfiguration` is de eigen tegenhanger van Sales configuration en
bevat minimaal:

- actief same-tenant Journal van type Purchase;
- actief same-tenant Accounts Payable-account van type Liability;
- actief same-tenant Input VAT-account van type Asset.

Expense/asset blijft per line. W4D Journal/LedgerAccount lifecycle en settingspagina's
worden hergebruikt; geen tweede masterdatabeheerlaag. Beheer → Instellingen krijgt
later alleen een expliciete “Inkoopboekingen”-selectorflow, zonder defaults.

Domestic posting:

1. Debit gekozen expense/asset per line voor net;
2. Debit Input VAT voor aftrekbare btw;
3. Credit Accounts Payable voor gross.

Uitsluitend PostingEngine maakt de JournalEntry. Eén transactionele Application-
orchestrator bewaart at-most-once PurchaseInvoicePosting-linkage, JournalEntry,
Input/Original TaxPostings en exact één Payable/Credit OpenItem met gross, Currency,
due date, supplier RelationId, posting date en source JournalEntry. Er ontstaat geen
OpenItem vóór succesvolle financiële/fiscale posting. Database-uniciteit plus locking
bewaken postingconcurrency; update-in-place financiële feiten zijn verboden.

## 6. Input VAT/fiscal model

De bestaande TaxPosting bewaart TaxCodeId, rate, taxable base, tax amount, Input/Output,
source document/line, PostingDate, JournalEntry/line trace, Original/Reversal,
TaxTreatment, VatReturnClassification en Currency via Money. Dat is een sterke basis
voor immutable reportingtruth. Supplier fiscal snapshots blijven immutable op het
Purchase-document en zijn via de source-identiteiten navigeerbaar; Reporting mag nooit
de actuele Relation of TaxCode reconstrueren.

Domestic Input is nog niet product-ready. De Demo/catalogue heeft alleen Output codes.
Een toekomstige create-missing-only, tenant-scoped en idempotente Input catalogue mag
bestaande codes nooit resetten. Bovendien accepteert `TaxClassification` voor Input
alleen DomesticStandard, DomesticReduced en ZeroRated; Exempt/OutsideScope en
inkoop-side EU/reverse-charge classificaties zijn niet representabel.

Reverse charge is een afzonderlijke **BLOCKER BEFORE INTERNATIONAL PURCHASE
IMPLEMENTATION**: purchase-side EU goods/services vereisen verschuldigde Output VAT én
mogelijk aftrekbare Input VAT in dezelfde fiscale gebeurtenis. Eén huidige Input
TaxPosting en één Input VAT-journalregel kunnen dit niet veilig modelleren. Eerst is een
Fiscal contract/story nodig met purchase-side return classifications, dubbele
input/outputtrace en expliciete aftrekbaarheid; er wordt geen fictieve VAT-line geboekt.

Factual dates blijven gescheiden:

- supplier invoice date: datum op het document;
- received date: wanneer de Administration de factuur ontving;
- supply date: feitelijke levering/prestatie, optioneel maar verplicht waar regime dat
  vereist;
- posting date: Accounting-periode van de JournalEntry;
- fiscal reporting date: door typed fiscal-date policy bepaald, niet automatisch de
  posting date.

Voor normale domestic voorbelasting bepaalt het factuurstelsel in beginsel het tijdvak
op basis van ontvangen facturen/factuurdatum, terwijl aftrek pas kan na ontvangst. Voor
intracommunautaire diensten is de leverdatum relevant en voor EU-goederen gelden eigen
factuur-/15e-dagregels. De huidige foundation mist received/supply date en een typed
policy: **BLOCKER BEFORE FISCAL PURCHASE PERSISTENCE**. Non-EUR transacties zijn niet
hetzelfde als VAT-reporting readiness; officiële EUR-conversie blijft een afzonderlijk
FX/fiscal-policyvraagstuk.

### Actuele autoritatieve broncontrole

Gecontroleerd op 25 augustus 2026:

- [Belastingdienst – Welke btw mag u aftrekken?](https://www.belastingdienst.nl/wps/wcm/connect/bldcontentnl/belastingdienst/zakelijk/btw/btw_aftrekken/welke_btw_is_aftrekbaar/welke_btw_mag_u_aftrekken): aftrek vereist zakelijk/belast gebruik, werkelijke levering en een geldige btw-factuur.
- [Belastingdienst – Factuureisen](https://www.belastingdienst.nl/wps/wcm/connect/bldcontentnl/belastingdienst/zakelijk/btw/administratie_bijhouden/facturen_maken/factuureisen/): leverancier/afnemer, adressen, VAT ID, factuurnummer, factuur- en leverdatum, base, tarief en btw-bedrag zijn relevante documentfeiten.
- [Belastingdienst – Factuurstelsel](https://www.belastingdienst.nl/wps/wcm/connect/bldcontentnl/belastingdienst/zakelijk/btw/btw_aangifte_doen_en_betalen/bereken_het_bedrag/hoe_berekent_u_het_btw_bedrag/factuurstelsel): ontvangen facturen/factuurdatum sturen reguliere voorbelasting; aftrek kan pas na ontvangst.
- [Belastingdienst – EU-afnamen](https://www.belastingdienst.nl/wps/wcm/connect/bldcontentnl/belastingdienst/zakelijk/btw/zakendoen_met_het_buitenland/goederen_en_diensten_afnemen_uit_andere_eu_landen/): EU-goederen en vaak EU-diensten vereisen zelfberekende Nederlandse btw, met aftrek alleen voor zover toegestaan.
- [Belastingdienst – Btw berekenen over EU-afnamen](https://www.belastingdienst.nl/wps/wcm/connect/bldcontentnl/belastingdienst/zakelijk/btw/zakendoen_met_het_buitenland/goederen_en_diensten_afnemen_uit_andere_eu_landen/btw_berekenen_over_goederen_en_diensten/btw_berekenen_als_u_goederen_of_diensten_afneemt_uit_andere_eu_landen): EU-afnamen gebruiken Nederlands tarief en vreemde valuta moet voor aangifte naar EUR.
- [Belastingdienst – Factuurdatum of leverdatum](https://www.belastingdienst.nl/wps/wcm/connect/bldcontentnl/belastingdienst/zakelijk/btw/zakendoen_met_het_buitenland/goederen_en_diensten_afnemen_uit_andere_eu_landen/aangifte_doen/factuurdatum_of_leverdatum/): EU-goederen en intracommunautaire diensten hebben verschillende tijdvakregels.
- [Belastingdienst – Hoe werkt btw verleggen?](https://www.belastingdienst.nl/wps/wcm/connect/bldcontentnl/belastingdienst/zakelijk/btw/btw_berekenen_aan_uw_klanten/waarover_btw_berekenen/verleggingsregeling/hoe_werkt_btw_verleggen): de afnemer geeft verlegde btw als verschuldigd aan en mag haar, waar toegestaan, in dezelfde aangifte als voorbelasting aftrekken.

Ontwerpconclusie: domestic input VAT kan na catalogus-, date- en snapshotalignment in
de eerste productbatch; international/reverse-charge niet.

## 7. OpenItem/AP model

OpenItem is gereed. Een normale supplier invoice creëert `Payable/Credit` met positieve
originalAmount. Een posted supplier credit creëert `Payable/Debit`; dit is een
supplier credit balance, geen negatieve Payable en geen Receivable. Same-tenant,
same-Relation, same-Currency opposite-side `OpenItemMatch` kan invoice en credit
append-only matchen. `OpenItemSettlement` kan een geboekte bankbetaling append-only
toepassen/reversen. De bestaande repositories en locking zijn herbruikbaar.

## 8. Credits

PurchaseCreditInvoice heeft een optionele source PurchaseInvoiceId en het prototype
maakt een volledige Input/Reversal per originele TaxPosting, crediteert expense/input
VAT en debiteert AP. Het mist persistence, supplier snapshots, external-number-
semantiek, line-to-original trace en durable posting/open-item linkage.

V1 creditposting moet een concrete posted source PurchaseInvoice vereisen, dezelfde
tenant/supplier/Currency gebruiken en elk origineel fiscaal feit exact reversen. De
credit krijgt een Payable/Debit OpenItem en wordt via OpenItemMatch met de oorspronkelijke
Payable/Credit gematcht. Een volledig of gedeeltelijk betaalde bron verandert de
fiscale reversal niet, maar beperkt het matchbare open bedrag; een overschot blijft als
supplier credit balance.

Directe partial TaxPosting reversal is niet veilig ondersteund. Partial credit is
**FOLLOW-UP**, niet blocker voor de eerste invoicebatch: de eerste creditbatch beperkt
zich expliciet tot volledige source-line reversals, totdat remainder-as-new-Original en
allocatiebeleid ontworpen zijn.

## 9. Payments boundary

Banking heeft een Payment child met OpenItemId en Money plus domeintests, maar nog geen
volledige supplier-payment persistence/Webflow. Betaling hoort daarom niet in de eerste
Purchasing batch. Purchase posting eindigt productmatig bij een correcte openstaande
Payable. Een latere Banking/Payments-batch boekt de banktransactie en past daarna een
OpenItemSettlement toe; Purchasing markeert zichzelf niet handmatig Paid.

## 10. Documents boundary

W4E `DocumentArtifact` is generated output en niet het model voor een ontvangen
supplierbestand. Er bestaat geen upload/archive/attachment persistence. Handmatige
PurchaseInvoice-invoer is voldoende voor de eerste batch; origineel PDF koppelen is
**FOLLOW-UP** zodra een private incoming-documentcontract met integrity, tenant-FK,
mime/size allowlist, malwarebeleid, retention en downloadauthorization is ontworpen.
OCR, scanning en automatische import zijn OPTIONAL en geen predecessor.

## 11. Authorization

De permissioncatalogus noemt alleen namen voor View/Register/Change/Approve/Register
Credit; er zijn geen stable IDs, typed definitions, roles, provisioning of middleware.
De kleinste future set is:

- `PURCHASING.VIEW`;
- `PURCHASING.INVOICES_DRAFT_MANAGE`;
- `PURCHASING.INVOICES_FINALIZE`;
- `PURCHASING.INVOICES_POST`;
- later afzonderlijk `PURCHASING.CREDIT_INVOICES_DRAFT_MANAGE`,
  `...FINALIZE` en `...POST`.

Geen role-name checks. Rollen/provisioning volgen create-missing-only en kennen geen
automatische memberships toe.

## 12. Web journey

V1 is volledig expliciet: Inkoop → Inkoopfacturen → Nieuwe inkoopfactuur → actieve
Supplier selecteren → extern nummer/dates/Currency en supplier snapshot bevestigen →
lines met TaxCode en expense/asset-account → totals controleren → Finalize → Post →
Payable bekijken. Geen auto-post. Leveranciers blijven onder Relaties; Inkoop dupliceert
geen Supplier-masterdata-UI. Creditfacturen komen pas in de creditbatch.

Server-owned ActiveAdministrationContext bepaalt tenant. Iedere toekomstige tabel en
FK gebruikt AdministrationId en concrete same-tenant keys; requestbody kan nooit tenant
kiezen. Empty Administration acceptance loopt zonder SQL/seeder: W4D Journal/Account-
masterdata → Input TaxCode/productconfig → Supplier via Relations → PurchaseInvoice →
Post → Payable.

## 13. Dependencies, blockers en concurrency

### BLOCKER BEFORE PURCHASE IMPLEMENTATION

1. External supplier-numbersemantiek en toegestane tekens uitlijnen.
2. Immutable supplier/address snapshot en received/supply/fiscal-date policy vastleggen.
3. `Paid` uit documenttruth halen en paymentstatus uit OpenItem afleiden.
4. Input TaxClassification/catalogue voor domestic standard/reduced/zero én relevante
   exempt/outside-scope gevallen veilig maken.
5. Atomische postinglinkage ontwerpen voor JournalEntry + TaxPostings + Payable.

### REQUIRED IN FIRST PURCHASE BATCH

Typed authorization; PurchasePostingConfiguration/settings; PurchaseInvoice
persistence/Application; per-line account/TaxCode; database duplicate guard;
transactionele domestic posting; Payable OpenItem; Webflow en empty-Administration-
acceptance.

### FOLLOW-UP

Purchase credits, partial credits, incoming attachments, Banking supplier payments,
international/reverse-charge purchase fiscal model, non-EUR VAT FX-policy, automated
address purpose, period locking en generic mutation audit. Accounting heeft nog geen
boekjaar/periode-locking; dit is debt en wordt blocker zodra organisatiebeleid gesloten
perioden vereist, maar blokkeert de huidige technische domestic flow niet.

### OPTIONAL

OCR/scanning, fuzzy duplicate detection, multi-user approval workflow en automated
account suggestions.

Echte MySQL-concurrencytests zijn verplicht voor duplicate supplier invoice,
at-most-once posting/OpenItem/TaxPosting linkage en later credit posting/matching.
Supplier invoice finalization bewaart actor/timestamp als businessfact; generieke
mutation audit blijft debt.

Dependencygraph:

`contractalignment → authorization/configuration → invoice persistence/Application → domestic Input VAT posting + Payable → invoice Web/review → credit persistence/posting/matching → Banking payments → incoming documents → international fiscal extension`

## 14. Recommended next batch

Exact één aanbevolen volgende implementatiebatch:

**P3 – Domestic Purchase Invoice to Payable**

Deze batch levert de kleinste verticale productflow Supplier → handmatige domestic
PurchaseInvoice → Input VAT → Purchase Journal/AP → PostingEngine → Payable OpenItem →
Web. Zij start geen PurchaseCredit, payment, attachments, OCR, international reverse
charge of VAT/ICP reporting.

## 15. Definitive story split

De aanbevolen P3-batch bevat vijf sequentiële stories:

1. **P3-000 – Align Purchase Invoice Product Contracts**: external number, supplier
   snapshots, address/date policy, lifecycle/payment boundary en per-line allocation.
2. **P3-001 – Add Purchase Authorization & Posting Configuration**: stable typed
   permissions, create-missing-only domestic Input catalogue, Purchase journal/AP/Input
   VAT selectors en settings-readiness.
3. **P3-002 – Add PurchaseInvoice Persistence & Application Contracts**: same-tenant
   schema/repository, duplicate guard, snapshots, lifecycle and MySQL race tests.
4. **P3-003 – Post Domestic Purchase Invoice & Create Payable**: at-most-once atomic
   JournalEntry/Input TaxPostings/postinglink/Payable orchestration and concurrency.
5. **P3-004 – Add PurchaseInvoice Web Flow & Review**: explicit journey,
   authorization/tenant/security, empty-Administration acceptance and full regression.

Pas na P3 volgt één afzonderlijk ontworpen Purchase Credit-batch. Geen van die
follow-ups wordt nu gestart.

## 16. Deferred capabilities

Purchase credits en credit balances, partial credit, supplier payments, original
attachments, OCR/import, multi-user approvals, automated allocation, closed periods,
non-EUR VAT policy en international/reverse-charge purchases blijven deferred. VAT/ICP
reporting blijft geparkeerd; P3 bewaart wel vanaf dag één immutable domestic fiscale
bronfeiten zodat latere reporting niet uit live masterdata hoeft te reconstrueren.
