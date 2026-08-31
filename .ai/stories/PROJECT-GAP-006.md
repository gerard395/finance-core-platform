# PROJECT-GAP-006 – Post-B3 Product Direction Review

Reviewdatum: 31 augustus 2026. Type: design/inventarisatie/roadmap. Baseline:
`5c7a07bcf0b572619c616437cc7f50d1f5831691`. Deze review wijzigt geen productiecode,
schema, permissions of database-inhoud en start geen volgende feature.

## 1. Managementsamenvatting

B3 is gemerged, volledig gevalideerd en handmatig geaccepteerd. Een foutieve handmatige
bankbetaling kan nu append-only worden teruggedraaid zonder de originele
BankTransaction, JournalEntry, Applied Settlement, OpenItemMatch of fiscale waarheid te
muteren. De tijdens B3 gevonden same-date settlement-orderingfout is eveneens opgelost:
financiële causaliteit en niet een willekeurige UUID bepaalt de historievolgorde.

Daarmee is de eerdere directe correctnessgap gesloten. De breedste resterende
correctness- en production-safetygap is nu dat iedere bestaande posting- en
reversalflow historische `PostingDate`s accepteert zonder BookYear, AccountingPeriod of
centrale lockcontrole. Geautoriseerde gebruikers kunnen daardoor achteraf financiële
historie wijzigen die al beoordeeld of gerapporteerd kan zijn.

**De volgende batch is AP – Accounting Periods & Posting Locks.**

De volgorde uit PROJECT-GAP-005 blijft na B3 correct: AP eerst, daarna de International
Purchase VAT predecessor en vervolgens Bank Import & Reconciliation. Opening
Balance/Migration en Backup/Restore blijven noodzakelijke production-readinesssporen,
maar verdringen de centrale correctnessguard niet. VAT/ICP reporting blijft geparkeerd.

## 2. Baseline na B3

| Gebied | Beschikbare capability | Actuele grens |
| --- | --- | --- |
| Sales | Invoice, full-source credit, Receivable, payment/settlement en delivery | Domestic truth; geen period lock; partial credits/refunds deferred |
| Purchasing | Invoice, full-source credit, Payable, supplier payment en supplier credit balance | Domestic EUR; geen period lock; internationale multi-leg VAT ontbreekt |
| Banking | Manual CustomerReceipt/SupplierPayment, settlement en volledige append-only payment reversal | Geen statementimport, provenance of reconciliationautomation; geen period lock |
| Accounting | PostingEngine, immutable JournalEntries, OpenItems, Settlements, Settlement Reversals, Matches en historische control accounts | Geen BookYear/AccountingPeriod, close/reopen of posting-date guard; manual JournalEntry bestaat conceptueel maar niet als product-Webflow |
| Fiscal | Domestic Sales/Purchase originals en full-credit reversals als immutable TaxPostings | Internationale Purchase VAT, deductibility, fiscale locks en officiële VAT/ICP-reporting ontbreken |

De capabilitymatrix onderscheidt productcapability van developmentdata. De afwezigheid
van actuele documenten in een database zou geen afwezigheid van codecapability bewijzen;
deze review baseert zich op stories, productiecode, schema en tests.

## 3. B3 manual acceptance

De handmatige acceptance van `BET-TEST-INK-002` is **PASS**:

- de originele SupplierPayment van EUR 121 bleef `Posted`;
- reversal date was `2026-08-28` en de reden is duurzaam gedocumenteerd;
- één contra-Journal boekte Debit Bank EUR 121 / Credit historische AP EUR 121;
- exact één Settlement Reversal werd toegevoegd;
- het oorspronkelijke Payable werd hersteld naar open EUR 121;
- `TEST-CREDIT-001` bleef `Posted` met open `Payable/Debit` EUR 121;
- OpenItemMatches bleven onaangeraakt;
- B3 maakte nul TaxPostings;
- duplicates, orphans en ongeldige balances waren nul: integriteit **PASS**.

De correctnessfix uit B3-003 ordent same-date settlementhistorie via de duurzame
original→reversal-causaliteit. UUID's zijn uitsluitend tie-breakers tussen economisch
equivalente chains en bepalen geen saldo of geldigheid.

