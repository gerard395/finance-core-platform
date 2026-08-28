# PROJECT-GAP-005 – Post-Purchase-Credits Product Direction Review

Reviewdatum: 28 augustus 2026. Type: design/inventarisatie/roadmap. Deze review wijzigt
geen productiecode, schema, permissions of developmentdata.

## 1. Managementsamenvatting

PC is gemerged en handmatig geaccepteerd. De domestic EUR-keten ondersteunt nu Sales-
en Purchase-documenten, full-source credits, financiële/fiscale posting, OpenItems,
handmatige klantontvangsten en leveranciersbetalingen, settlement en documentdelivery.
De acceptance bewees bovendien dat een PurchaseCredit op een volledig betaalde factuur
de bestaande cashsettlement niet terugdraait: de credit blijft terecht als open
`Payable/Debit` leverancierssaldo staan.

De belangrijkste actuele correctnessgap is dat een foutief geposte handmatige
BankTransaction niet productmatig kan worden teruggedraaid. `OpenItem` en persistence
kennen al append-only settlement reversals, maar er bestaat geen use case, linkage,
tegen-JournalEntry, permission of Webflow die de volledige bankposting atomair
reversed. De volgende hoofdproductbatch is daarom exact:

**B3 – Bank Payment Reversal.**

Voor B3 komt één kleine, geïsoleerde regressiefix: de PurchaseCredit-successmelding
moet het werkelijk gematchte bedrag uit het bestaande postingreadmodel respecteren.
Er is geen andere predecessor voor B3. Accounting periods/posting locks volgen direct
na B3 als correctness- en production-safetybatch. International Purchase VAT, Bank
Import/Reconciliation en VAT/ICP volgen pas daarna volgens de onderbouwde volgorde in
dit document.

## 2. Baseline na PC

Actuele developmentdatabase, read-only geïnventariseerd op 28 augustus 2026:

| Gebied | Feitelijke aantallen |
| --- | --- |
| Core | 1 Administration, 2 Domain Users, 2 Memberships |
| Sales | 0 Quotations, 0 Orders, 0 SalesInvoices, 0 SalesCreditInvoices |
| Purchasing | 2 PurchaseInvoices: 1 Posted, 1 Cancelled; 1 Posted PurchaseCreditInvoice |
| Purchase posting | 1 PurchaseInvoicePosting, 1 PurchaseCreditPosting, 1 source-line claim |
| Banking | 1 AdministrationBankAccount, 1 Posted BankTransaction, 1 Payment, 1 allocation, 1 postinglink |
| Accounting masterdata | 3 Journals, 7 LedgerAccounts |
| Accounting facts | 3 JournalEntries, 8 lines; 2 Payable OpenItems: 1 Credit en 1 Debit |
| Afwikkeling | 1 Applied Settlement, 0 Matches |
| Fiscal | 1 Input/Original en 1 Input/Reversal TaxPosting |
| Delivery | 0 artifacts, requests, attempts, outboxitems en outcome resolutions |

De nulstanden bij Sales en Delivery zijn database-inhoud, geen ontbrekende
productcapability. De actuele code/tests leveren die capabilities wel.

## 3. Handmatige acceptance PC

De recente handmatige acceptance is productbewijs voor deze keten:

1. `TEST-INK-002`: PurchaseInvoice EUR 121, Posted, `Payable/Credit` EUR 121.
2. `BET-TEST-INK-002`: SupplierPayment EUR 121, Posted, Settlement EUR 121; source
   remaining EUR 0.
3. `TEST-CREDIT-001`: PurchaseCredit EUR 121, Posted; automatic Match EUR 0; source
   remains EUR 0; supplier credit balance `Payable/Debit` EUR 121.

Conclusie: de eerdere cashsettlement bleef immutable historical truth. De creditposting
maakte geen payment reversal en geen fictieve zero-match. Dit is correct.

## 4. Actuele capabilitymatrix

