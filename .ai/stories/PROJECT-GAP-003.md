# PROJECT-GAP-003 – Post-P3 Purchasing & Payments Direction Review

Reviewdatum: 25 augustus 2026. Type: design/gap review; geen productiecode,
migration, database- of permissionwijziging.

## 1. Post-P3 baseline

P3 sluit de handmatige domestic keten Supplier → Draft PurchaseInvoice → Finalized →
Posted → Payable/Credit. Sales heeft daarnaast Quotations, Orders, SalesInvoices,
SalesCreditInvoices, transactionele posting naar Receivables en documentdelivery.
Journal-, LedgerAccount-, JournalEntry-, TaxPosting- en OpenItem-persistence bestaat;
OpenItems hebben append-only Settlements en opposite-side Matches.

| Capability | Domain | Application | Persistence | Web | Feitelijke status |
| --- | --- | --- | --- | --- | --- |
| Sales documenten/credits/delivery | Ja | Ja | Ja | Ja | Productflow operationeel |
| Domestic PurchaseInvoice/Payable | Ja | Ja | Ja | Ja | P3 compleet |
| OpenItem settlement/matching | Ja | Ja | Ja | Nee | Accounting-foundation duurzaam |
| PurchaseCreditInvoice | Oud prototype | Alleen in-memory full reversal | Nee | Nee | Niet productwaardig |
| BankTransaction/Payment | Eerste Domain-iteratie | Posting-requestmapper | Nee | Nee | Foundation, geen productflow |
| International Purchase VAT | Domestic model blokkeert dit bewust | Nee | Nee | Nee | Fiscal predecessor nodig |

De zwaarste actuele productgap is cash settlement: de gebruiker kan zowel een
Receivable als Payable creëren, maar geen klantontvangst of leveranciersbetaling als
duurzaam bankfeit boeken en aan openstaande posten koppelen. Dit is een incompletere
boekhoudcyclus dan het ontbreken van een uitzonderingsdocument of internationale
fiscaliteit.

## 2. Purchase Credits readiness

`PurchaseCreditInvoice` en zijn lines bewaken alleen identity, Supplier, EUR/context,
Draft-lines en Draft → Finalized → Posted/Cancelled. Ze missen de P3-contracten voor
extern supplier-creditnummer, actor/timestamp, supplier/addresssnapshot, source-line-
trace, account-/taxsnapshot, persistence, readmodels, linkage en Web.
`PostPurchaseCreditInvoiceWithTax` bewijst uitsluitend in memory dat één creditline een
volledige Original Input `TaxPosting` kan reversen en expense/Input VAT kan crediteren
tegen AP-debet. Het is geen transactionele durable use case en herleest door de caller
aangeleverde originals.

Het Accounting-model is wel geschikt: een supplier credit is een positieve
`Payable/Debit`; de normale invoice is `Payable/Credit`. `OpenItemMatchingPolicy`
vereist exact hetzelfde type, Administration, Relation en Currency, tegengestelde side
en een bedrag dat geen van beide open amounts overschrijdt. `open_item_matches` kan dit
zonder schemawijziging append-only opslaan en lockt beide items in stabiele ID-volgorde.
Een overschot blijft veilig als open Payable/Debit supplier credit balance.

Het P3 `tax_postings`-schema kan full reversal zonder herontwerp dragen: source document
en line, Original/Reversal, reversed TaxPostingId, rate, treatment, VAT/ICP-
classification, base, tax, fiscal/postingdatum en journal-line trace zijn immutable.
De creditorchestrator moet de persisted source PurchaseInvoice en diens TaxPostings
lezen; actuele TaxCode- of Supplier-masterdata mag niets herinterpreteren.

Aanbeveling voor een eerste latere creditbatch: **uitsluitend volledige source-line
reversal**. Partial quantities/amounts vereisen allocatie van original versus remainder,
afrondingsbeleid, meerdere reversals per source fact en een harde cumulative guard. Dat
vergroot de eerste batch onnodig. Credits op open, gedeeltelijk betaalde en betaalde
invoices zijn fiscaal wel mogelijk, maar de matchbare hoeveelheid verschilt: alleen het
open invoicebedrag kan direct matchen; het restant blijft supplier credit balance.
Concurrency moet duplicate external credit, duplicate source-line reversal en een
credit/payment matchrace bewaken.

Productclassificatie: **important follow-up**, niet de critical next batch. Een eerste
release kan creditnota's tijdelijk buiten het systeem corrigeren, maar niet duurzaam;
dat is professioneel onvolledig. Toch raakt het minder dagelijkse transacties dan het
volledig ontbreken van ontvangst- en betaalregistratie.

## 3. Payments/Banking readiness