## 4. Accounting periods en huidig risico

Feitelijke code- en schemainspectie vindt geen duurzame `BookYear`/`FiscalYear`- of
`AccountingPeriod`-implementatie, statusmodel, periodegenerator, close/reopen-command,
auditrecord, posting lock, fiscal lock of centrale posting-date validation. De namen in
`.ai/DOMAIN_MODEL.md` en de generieke permissionnaam `Manage Accounting Periods` zijn
ontwerpintentie, geen geïmplementeerde capability. Ook
`NumberSequenceResetPolicy::FiscalYear` is geen boekjaar- of lockwaarheid.

Het gevolg is dat een gebruiker met de bestaande post/reverse-permission achteraf kan
posten of tegenboeken in een historische periode zolang de lokale document- en
OpenItem-datumregels slagen. Een eerdere Trial Balance, P&L, General Ledger, Open Items-
of VAT-selectie kan daardoor veranderen zonder expliciete reopen, reden of audittrail.
Dat raakt Sales, Purchasing, PurchaseCredit, Banking en B3-reversals uniform. Het maakt
betrouwbare month/year close, opening balances en toekomstige VAT-finalisatie onmogelijk.

Prioriteit: **Critical accounting correctness / production safety**. Tijdens lokale
ontwikkeling bestaat een manual fallback, maar vóór production-grade financiële
afsluiting is dit een harde predecessor.

## 5. Voorlopig periodemodel

### BookYear

`BookYear` is Administration-owned en bewaart een immutable identiteit, startdatum en
einddatum plus een afgeleide/beheerde status. Boekjaren van één Administration mogen
niet overlappen. V1 ondersteunt expliciete, aaneengesloten kalender- of gebroken
boekjaren; het model leidt een boekjaar niet stilzwijgend uit een documentnummer of
kalenderjaar af.

### AccountingPeriod

`AccountingPeriod` behoort tot exact één same-Administration `BookYear`, heeft een
immutable start- en einddatum binnen dat boekjaar en een status. Perioden binnen een
BookYear mogen niet overlappen en de gegenereerde standaardset moet het boekjaar volledig
dekken. Een `PostingDate` is postbaar wanneer exact één bijbehorende periode bestaat en
die periode Open is.

### Statusbesluit

V1 gebruikt exact `Open` en `Closed`. `SoftClosed` wordt **niet** opgenomen: zonder een
volledig ontworpen uitzonderingenset, rapportfreeze en aparte overridepermission is het
feitelijk een tweede soort Open en vergroot het de kans op bypasses. Een later bewezen
reviewworkflow kan SoftClosed additief ontwerpen.

- **Open:** nieuwe postings en tegenboekingen op een datum in de periode zijn toegestaan,
  mits de mutation zelf authorized en inhoudelijk geldig is.
- **Closed:** iedere nieuwe financiële posting of reversal met een `PostingDate` in de
  periode wordt vóór financiële writes geweigerd.
- **Reopened:** geen blijvende derde status. Een expliciete audited reopen-transition
  zet Closed terug naar Open en bewaart een afzonderlijk immutable reopen-auditfact.

BookYear-status mag in V1 uit zijn perioden worden afgeleid of als workflowstatus worden
vastgelegd, maar mag de period guard nooit tegenspreken. AP-000 beslist dit contract vóór
persistence. Sluiten verwijdert of muteert geen financiële feiten.

## 6. Posting-lock policy

De lock is Administration-scoped en gebaseerd op de uiteindelijke Accounting
`PostingDate`, niet op invoice-, credit-, transaction-, supply- of documentdatum. De
controle gebeurt binnen dezelfde outer transaction als de posting, vóór PostingEngine
of andere financiële writes, en leest/lockt authoritative period state zodat close
versus post serialiseerbaar is. Presentationchecks zijn alleen UX en nooit authoritative.

De volgende mutations moeten de guard gebruiken:

| Mutation | Te controleren datum |
| --- | --- |
| SalesInvoice Post | de PostingDate die de Sales-orchestrator aan PostingEngine levert |
| SalesCredit Post | de PostingDate van de credit-tegenboeking |
| PurchaseInvoice Post | de expliciet gekozen PostingDate |
| PurchaseCredit Post | de expliciet gekozen PostingDate |
| BankTransaction Post | de expliciet gekozen PostingDate |
| BankTransaction Reversal | de expliciete ReversalPostingDate |

De huidige Salesflows leiden hun PostingDate af uit invoice-/creditdatum; AP verandert
dat niet stilzwijgend, maar controleert de feitelijk aan PostingEngine geleverde datum.
Als een manual JournalEntry-capability wordt ontsloten, moet zowel Post als iedere
tegenboeking dezelfde guard gebruiken. Opening-balance-import gebruikt later eveneens
PostingEngine en een expliciet aangewezen open periode; er komt geen bypass op basis van
het label “opening”.

Een ontbrekend BookYear, ontbrekende periode, Closed periode en concurrencyconflict
krijgen typed veilige outcomes. Geen controller of capability bouwt een eigen afwijkende
perioderegel. Reads/reporting blijven beschikbaar voor Closed perioden.

## 7. PostingDate versus FiscalReportingDate

`PostingDate` bepaalt de Accounting-periode van JournalEntry, OpenItem-opening,
Settlement en financiële reversal. `FiscalReportingDate` bepaalt de fiscale
rapportagecontext wanneer het fiscale regime dat voorschrijft. De huidige Purchasing-
flow bewijst al dat beide kunnen verschillen: JournalEntry gebruikt de expliciete
PostingDate, TaxPosting gebruikt de persisted FiscalReportingDate.

Een gesloten AccountingPeriod zegt daarom niet automatisch dat de bijbehorende fiscale
reportingperiode gesloten is, en een open fiscale periode opent geen AccountingPeriod.
AP V1 implementeert **uitsluitend accounting posting locks**. Fiscal-owned `TaxPeriod`,
returnfinalisatie, fiscal locks, suppletie/correction-period policy en hun permissions
komen later als afzonderlijk contract. AP mag daarvoor een readcontract leveren, maar
voegt geen fiscale status toe aan AccountingPeriod en blokkeert niet op documentdatum.

Toekomstige VAT-reporting moet expliciet bepalen hoe een toegestane correctieboeking in
een open AccountingPeriod met een FiscalReportingDate voor een reeds gefinaliseerde
fiscale periode wordt behandeld. Dat beleid wordt niet stilzwijgend door AP ingevuld.

## 8. Close- en reopenbeleid

Close en reopen zijn high-impact, expliciete Application-mutations. Close vereist dat de
periode Open is, bewaart actor/tijd en controleert structurele geldigheid; AP V1 hoeft
geen fiscale return te finaliseren. Reopen vereist **JA**, een verplichte getrimde reden,
actor en authoritative timestamp. Silent reopen en een generieke settingspermission als
bypass zijn verboden.

Reopen zet de operationele status terug naar Open, maar appent een immutable auditfact
met periode, vorige/nieuwe status, reason, actor en tijd. Daardoor blijven rapporteurs
zien dat een eerder gesloten periode opnieuw wijzigbaar werd. Een latere close krijgt
een nieuw auditfact; eerdere close/reopenfacts blijven bestaan. Rapportages moeten Closed
niet als onveranderlijke historische claim presenteren wanneer een latere reopen in de
audit staat. Fiscale reopen blijft afzonderlijk.

## 9. Authorization en canonical roles

Kleinste correcte onafhankelijke permissionset:

- `ACCOUNTING.PERIODS_VIEW`: BookYears, perioden, status en audit raadplegen;
- `ACCOUNTING.PERIODS_MANAGE`: BookYear/periodsetup en veilige generatie beheren, maar
  niet sluiten of heropenen;
- `ACCOUNTING.PERIODS_CLOSE`: een Open periode expliciet sluiten;
- `ACCOUNTING.PERIODS_REOPEN`: een Closed periode met verplichte reden heropenen.