| Capability | Status | Feitelijke grens |
| --- | --- | --- |
| Relation/customer/supplier | Operational | Tenant-owned classificatie en masterdata; geen generieke mutation audit |
| Quotation | Operational | Draft/lifecycle/conversie/delivery; geen volledige workflow-engine |
| Sales Order | Operational | Conversie en invoicing; geen delivery/goods-receiptmodule |
| SalesInvoice | Operational | Posting, Receivable, TaxPosting en delivery |
| SalesCredit | Operational | Full-source corrections; partial credits/refunds deferred |
| Customer payment | Operational | Handmatig EUR, partial/multi-OpenItem settlement |
| PurchaseInvoice | Operational | Domestic EUR, volledig aftrekbare Input VAT |
| PurchaseCredit | Operational | Full source-line reversal en supplier credit balance |
| Supplier payment | Operational | Handmatig EUR, partial/multi-OpenItem settlement |
| Bank reversal | Foundation-only | Settlement reversal primitive bestaat; productflow ontbreekt |
| Bank import | Absent | Geen Statement/Line, parser, provenance of reconciliation |
| Journals/ledger | Operational | Masterdata en immutable posted entries; geen manual journal-entry Webflow |
| OpenItems | Operational | Receivable/Payable, temporal settlements en matches |
| TaxPostings | Operational/partial | Domestic truth en Sales/Purchase credits; geen multi-leg Purchase VAT |
| P&L/balance/trial balance | Foundation-only | Domaincalculators; dashboard gebruikt slechts een beperkte selectie |
| General ledger/Open Items/VAT overview | Foundation-only | Domain/readcontracts bestaan; geen complete reportingmodule/Websuite |
| Sales PDF/e-mail delivery | Operational | Private artifacts, durable delivery/outbox/audit; runtime readiness vereist |
| Incoming supplier documents | Absent | Geen upload/private received-document/OCR-contract |
| Purchase Orders | Absent | Geen Domain, persistence, approval, receipt of invoice matching |

## 5. Openstaande correctnessgaps

- **Critical:** foutief geposte BankTransaction kan niet als één atomische financiële
  reversal worden gecorrigeerd. Handmatige SQL of mutatie van posted facts is verboden.
- **High:** er zijn geen AccountingPeriods, FiscalYears of posting locks. Alle posting-
  controllers accepteren een datum zonder centrale open-period guard.
- **High:** generieke mutation audit ontbreekt; auditdekking verschilt per aggregate.
- **Medium:** partial Sales/Purchase credits en refund/credit-applicationflows ontbreken.
- **Medium:** geen productflow voor manual JournalEntry-correcties; correcties zijn nu
  capabilityspecifiek.
- **Low usability / misleading financial presentation:** de PC-successmelding claimt
  verrekening wanneer `matchedAmount` nul is.

De grootste directe correctnessgap is B3: een concrete reeds ondersteunde dagelijkse
geldboeking kan bij een invoerfout niet veilig worden hersteld.

## 6. Openstaande fiscale gaps

Domestic Sales/Purchase originals en full-credit reversals leveren immutable
TaxPostings. Niet gereed zijn: EU Purchase goods/services, domestic reverse charge,
import VAT/artikel 23, foreign VAT, partial/non-deductible VAT, multi-leg fiscal truth,
fiscale perioden/locks, officiële returnrounding/reconciliation en non-EUR-conversie.

Een PurchaseInvoiceLine heeft één Input-tax snapshot/result. Reverse charge vereist
minimaal één Output-leg en één mogelijk beperkte Input-leg over dezelfde taxable base.
Twee unrelated TaxCodes of twee fictieve source lines zijn geen toegestaan model.

## 7. Openstaande dagelijkse workflowgaps

- incoming PurchaseInvoice/PurchaseCredit attachment en later OCR;
- Purchase Orders, approval, goods/services receipt en invoice matching;
- bankstatementimport en user-confirmed reconciliation;
- supplier refund/customer refund en toepassing van credit balances;
- AR/AP aging en complete operational reporting;
- opening balances en gecontroleerde cutover uit bestaande administraties.

## 8. Operations- en securitygaps

Delivery heeft queue/outbox, workerheartbeat en `delivery:health`. Compose bevat service-
healthchecks en de deliverydesigns benoemen durable MySQL/artifactvolumes. Er is in de
repository echter geen aantoonbaar end-to-end MySQL-plus-artifact backup/restoreproces,
periodieke restore-test, deploymentrunbook of brede monitoring/SLO-laag. Logs bestaan
capabilitygericht, niet als volledige operationsoplossing.