Banking heeft `BankTransaction` als Aggregate Root en `Payment` als child allocation
naar één OpenItem. Money/currency, immutable transactioncontext, paymentownership,
exacte allocationtotalen en Imported → Matched → Posted zijn getest. De mapper maakt
voor inkomend geld Debit Bank/Credit counteraccount en voor uitgaand geld Debit
counteraccount/Credit Bank. De geïntegreerde test bewijst conceptueel posting plus
`OpenItem::applySettlement()`.

Productreadiness ontbreekt volledig: geen BankTransaction/Payment repository of tabellen,
geen transactionele Application-orchestrator, readmodels, identity clocks, Webroutes,
typed Banking-permissions of provisioning. Relation BankAccount is alleen masterdata;
er is geen Administration-owned bankrekeningmapping naar Bank Journal en Bank
LedgerAccount. De bestaande Journalmasterdata kent `JournalType::Bank`, maar er is geen
BankPostingConfiguration, readiness of suspensebeleid.

### Begrippen en minimale productboundary

- **BankTransaction** is het immutable kas/bankfeit (credit/debet, datum, referentie,
  tegenpartijcontext en provenance), handmatig geregistreerd of later geïmporteerd.
- **Payment** is geen los mutable betaalrecord maar een allocation-child van dat feit
  naar één OpenItem.
- **OpenItemSettlement** is het append-only Accounting-gevolg van een daadwerkelijk
  geposte BankTransaction/Payment en verwijst naar die geposte JournalEntry.
- **OpenItemMatch** verbindt twee document-OpenItems met tegengestelde side, bijvoorbeeld
  invoice en credit; het is niet het mechanisme voor cash settlement.

Een eerste batch kan waarde leveren met **handmatige BankTransaction-registratie** en
expliciete allocations; CAMT.053, MT940, PSD2/API en automatische reconciliation zijn
follow-up. De huidige statusnaam `Imported` is dan te specifiek en moet in de designstory
expliciet worden uitgelijnd met provenance, zonder een handmatig feit als import te
vermommen.

Customer receipt: Debit Bank, Credit Accounts Receivable, daarna settlement van een
`Receivable/Debit`. Supplier payment: Debit Accounts Payable, Credit Bank, daarna
settlement van een `Payable/Credit`. De huidige OpenItem-side en settlementsemantiek
ondersteunen beide. Positieve partial settlements, meerdere settlements per item en één
banktransactie verdeeld over meerdere invoices zijn ondersteund door meerdere Payment-
children plus append-only settlementhistory. De transactionele productorchestrator en
echte MySQL-locking ontbreken nog.

Overpayment is niet veilig representabel als iedere Payment verplicht naar een OpenItem
wijst: `OpenItem` weigert settlement onder nul, terwijl Matching eist dat alle
BankTransaction-Money exact gealloceerd is. De eerste batch moet overpayment daarom
typed weigeren/ongepost laten. Een later ontwerp kan een unallocated/suspense bankleg en
een afzonderlijk customer/supplier credit balance introduceren; niet stil boeken op het
eerste OpenItem.

Benodigde configuratie: active same-tenant Bank Journal, Asset Bank LedgerAccount en
expliciete Administration BankAccount-mapping; een suspense-account alleen wanneer een
latere unallocated flow is ontworpen. Geen defaults op IBAN, naam of eerste account.
Nieuwe facts die actor/timestamp vereisen zijn handmatige registratie, allocation-
finalization/posting en settlementreversal. Concurrencytests moeten duplicate reference/
provenance, double post, twee settlements op hetzelfde open amount en dezelfde latere
import tweemaal bewaken.

## 4. International Purchase VAT readiness

P3 heeft per PurchaseInvoiceLine exact één Input Tax snapshot en één
`TaxCalculationResult`. `TaxClassification` weigert international/reverse-charge Input
expliciet. Een `TaxPosting` heeft één direction en hoogstens één tax journal line. De
huidige Purchase postingbuilder maakt per line netto plus nul of één Input VAT-leg.
Daarmee kan één bronregel niet tegelijk de verschuldigde Output VAT en aftrekbare Input
VAT van een EU-verwerving/verlegde dienst representeren.

Voor een EU B2B service is conceptueel nodig: Expense/Asset-debet en AP-credit voor het
factuurbedrag, plus een Output-VAT-credit en—voor zover aftrekbaar—Input-VAT-debet over
dezelfde taxable base. Daarvoor ontbreken een purchase-side treatment/classification,
een fiscal result met meerdere legs, meerdere TaxPostings per source line, gekoppelde
Input/Output-pairtrace, aparte VAT accounts, deductibility en regime-afhankelijke fiscal
date policy. Supplier VAT ID/jurisdiction snapshots bestaan, maar validatie en evidence
voor het regime ontbreken. Non-EUR vereist bovendien een expliciete EUR-conversiebron en
koersdatum voor aangifte; P3 is EUR-only.