De bestaande catalogusnaam `Manage Accounting Periods` moet in AP-000 met deze typed
scheiding worden uitgelijnd; geen implementation gebeurt in deze review. `View Ledger`,
`Create Journal Entry`, `Post Journal Entry` en Administration-settings impliceren geen
Close/Reopen. Close en vooral Reopen blijven afzonderlijk intrekbaar.

Voorgestelde canonical roles: een `ACCOUNTING_PERIOD_MANAGER` met View + Manage + Close
en een least-privilege `ACCOUNTING_PERIOD_REOPENER` met View + Reopen. Bestaande
Administration-/Accountingrollen worden in AP-001 expliciet geïnventariseerd; er is geen
role-name authorization en geen automatische production-membershipassignment.

Voor development acceptance krijgt `dev-admin@financecore.local` pas vóór AP-004
expliciet de benodigde canonical role(s), zonder duplicate assignment. Required en
effective permissions, actieve membership, navigatie en iedere actie worden read-only
geverifieerd en gerapporteerd.

## 10. Configuratie en masterdata

Een Administration heeft vóór de eerste financiële posting minimaal één passend
BookYear en één dekkende Open AccountingPeriod nodig. Production provisioning is
expliciet user-driven: de gebruiker kiest BookYear-grenzen en kan een voorgestelde
maandindeling laten genereren en controleren. Er is geen automatische destructieve
year rollover, geen impliciet sluiten en geen onzichtbare productiebootstrap.

Voor een nieuwe lege Administration mag een expliciete setupwizard een standaardset
voorstellen, maar pas na bevestiging creëren. Voor bestaande Administrations vereist AP
een veilige introductiepolicy: geen historische posting wordt gewijzigd; de eerste
BookYear-/periodset wordt expliciet gekozen en gevalideerd tegen bestaande PostingDates.
AP-000/AP-001 moeten beslissen of een tijdelijk setup-required outcome nodig is om een
onbedoelde productiebreuk tijdens rollout te voorkomen.

Development acceptance mag deterministische lokale BookYear-/periodmasterdata voor het
concrete scenario provisioneren en de canonical roles expliciet toekennen. Dat is geen
production seeder, geen production auto-assignment en geen rechtvaardiging voor een
runtime bypass.

## 11. International Purchase VAT

De International Purchase VAT predecessor blijft **High fiscal**, maar volgt na AP.
De huidige Purchase-fiscale waarheid ondersteunt één Input-leg. Reverse charge en
intra-EU/importscenario's vereisen één taxable source base, afzonderlijke Output- en
mogelijk beperkte Input-legs, duurzame pair/source trace, deductibility en regime-owned
fiscal-date/evidencepolicy. Credits moeten ieder gerealiseerd fiscal leg exact reversen.

EUR-only is een valide eerste slice wanneer non-EUR hard wordt geweigerd; FX is dan geen
directe blocker. Implementation readiness is **Medium**: de benodigde grenzen zijn
bekend, maar multi-leg/deductibility heeft hoger architectuurrisico en officiële
fiscal-date/lockinteractie profiteert van de expliciete AP-scheiding. International VAT
mag AccountingPeriod niet als fiscale lock hergebruiken.

## 12. Bank Import & Reconciliation

B3 maakt handmatige bankboekingen veilig corrigeerbaar, maar maakt import niet
correctnesskritischer dan AP. De gebruiker kan nog handmatig boeken; zonder AP kan ook
een geïmporteerde, bevestigde BankTransaction onbeperkt historisch worden gepost of
gereversed.

Het model blijft:

`BankStatement → immutable StatementLine → user-confirmed BankTransaction`.

BankStatement/Line bewaren provider/account/statement/entry-identiteiten en immutable
provenance. File hash detecteert alleen dezelfde upload; datum+bedrag+omschrijving is
hoogstens een duplicate warning. Reconciliation levert uitlegbare voorstellen, maar
user confirmation creëert/gebruikt de bestaande BankTransactionflow. Zij moet ook aan
een reeds handmatig geboekte movement kunnen koppelen. Readiness is **Medium** door
parser-, duplicate-, fees/split/suspense- en concurrencykeuzes. Prioriteit blijft High
automation, na AP en de fiscale predecessor.