Productontwikkeling kan gecontroleerd doorgaan; dit is geen blocker voor B3. Voor echte
productie-ingebruikname zijn backup/restore-test, deployment, worker/schedulerbewaking,
secretbeheer, centrale health en alerting verplicht.

## 9. Acceptance-readinessbeleid

Iedere toekomstige productbatch met Webfunctionaliteit is pas praktisch accepted nadat
voor `dev-admin@financecore.local` expliciet is gerapporteerd:

- required en effective permissions;
- required canonical roles en huidige assignments, zonder duplicates;
- zichtbare/geautoriseerde navigation en create/finalize/post/reverse-acties;
- benodigde Administration-masterdata: Journals, LedgerAccounts, TaxCodes,
  AdministrationBankAccounts, Relationclassificaties en documentsettings;
- benodigde posting/configuration-readiness met status `Success`;
- een concreet handmatig happy-pathscenario met verwachte accountingfacts.

Ontbrekende masterdata levert een duidelijke readinessmelding of een expliciete
development setup; nooit fictieve data om tests groen te maken. Dit is uitsluitend
development acceptancebeleid. Het ontwerpt geen productie-user bootstrap en kent geen
rollen automatisch toe aan productie-memberships.

## 10. PC success-message usability-gap

Huidige melding bij iedere succesvolle post:

> Creditnota is geboekt en automatisch met de bronfactuur verrekend.

Bij de acceptance was `matchedAmount = EUR 0`. De melding is daarom geen boekhoudkundige
correctnessfout—de persisted facts zijn goed—maar wel **misleidende financiële
presentatie** en een usability-regressie; meer dan cosmetisch.

Kleinste juiste fix: laat de controller na Success de bestaande
`PostPurchaseCreditInvoiceResult`/`PurchaseCreditPostingReadModel`-bedragen gebruiken:

- matched > 0 en credit remaining = 0: volledig automatisch verrekend;
- matched > 0 en credit remaining > 0: gedeeltelijk automatisch verrekend; creditsaldo
  resteert, met de bedragen;
- matched = 0: geboekt; geen bedrag automatisch verrekend; supplier credit balance
  resteert, met het resterende bedrag.

De tekst leidt nooit financiële state af; zij presenteert het postingreadmodel. Advies:
aparte regressiefix vóór B3, inclusief fully-open/partially-paid/fully-paid Webtests.

## 11. Payment reversal assessment

Posted BankTransactions, JournalEntries, PaymentAllocations en Applied Settlements zijn
immutable. `OpenItem::reverseSettlement()` en `reversed_settlement_id` ondersteunen een
append-only volledige reversal van één settlement, inclusief one-reversal uniqueness.
Wat ontbreekt:

- een BankTransaction-reversalidentity en één-op-één linkage;
- een tegen-JournalEntry die exact de oorspronkelijke bank- en controlaccountregels
  spiegelt;
- atomische reversal van alle settlements uit de originele Payment;
- actor/reason/timestamps, use cases, reader, permission en Webflow;
- lockvolgorde, double-reversal- en concurrent settlement/matchtests.

Classificatie: **critical correctness** en implementation-ready na contractalignment.
De bestaande Accounting-primitieven zijn voldoende; er is geen schema- of application-
predecessor buiten B3 zelf.

## 12. Accounting periods en posting locks

`FiscalYear` en `AccountingPeriod` bestaan niet als duurzame productmodellen. Er is geen
period close/reopen, posting lock, fiscal lock, actor/reason audit of centrale guard in
PostingEngine/Application. `NumberSequenceResetPolicy::FiscalYear` is slechts een
sequencebegrip en geen periodewaarheid.

Risico: een geautoriseerde gebruiker kan momenteel een expliciete historische
PostingDate kiezen zolang lokale document/open-item datumguards slagen. Daarmee kunnen
eerder beoordeelde rapportages en fiscale perioden achteraf veranderen.

Classificatie: **High correctness/production safety**. Deze batch volgt direct na B3 en
wordt predecessor voor officiële VAT/ICP-finalisatie en production-grade close. B3
moet alvast een expliciete reversal PostingDate bewaren, maar verzint geen lockbeleid.

## 13. Audit