Actuele broncontrole, 25 augustus 2026:

- [Belastingdienst – goederen en diensten afnemen uit andere EU-landen](https://www.belastingdienst.nl/wps/wcm/connect/bldcontentnl/belastingdienst/zakelijk/btw/zakendoen_met_het_buitenland/goederen_en_diensten_afnemen_uit_andere_eu_landen/): bij EU-goederen berekent de afnemer doorgaans Nederlandse btw; bij EU-diensten is btw vaak verlegd. Aftrek bestaat alleen voor zover gebruikt voor belaste omzet.
- [Belastingdienst – btw berekenen over EU-afnamen](https://www.belastingdienst.nl/wps/wcm/connect/bldcontentnl/belastingdienst/zakelijk/btw/zakendoen_met_het_buitenland/goederen_en_diensten_afnemen_uit_andere_eu_landen/btw_berekenen_over_goederen_en_diensten/): het Nederlandse tarief wordt toegepast en vreemde valuta moeten voor de aangifte naar EUR.
- [Belastingdienst – aangifte EU-afnamen](https://www.belastingdienst.nl/wps/wcm/connect/bldcontentnl/belastingdienst/zakelijk/btw/zakendoen_met_het_buitenland/goederen_en_diensten_afnemen_uit_andere_eu_landen/aangifte_doen/): verschuldigde btw komt in rubriek 4b en aftrekbare btw in 5b; goederen en diensten kennen verschillende valuta-/tijdstipregels.
- [Belastingdienst – verlegde btw aftrekken](https://www.belastingdienst.nl/wps/wcm/connect/bldcontentnl/belastingdienst/zakelijk/btw/btw_aftrekken/welke_btw_is_aftrekbaar/verlegde_btw_aftrekken): verlegde btw moet worden aangegeven, maar is alleen onder voorwaarden aftrekbare voorbelasting.
- [Belastingdienst – hoe werkt btw verleggen?](https://www.belastingdienst.nl/wps/wcm/connect/bldcontentnl/belastingdienst/zakelijk/btw/btw_berekenen_aan_uw_klanten/waarover_btw_berekenen/verleggingsregeling/hoe_werkt_btw_verleggen): verschuldigde en aftrekbare btw worden in dezelfde aangifte afzonderlijk verantwoord; onterecht gefactureerde btw bij verplichte verlegging is niet aftrekbaar.

International Purchase VAT is onafhankelijk van Payments en technisch ook niet
afhankelijk van Purchase Credits; credits moeten later wel ieder gerealiseerd fiscal leg
exact kunnen reversen. International Purchase is waarschijnlijk de laatste grote
transactionele Purchase-bronstroom vóór volledige Nederlandse VAT reporting zinvol
wordt, maar niet de laatste totale fiscale bron: domestic/international credits,
import-btw/artikel 23, binnenlandse verlegging, partial deductibility, bad-debt-
correcties en aangifteperioden/locks ontbreken eveneens.

## 5. Dependency comparison

| Criterium | A Purchase Credits | B Payments/Banking | C International Purchase VAT |
| --- | --- | --- | --- |
| Productwaarde | Belangrijk correctiedocument | Hoog voor Sales én Purchase | Hoog voor internationale gebruikers |
| Boekhoudkundige noodzaak | Document-completeness | Sluit cashcyclus | Nieuwe fiscale transactiestroom |
| Fiscale noodzaak | Reversal van bestaande truth | Geen nieuwe VAT-truth | Hoog, wettelijk regimespecifiek |
| Afhankelijkheden | P3 + OpenItem matching | Accounting/OpenItems + bankconfig | Fiscal redesign + config/date/FX |
| Architectuurrisico | Middel | Middel; durable orchestration ontbreekt | Hoog; huidig one-leg-model blokkeert |
| Omvang | Middel bij full lines only | Middel/hoog maar goed af te bakenen | Hoog |
| Hergebruik | P3 snapshots/posting + Sales creditpatterns | Banking Domain + PostingEngine + settlements | TaxPosting basis, verder beperkt |
| Blokkeert andere module | Nee | Ja: praktische Sales/Purchase afwikkeling | Blokkeert latere fiscale dekking |
| Volgende afgebakende batch | Ja | **Ja, hoogste waarde** | Nee; eerst fiscal predecessor |

Accounting-completeness verschilt per as: Purchase Credits vergroten document-
completeness; Payments/Banking levert de grootste stap in cash-settlement-completeness;
International Purchase VAT vergroot tax-completeness. Omdat normale Sales- en Purchase-
documenten nu al open items maken maar geen geldbeweging kan worden verwerkt, weegt de
cashgap het zwaarst.

Purchase Credits kunnen vóór Payments worden gebouwd als de eerste versie full source-
line reversal gebruikt en open/paid-state niet met fiscale reversal verwart. Payments
kan veilig zonder Purchase Credits normale Receivables/Debit en Payables/Credit
settelen. International Purchase VAT hangt van geen van beide af; fiscale posting hoort
cash-onafhankelijk te blijven.

Attachments/OCR zijn voor geen kandidaat een predecessor. Handmatige bronregistratie is
voldoende; documentintegriteit blijft een afzonderlijke capability.

## 6. Accounting completeness

De huidige gebruiker kan omzet en kosten plus Receivable/Payable boeken, maar geen bank-
ontvangst of -uitgave en geen sluiting van die posten. Daardoor blijven zelfs volledig
betaalde facturen productmatig open. Dit raakt de dagelijkse boekhoudcyclus, cashpositie,
debiteuren-/crediteurenbeheer en historische Open Items. Payments/Banking levert daarom
meer algemene completeness dan eerst alleen Purchase Credits of een complex buitenlands
VAT-regime.

## 7. Recommended next batch

**Exact gekozen volgende implementatiebatch: B2 – Bank Payments & Open Item Settlement.**

De batch levert handmatige, duurzame bankontvangsten en leveranciersbetalingen voor
bestaande Receivable- en Payable-OpenItems. Zij hergebruikt de Banking Domain-foundation,
PostingEngine en append-only settlements, sluit tegelijk Sales en Purchasing af en
vermijdt bankimport- en overpaymentcomplexiteit. Er komt geen generiek mutable Payment-
record: BankTransaction blijft het primaire bankfeit en Payment blijft allocation-child.

Permissionrichting voor B2: typed View, Manage/Allocate en Post/Reversal-capabilities,
zonder role-name checks of automatische assignments. De designstory moet de kleinste
onafhankelijke set definitief vastleggen. Configuration is Administration-owned en bevat
alleen expliciet Bank Journal, Bank LedgerAccount en BankAccount-mapping.

## 8. Definitive story split

1. **B2-000 – Align Manual Bank Payment & Settlement Contracts**
   Leg provenance/status, receipt/paymentboekingen, allocation-, partial-, overpayment-
   en reversalgrenzen plus actor/timestamp vast; geen persistence.
2. **B2-001 – Banking Authorization & Posting Configuration**
   Typed permissions/canonical roles zonder assignments en tenant-safe Bank Journal,
   Bank LedgerAccount en Administration BankAccount readiness/settings.
3. **B2-002 – BankTransaction & Payment Persistence/Application Contracts**
   Durable aggregate/children, duplicate identity/provenance, selectors/readmodels en
   tenant-safe manual create/allocation/finalize met echte MySQL concurrency.
4. **B2-003 – Atomic Bank Posting & OpenItem Settlement**
   Eén outer transaction voor PostingEngine, postinglinkage, transactionstatus en één
   settlement per Payment; partial/multi-item, rollback, idempotency en settlement races.
5. **B2-004 – Banking Web Flow & Batch Review**
   Permission-aware list/create/allocate/post/read flow voor receipts en supplier
   payments, open-itemresultaat, regressies en volledige review. Geen import UI.

## 9. Follow-up order

2. **Purchase Credits** – daarna, beperkt tot volledige source-line reversals. B2 maakt
   open/partially-paid/paid bronnen en match-/creditbalancegedrag productmatig toetsbaar.
3. **International Purchase VAT predecessor** – vervolgens als expliciete Fiscal-
   redesignbatch vóór internationale PurchaseInvoice-Websemantiek.

## 10. VAT/ICP reconsideration condition

VAT/ICP reporting blijft geparkeerd. Herbeoordeling volgt pas wanneer alle materiële
Sales- en Purchase-fiscale source streams als immutable TaxPostings bestaan: domestic en
international/reverse-charge invoices én credits, import/binnenlandse verlegging,
deductibility/correcties, plus aangifteperiode-/lockbeleid en EUR-conversie waar nodig.
Payments zijn geen fiscale predecessor voor reporting.

## 11. Deferred capabilities

Niet onderdeel van B2: PurchaseCreditInvoice, CAMT.053/MT940/PSD2/API-import,
automatische reconciliation, overpayment/suspense, customer/supplier advances,
attachments/OCR, international/reverse-charge/import/foreign VAT, partial/non-
deductible VAT, VAT/ICP generators en multi-step approval.