## 13. Andere high-risk gaps

- **Opening Balance/Migration:** High production readiness en noodzakelijk voor cutover.
  Vereist source provenance, PostingEngine, initial OpenItems, bankreconciliation en
  immutable audit. AP levert eerst de veilige PostingDate-/periodboundary.
- **Backup/Restore:** Critical vóór productie-exploitatie. Repositorybewijs voor een
  volledig MySQL-plus-artifact restoreproces en periodieke restoretest ontbreekt. Dit is
  een parallel operations gate, maar geen productbatch die de huidige postingcorrectness
  vervangt.
- **Generic audit:** High operational debt. AP levert capabilityspecifieke immutable
  close/reopenaudit; een generieke audittrail blijft apart.
- **Incoming documents/Purchase Orders:** hoge dagelijkse workflowwaarde, geen
  predecessor voor financiële afsluiting.
- **Reporting:** belangrijke businesswaarde, maar betrouwbare afgesloten rapportage
  vereist eerst periods/locks.
- **VAT/ICP:** blijft geparkeerd tot internationale/multi-leg Purchase VAT,
  deductibility, fiscale perioden/locks, correcties, rounding/reconciliation en EUR-
  conversie duurzaam zijn opgelost.

Geen van deze gaps verdringt AP als eerstvolgende hoofdproductbatch. Backup/Restore kan
organisatorisch parallel worden voorbereid zonder AP-scope te vermengen.

## 14. Candidate comparison

| Kandidaat | Accounting correctness | Fiscal necessity | Daily workflow | User risk if absent | Unlocking | Readiness | Architecture risk | Production impact |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| A. Accounting Periods & Posting Locks | **Critical** | High predecessor | Medium | **Critical: historische mutatie zonder reopen** | **Critical** | High/Medium | Medium/High concurrency | **Critical** |
| B. International Purchase VAT predecessor | High | **Critical internationaal** | Medium | High voor internationale users; scope kan worden geweigerd | High fiscal | Medium | **High** multi-leg/deductibility | High |
| C. Bank Import & Reconciliation | Medium | Low | **Critical automation** | Medium door manual fallback | High workflow | Medium | High provenance/matching | High |
| D. Opening Balance/Migration foundation | High | Low | Low tijdens build | Critical bij concrete cutover | High production | Low/Medium | High provenance/reconciliation | Critical vóór go-live |
| E. Backup/Restore hardening | Indirect | Indirect | Low zichtbaar | Critical bij incident | Operations gate | Medium | Medium platform | Critical vóór go-live |

AP wint omdat één centrale guard alle bestaande en toekomstige financiële
mutationpaden beschermt, betrouwbare close/reporting ontsluit en een noodzakelijke
grens levert voor migration en fiscal work. International VAT is daarna de grootste
fiscale uitbreiding; Bank Import levert daarna de grootste dagelijkse automationwinst.

## 15. Exact besluit en volgorde

**De volgende batch is AP – Accounting Periods & Posting Locks.**

AP komt nu omdat B3 de laatste concrete onherstelbare cashpostinggap heeft gesloten en
tegelijk bewijst dat ook corrections een expliciete PostingDate hebben die centraal
moet worden bewaakt. AP komt vóór International Purchase VAT omdat zonder periodboundary
nieuwe fiscale en financiële legs eveneens onbeperkt historisch wijzigbaar zijn. AP komt
vóór Bank Import omdat automation meer postingvolume toevoegt en daarmee het ontbreken
van locks vergroot. AP komt vóór Opening Balance/Migration omdat cutoverboekingen een
expliciete open-periodpolicy nodig hebben. Backup/Restore blijft een afzonderlijke
production gate en hoeft geen domeinbatch te verdringen.

Follow-upvolgorde:

1. AP – Accounting Periods & Posting Locks;
2. International Purchase VAT predecessor;
3. Bank Import & Reconciliation;
4. production-timelinegestuurd Opening Balance/Migration en Backup/Restore hardening;
5. overige workflow/reportingbatches.

VAT/ICP reporting blijft geparkeerd: **JA**.