BankTransactions bewaren Created/Finalized/Posted actor en tijd. PurchaseCredits bewaren
Created/Finalized/Posted/Cancelled. PurchaseInvoices hebben FinalizedBy/At maar niet
dezelfde volledige lifecycledekking. Sales-documenten, delivery requests/attempts,
outbox en outcome resolutions hebben capabilityspecifieke auditfacts.

Er is geen generieke tenant-scoped mutation audit voor settings, masterdata en alle
Draftwijzigingen met before/after, actor, requestcorrelation en reason. Classificatie:
**High technical/operational debt**, maar geen blocker voor B3 wanneer B3 zijn eigen
immutable reversal-auditfacts volledig bewaart.

## 14. Incoming documents

PurchaseInvoice en PurchaseCredit hebben geen ontvangen PDF/image attachment. W4E
`DocumentArtifact` is gegenereerde Sales-output en wordt niet hergebruikt als incoming-
documentmodel.

Een latere capability vereist private storage, server-generated keys, size- en
MIME/contentallowlists, filename safety, malware-scan boundary, SHA-256/integrity,
immutable received document, same-tenant source linkage, retention/download audit en
duplicate warnings. OCR is een afzonderlijke derived interpretation; het originele
bestand blijft waarheid. Prioriteit: **High daily workflow**, na correctnessbatches.

## 15. Purchase Orders

Er is geen PurchaseOrder aggregate, persistence, Web, approval, goods/services receipt
of three-way PurchaseInvoice matching. Businesswaarde is hoog voor procure-to-pay, maar
het is geen accounting/fiscal correctness predecessor. Eerst zijn productrequirements
voor approval, commitment versus boeking, partial receipt, price/quantity variance en
matching nodig. Prioriteit: **Medium**, na incoming documents of volgens klantvraag.

## 16. Bank Import en Reconciliation

Er is geen CAMT.053/MT940/CSV/PSD2-parser, BankStatement, StatementLine, importidentity,
immutable provenance of reconciliationqueue. De eerdere aanbeveling blijft correct:

`BankStatement → immutable StatementLine → user-confirmed BankTransaction → bestaande
B2 posting/settlement`.

Harde duplicate identity gebruikt bankgeleverde account/provider/statement/entry-
references; file hash detecteert alleen exact dezelfde upload. Datum+bedrag+omschrijving
is hoogstens een warning. Reconciliation is afzonderlijk van parsing en ondersteunt
exacte referenties/IBAN/amount als uitlegbare suggesties, maar geen fuzzy autoposting.
Zij moet ook lijnen kunnen koppelen aan reeds handmatig geboekte BankTransactions om
duplicate bank movements te voorkomen. Split payment, fees, unmatched cash en suspense
vereisen expliciete user-confirmed modellen. Prioriteit: **High automation**, maar pas
na B3 en periods/locks.

## 17. International Purchase VAT

De huidige domestic architectuur is one-leg Input VAT. De required predecessor is een
multi-leg Purchase fiscal model met:

- één immutable taxable source base;
- typed regimes voor EU goods, EU/non-EU services, domestic reverse charge en import;
- afzonderlijke Output- en Input-legs/TaxPostings met pair/source trace;
- deductibility ratio/allocation en niet-aftrekbare cost/asset impact;
- regime-owned fiscal date/evidence en accountconfiguration;
- credits die ieder gerealiseerd fiscal leg exact reversen.

Volledig, gedeeltelijk en niet-aftrekbaar moeten afzonderlijk representabel zijn. Een
EUR-only eerste implementatie is mogelijk door non-EUR hard te weigeren; FX-accounting
is dan geen blocker. Non-EUR vereist later immutable rate source, rate date, transaction
amount, EUR fiscal amount en rounding. Prioriteit: **High fiscal**, maar de predecessor
heeft hoog architectuurrisico en volgt na de directe correctnessbatches.

## 18. VAT/ICP readiness

VAT-return readiness: **partially ready / blocked voor officiële aangifte**. ICP:
**partially ready / blocked**. Besluit: VAT/ICP blijft geparkeerd: **JA**.

Exacte blockers:

- international/reverse-charge/import Purchase sourceflows en multi-leg truth;
- partial/non-deductible VAT;
- complete corrections, waaronder toekomstige fiscal cases;
- fiscale perioden, close/locks en reopenbeleid;
- officiële rubriekaggregatie, rounding, reconciliation en auditworkflow;
- EUR-conversie voor non-EUR;
- voor ICP: complete EU goods/services sources, VAT-ID evidence, credits/corrections,
  perioden en reconciliation.

Sales-data alleen is onvoldoende reden om ICP te bouwen.

## 19. Reporting, opening balance en migration

P&L, Balance Sheet, Trial Balance, General Ledger, Open Items en VAT Overview bestaan als
Domaincalculators/readfoundation. Het dashboard toont beperkte operationele informatie.
Een volledige Reporting-Webmodule, AR/AP aging, cash/bankoverzicht, purchasing/sales-
dashboards, export, scale-projecties en audit drill-down ontbreken. Prioriteit: **Medium
to High business value**, afhankelijk van production timeline; periods/locks zijn een
belangrijke predecessor voor afgesloten historische rapportage.

Opening balance/cutover ontbreekt: geen initial AR/AP, initial bank, historical import,
cutover aggregate/workflow of reconciliation. Dit is **High production-readiness** vóór
echte ingebruikname, maar geen blocker voor verdere gecontroleerde productontwikkeling.
Een toekomstig model moet openings-JournalEntries via PostingEngine, source provenance,
initial OpenItems, bank reconciliation en immutable cutoveraudit scheiden.

### Number sequences

Sales gebruikt tenant-scoped, concurrency-safe `next_value`-sequences voor Quotation,
Order, SalesInvoice en SalesCreditInvoice. Relations gebruikt hetzelfde patroon voor
Customer en Supplier. PurchaseInvoice- en PurchaseCredit-nummers zijn externe
leveranciersidentiteiten en krijgen terecht geen interne sequence. De generieke Domain-
`NumberSequence` kent een `FiscalYear` resetpolicy, maar de actuele Sales/Relation-
persistence bewaart geen jaar/resetperiode; feitelijke year rollover, gapbeleid en
fiscale reset zijn dus niet productmatig geïmplementeerd.

AccountingPeriods/FiscalYears mogen toekomstige sequence rollover niet impliciet uit
een kalenderjaar afleiden. Een aparte policy moet bepalen welke documenttypes resetten,
welk boekjaar geldt, hoe gaps/audit worden behandeld en hoe concurrency rond rollover
werkt. Dit is geen B3-blocker, maar wel dependency-impact van de periods/locks-batch.

### Correction model

Beschikbaar zijn SalesCredit en PurchaseCredit voor full-source document/taxreversals,
plus Domain/persistence voor OpenItem Settlement Reversal. Niet beschikbaar zijn Bank-
payment reversal als productflow, generic manual JournalEntry correction en een aparte
tax-correctionworkflow buiten broncredits. De grootste correctnessgap is daarom de
ontbrekende atomische bankreversal; B3 productiseert precies dat pad.

## 20. Candidate comparison

| Kandidaat | Correctness | Fiscaal | Dagwaarde | Risico indien afwezig | Unlocking | Readiness | Architectuurrisico | Testbaarheid | Production impact | Overall |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| A International Purchase VAT predecessor | High | Critical | Medium | High internationaal | High | Medium | High | High | High | High |
| B Bank Import & Reconciliation | Medium | Low | Critical | Medium door manual fallback | High automation | Medium | High | Medium | High | High |
| C Payment Reversal | Critical | Low | High | Critical incorrect cash | High correction safety | High | Medium | High | Critical | **Critical** |
| D Accounting Periods & Posting Locks | Critical | High | Medium | Critical bij close/productie | Critical | Medium | High | High | Critical | Critical, na C |
| E Incoming Purchase Documents | Low | Low | High | Medium | OCR/workflow | Medium | High security | High | High | High |
| F Purchase Orders | Low | Low | High | Medium | Procure-to-pay | Low/Medium | High | Medium | Medium | Medium |
| G Reporting foundation/Web | Medium | Medium | High | Medium | Insight | Medium/High | Medium | High | High | High |
| H Opening Balance/Migration | High | Low | Low tijdens build | Critical voor cutover | Production use | Low | High | Medium | Critical | High before go-live |

Volgens correctness vóór fiscal truth, workflow en automation wint C. D is breder maar
lost niet de nu aantoonbare onherstelbare cashfout op; C kan de bestaande reversalbasis
direct veilig productiseren. D volgt onmiddellijk.

## 21. Exact gekozen volgende hoofdproductbatch

**De volgende hoofdproductbatch is B3 – Bank Payment Reversal.**

Waarom nu: B2 laat geautoriseerde gebruikers posted cashtruth maken, maar een fout in
rekening, bedrag, Relation of allocations heeft geen ondersteund herstelpad. B3 maakt
de boekhouding corrigeerbaar zonder posted facts te muteren en benut reeds bewezen
JournalEntry-immutability, OpenItem settlement reversal, postinglinkage en locking.

### Harde V1-scope

Included:

- exact één volledige reversal per Posted manual B2 BankTransaction;
- expliciete reversal date en verplichte reason;
- exact gespiegeld tegen-JournalEntry via PostingEngine in hetzelfde Bank Journal en
  dezelfde historische accounts/currency/amounts;
- één Reversal Settlement per originele Applied Settlement;
- immutable one-to-one linkage en actor/timestamps;
- list/detail/reverse Webflow, authorization en idempotency;
- atomische transaction, deterministic OpenItem locks en echte MySQL-racetests.

Excluded:

- partial reversal, edit/delete van Posted facts, reversal van een reversal;
- een echte nieuwe bankmovement/refund;
- imported statements/reconciliation, overpayment/suspense en FX;
- document/tax reversal, generic journal correction en period management.

Accountingimpact: contra-entry herstelt bank/control balances en Reversal Settlements
heropenen de betrokken OpenItems per reversal date. Fiscal impact: geen TaxPosting; een
bankpayment is geen VAT source.

## 22. Predecessors en definitieve storysplit

Volgorde:

`PC-SUCCESS-MESSAGE regressiefix → B3 Bank Payment Reversal → Accounting Periods &
Posting Locks`.

Geen functionele predecessor voor B3. Definitieve split:

1. **B3-000 – Align Bank Payment Reversal Contracts**: semantics, eligibility,
   historical lines, date/reason, audit, idempotency, ordering en failurebeleid.
2. **B3-001 – Add Reversal Authorization & Persistence**: onafhankelijke permission,
   canonical rolemapping zonder membershipassignment, one-to-one tenant-safe linkage,
   repositories/readmodels en uniqueness.
3. **B3-002 – Reverse Bank JournalEntry Atomically**: historical exact contra-request
   via PostingEngine, immutable linkage en rollback/at-most-once.
4. **B3-003 – Reverse OpenItem Settlements**: alle originele allocations, deterministic
   locks, append-only Reversal Settlements, temporal validation en concurrency.
5. **B3-004 – Add Bank Reversal Web Flow**: detailactie, reason/date, typed errors,
   CSRF/authorization en resultaatpresentatie.
6. **B3-005 – Review, Regression & Development Acceptance**: double/concurrent reversal,
   partial/multi-item, later history, tenant/security, full validation en manual E2E.

## 23. Authorization, security en acceptancereadiness

Nieuwe permission nodig: **JA**, voorstel `BANKING.PAYMENTS_REVERSE`. Hergebruik van
`BANKING.PAYMENTS_POST` is niet veilig: posten en corrigeren zijn onafhankelijk
revocable high-impact acties. De definitieve B3-001-designstory bepaalt stable identity
en canonical mapping, bij voorkeur een aparte `BANKING_REVERSER`-rol of een expliciet
onderbouwde bestaande canonical role-uitbreiding. Nooit role-name authorization en
nooit automatische membershipassignment.

Securitygrenzen:

- server-owned AdministrationId en same-tenant source/linkage/FK's;
- typed route UUID, allowlisted date/reason, bounded reason, geen mass assignment;
- POST-mutatie met CSRF, escaped output, geen accountingtruth in JavaScript;
- active membership, runtime revocation en permission independence;
- source/status/linkage opnieuw lezen onder locks; geen hidden accountinput;
- audit logt geen onnodige persoonsgegevens of bankpayload.