## 16. Uitvoerbare storysplit

### AP-000 – Align Accounting Period & Lock Contracts

Leg aggregategrenzen, BookYear-/AccountingPeriod-invarianten, Open/Closed, typed
outcomes, authoritative PostingDate, fiscal separation, close/reopenaudit, lockvolgorde,
rollout naar bestaande Administrations en tests vast. Beslis BookYear-statusafleiding
en voorkom overlap/gaps. Geen schema voordat deze contracten akkoord zijn.

### AP-001 – Period Authorization & Persistence

Implementeer de vier onafhankelijke permissions, canonical roles zonder auto-assignment,
tenant-safe BookYear/AccountingPeriod persistence, uniqueness/overlapbescherming waar
mogelijk, immutable close/reopenaudit en Application read/manage/close/reopencommands.
Geen financiële postingguard in deze story.

### AP-002 – Posting-Date Lock Enforcement

Voeg één frameworkonafhankelijk Accounting-contract en transactioneel authoritative
periodcheck toe aan SalesInvoice, SalesCredit, PurchaseInvoice, PurchaseCredit,
BankTransaction Post en BankTransaction Reversal. Bewijs close-versus-post concurrency,
geen writes bij Closed/missing period, geen bypass via Web en behoud van PostingEngine-
exclusiviteit. Leg contract klaar voor manual JournalEntry/opening balance.

### AP-003 – Period Management Web Flow

Lever permission-scoped BookYear-/periodlist, setup/generation, closeconfirmation en
reopenflow met verplichte reden en zichtbare audit. Controllers/Blade bevatten geen
lock- of statuswaarheid. Read-only toegang blijft gescheiden van Manage/Close/Reopen.

### AP-004 – Review, Development Readiness & Regression

Review architectuur, tenant/security, transactionele races, alle zes postingpaths,
reportingreads en rollout. Provision uitsluitend expliciete developmentmasterdata/roles,
voer de handmatige E2E uit en bewijs dat er geen fiscale lock of production auto-
assignment is geïntroduceerd.

## 17. Development acceptance

Vóór handmatige acceptance wordt voor `dev-admin@financecore.local` expliciet
gerapporteerd:

- actieve Domain User, membership en Administration;
- required/effective `ACCOUNTING.PERIODS_VIEW`, `MANAGE`, `CLOSE` en `REOPEN`;
- canonical roleassignments zonder duplicates;
- zichtbare Accounting-navigation en alleen authorized acties;
- één expliciet development-BookYear met minimaal twee perioden, waarvan de testperiode
  Open is en een tweede periode Open blijft;
- geschikte masterdata en één concrete postbare fixture per representatief pad.

Minimale handmatige E2E:

1. post op een datum in een Open periode: toegestaan en één complete financial truth;
2. sluit die periode en controleer actor/tijd/audit;
3. probeer dezelfde mutation met dezelfde PostingDate: typed geweigerd, nul writes;
4. post in de andere Open periode: toegestaan;
5. reopen de eerste periode met verplichte reden;
6. post opnieuw op een geldige datum: toegestaan;
7. controleer dat close/reopenreason, actor en tijd zichtbaar en immutable zijn.

De scenarioselectie moet minstens één original posting en de B3 BankTransaction
Reversal afdekken; geautomatiseerde regressie dekt alle zes mutationtypes. Er is geen
production auto-assignment of impliciete BookYear-provisioning.

## 18. Implementation readiness

AP is **implementation-ready na AP-000 contractalignment**. Bekende ontwerpbeslissingen
zijn voldoende begrensd: V1 Open/Closed, audited reopen terug naar Open, PostingDate-
guard, afzonderlijke fiscal locks, vier permissions en vijf stories. AP-000 moet vóór
schemawerk nog exact vastleggen: BookYear-statusopslag versus afleiding, overlap/gap-
constraints, rolloutgedrag voor bestaande Administrations en de transactionele
close-versus-post lockorde.

Er is geen predecessor buiten AP-000. De batch wijzigt geen historische JournalEntries
of TaxPostings en introduceert geen PostingEngine-bypass.