Development acceptancecheckpoint voor `dev-admin@financecore.local`: required/effective
permission, canonical role en assignment, navigation/action, active bankaccount,
Bank Journal, Bank LedgerAccount, `BankingPostingConfiguration = Success`, geschikte
Posted BankTransaction en concrete open-itemfixture. Geen production auto-assignment.

## 24. Voorlopig logisch datamodel

Nieuwe aggregate/fact: `BankTransactionReversal` met ReversalId, AdministrationId,
source BankTransactionId, source posting/JournalEntryId, reversal JournalEntryId,
PostingDate, reason, actor en created/posted timestamps. De bron BankTransaction blijft
immutable Posted.

Categorieën:

- systeemdata: nieuwe stable permission/roledefinition;
- Administration masterdata: geen nieuwe; bestaande bankaccount/config wordt gelezen;
- transactionele data: reversalheader/linkage;
- accounting facts: nieuw Posted contra-JournalEntry en bestaande append-only Reversal
  Settlements;
- auditfacts: actor, reason, timestamps, source/reversal identities;
- fiscale/imported facts: geen nieuwe.

Constraints: AdministrationId op iedere nieuwe row; composite same-tenant FKs; RESTRICT
naar source transaction/posting/entries/users; geen CASCADE over financial facts; unique
`(administration_id, source_bank_transaction_id)`; unique reversal JournalEntry; één
reversal per Applied Settlement via bestaande constraint; immutable IDs; deterministic
locks op source BankTransaction en gesorteerde OpenItems. Dit is logisch ontwerp; er is
geen SQL gegenereerd.

## 25. Manual acceptance scenario

Fixture: Posted SupplierPayment `BET-REV-001` EUR 121 tegen één Posted PurchaseInvoice-
Payable EUR 121. Voor reversal: original bank JournalEntry boekt Debit AP 121/Credit
Bank 121; Applied Settlement 121; invoice remaining EUR 0.

Actie: geautoriseerde dev-admin kiest **Betaling terugdraaien**, reversal date
29 augustus 2026, reason `Verkeerde handmatige bankboeking`, bevestigt POST.

Verwacht:

- originele BankTransaction, JournalEntry, allocation en Settlement ongewijzigd;
- één BankTransactionReversal en exact één nieuwe Posted JournalEntry;
- contra-entry Debit Bank EUR 121/Credit historische AP EUR 121;
- één Reversal Settlement EUR 121 verwijzend naar het originele Applied Settlement;
- PurchaseInvoice Payable remaining opnieuw EUR 121;
- geen TaxPosting, Match of nieuwe echte BankTransaction;
- tweede/concurrente submit maakt geen facts en meldt AlreadyReversed;
- andere Administration blijft ongewijzigd.

## 26. Risico's, deferred scope en eerstvolgende actie

Belangrijkste B3-risico's zijn historische accounttrace, reversaldatum versus latere
settlement/matchhistory, deadlocks bij multi-item Payments, dubbel reversal en verwarring
tussen accounting correction en echte bankrefund. B3-000 moet deze terminologie en
temporal policy eerst definitief vastleggen.

Deferred: partial reversal, refund/new bankmovement, bankimport/reconciliation,
AccountingPeriods/locks, generic journal corrections, generic audit, international VAT,
incoming documents/OCR, Purchase Orders, reporting Web, opening balances en VAT/ICP.

Eerstvolgende actie: implementeer uitsluitend de aparte PC-success-message regressiefix.
Start B3 daarna als eigen batch bij B3-000; start hem niet vanuit deze review.

Fiscal source check required: **NEE**. B3 corrigeert uitsluitend een bestaande
BankTransaction/JournalEntry/Settlement en creëert geen TaxPosting of fiscale
bronregel. De fiscale onderwerpen in deze review blijven ontwerpclaims uit de reeds
gecontroleerde PROJECT-GAP-002/003/004-bronnen en moeten vóór een toekomstige fiscale
implementatie opnieuw bij de Belastingdienst worden gecontroleerd.

Dependencycheck B3: Domain foundation JA; Application foundation JA; persistence
predecessor NEE; authorization predecessor NEE (onderdeel B3-001); accounting
predecessor NEE; fiscal predecessor NEE; masterdata predecessor NEE; operations
predecessor NEE; concurrencymodel voldoende JA, met B3-tests; manual acceptance
technisch mogelijk JA.
