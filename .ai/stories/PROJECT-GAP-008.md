# PROJECT-GAP-008 – Bank Import & Reconciliation Architecture Review

## Besluit

Classificatie: **B – implementation-ready after predecessor**.

De verplichte predecessor is **BIR-000 – Align Bank Import & Reconciliation Contracts**.
De bestaande Banking/B2/B3-, PostingEngine-, OpenItem-, Settlement-, Match- en AP-kern is
voldoende als financiële basis. Wat nog ontbreekt is geen nieuwe boekhoudkern, maar een
expliciet contract voor immutable bankbrondata, format-specifieke duplicate identities,
promotie naar de bestaande BankTransaction, reconciliationstatus, Other-postingintent en
de atomische accept/post/link-boundary. BIR-000 legt die keuzes vast vóór schema of parser.

## Bestaande Banking-kern

`BankTransaction` is de Administration-owned aggregate root en bezit exact één `Payment`;
Payment bezit meerdere `PaymentAllocation` children. De immutable aggregate-identiteiten
zijn BankTransactionId, PaymentId en PaymentAllocationId. Een positieve signed EUR-Money
is `CustomerReceipt`, een negatieve `SupplierPayment`. Payment en allocations zijn
positieve Money. Eén Payment hoort bij één Relation en kan meerdere open items van diezelfde
Relation gedeeltelijk afwikkelen. Meerdere BankTransactions kunnen via append-only
Settlements samen één OpenItem afwikkelen.

De huidige lifecycle is `Draft → Finalized → Posted` en `Draft → Cancelled`. Draftvelden
zijn bankrekening, TransactionDate, signed amount, reference, description, Relation en
allocations. Finalize bevriest deze intent plus OpenItem type/side/Relation/control-account
snapshots. Finalize reserveert geen saldo. Post lockt BankTransaction en OpenItems, herleest
de actuele balance, controleert het AP-period op command PostingDate, gebruikt de actuele
same-tenant BankingPostingConfiguration en schrijft in één outer transaction:

- één immutable JournalEntry via PostingEngine;
- één append-only Settlement per allocation;
- één BankTransactionPosting-link;
- de Posted actor/timestamp.

CustomerReceipt boekt Debit Bank / Credit historische AR-control. SupplierPayment boekt
Debit historische AP-control / Credit Bank. AdministrationBankAccount is een afzonderlijke
operationele Administration-owned EUR-rekening; meerdere rekeningen per Administration
zijn ondersteund. BankingPostingConfiguration mapt iedere rekening naar één active Bank
Journal en één active Asset Bank LedgerAccount.

OpenItem-balance is afgeleid uit original, append-only Settlements/Settlement Reversals en
OpenItemMatches. Match is document-balance-truth, Settlement is cashtruth. B3 laat original
BankTransaction, posting, Journal en Settlements immutable, spiegelt bij reversal alle
historische journalregels en appendt per allocation een volledige Settlement Reversal plus
linkage. `Teruggedraaid` is afgeleid; de original blijft Posted. OpenItemlocks zijn
deterministisch gesorteerd en deadlockretry is begrensd.

Bestaande permissions zijn `BANKING.VIEW`, `BANKING.PAYMENTS_MANAGE`,
`BANKING.PAYMENTS_POST` en `BANKING.PAYMENTS_REVERSE`. Webcontrollers gebruiken typed
Application-readmodels en geen Eloquent/accountinglogica. GET muteert niet. AP-locking
gebruikt uitsluitend PostingDate; bank-TransactionDate en reversal PostingDate zijn
gescheiden waar het bestaande contract dat vereist.

### Capabilitymatrix

| Capability | Huidig | Richting |
| --- | --- | --- |
| Handmatig CustomerReceipt/SupplierPayment | Ja | Hergebruiken |
| Partial settlement van één OpenItem | Ja | Hergebruiken |
| Eén payment naar meerdere OpenItems | Ja, same Relation | Hergebruiken |
| Meerdere payments naar één OpenItem | Ja | Hergebruiken |
| Onverdeeld bedrag/overpayment/suspense | Nee; allocationtotal moet exact bankbedrag zijn | Deferred |
| Posting/Settlement/OpenItem-balance | Ja, atomisch en append-only | Hergebruiken |
| Full B3 reversal | Ja | Hergebruiken/uitbreiden voor imported origin |
| Duplicate posting | Unieke postinglink en locks | Hergebruiken plus source dedupe |
| Bankimport/reconciliation | Nee | Nieuw source/applicationmodel |
| Other bank transaction | Nee; BankTransaction vereist Payment/Relation | Gerichte V1-extensie |
| Bank account/date/reference/description | Ja | Uit immutable bronfact voorinvullen |

## Definitieve V1-scope

- **Formaat:** CAMT.053 is het enige verplichte V1-formaat. Parsercontracts zijn
  format-onafhankelijk zodat MT940 later als adapter kan volgen. CSV blijft deferred omdat
  bankprofielen, kolommen en betekenis niet gestandaardiseerd zijn.
- **Importmechanisme:** handmatige upload van volledige statementbestanden, parse-preview,
  expliciete bevestiging en daarna een reconciliationworklist. Losse transactie-import en
  automatische API/PSD2/bankkoppeling zijn deferred.
- **Banken/rekeningen:** multi-bank en meerdere AdministrationBankAccounts worden ondersteund;
  ieder statement hoort bij exact één active same-tenant rekening en de CAMT-accountidentiteit
  moet ermee overeenkomen.
- **Valuta:** EUR-only. Een niet-EUR statement of entry geeft een typed unsupported-currency
  outcome; FX en mixed-currency statements zijn deferred.
- **Financiële scope:** CustomerReceipt, SupplierPayment en Other. Een paymententry moet in
  V1 volledig over één of meer same-Relation OpenItems worden verdeeld. Partial betaling van
  een OpenItem is toegestaan, maar een onverdeeld bankbedrag niet. Other gebruikt één
  geautoriseerde tegenrekening voor het volledige bedrag.
- **Niet in V1:** overpayment/suspense, split over meerdere Relations, fees/netting binnen één
  CAMT-entry, bank-generated reversals/returns als automatische B3-reversal, FX, templates,
  auto-posting en bank-API.

## Immutable source model

Een import wordt nooit direct financiële waarheid. De originele file, batch, statement en
entries blijven Banking-owned immutable sourcefacts. Parse/import maakt geen JournalEntry,
BankTransaction, Payment, Settlement of Match.

`BankImportBatch` bevat minimaal AdministrationId, uploader, importedAt, format/parserversion,
private ArtifactId, file SHA-256, originele bestandsnaam/contenttype/size, parse status en
typed failure summary. `BankStatement` bevat AdministrationBankAccountId, CAMT group/message
identity, statement ID/sequence, creation timestamp, statement period en optionele typed
opening/closing balances. `BankStatementEntry` bevat een stabiele normalized identity,
statement sequence/ordinal, bank/account-servicer transaction references, booking date,
value date, signed Money/direction, currency, counterparty naam en IBAN/account, remittance,
end-to-end ID, mandate/creditor reference, bank transaction code en een immutable normalized
structured metadata snapshot. ImportedAt/ImportedBy horen bij batch; entry bewaart de batch-
en statementidentiteit.

Alles wat de bank heeft aangeleverd is immutable. Parserdiagnostiek mag worden aangevuld als
append-only history, niet door sourcefacts te herschrijven. Raw XML wordt niet als
Domain-frameworktype doorgegeven.

De bestaande Sales `DocumentArtifact` is capability-specifiek en mag niet vanuit Banking
worden hergebruikt. BIR introduceert daarom een Banking Application storage-port met private
infrastructure-opslag; een toekomstige generieke artifactcapability kan beide adapters delen
zonder Banking afhankelijk te maken van Sales.

## Duplicatepreventie en idempotency

Fuzzy matching is nooit duplicatepreventie. De harde sleutels zijn trapsgewijs en worden als
genormaliseerde `source_identity_kind` plus cryptografische `source_identity_hash` vastgelegd:

1. batch: unique `(administration_id, administration_bank_account_id, file_sha256)`;
2. statement: unique `(administration_id, administration_bank_account_id, source_format,
   statement_id)`; CAMT message ID/sequence wordt aanvullend bewaard en gecontroleerd;
3. entry: account-servicer-reference als eerste voorkeur, daarna een door de bank gegarandeerde
   entry/transaction ID; unique per AdministrationBankAccount en format/providernamespace;
4. alleen als CAMT geen stabiele ID levert: een versiegebonden canonical hash van statement-ID,
   entryordinal, signed amount/currency, booking/value date, bank transaction code,
   counterparty account en alle gestructureerde references/remittance.

EndToEndId of bedrag/datum/omschrijving alleen is niet uniek en mag uitsluitend evidence voor
matching zijn. Overlappende statements met dezelfde stabiele bankentry-ID leveren
`DuplicateEntry`, niet een tweede row of financiële waarheid. Exact dezelfde file/batch is
idempotent; een deels overlappende nieuwe batch importeert alleen werkelijk nieuwe entries en
rapporteert duplicates per entry. IDs veranderen niet bij retry.

## Imported source versus BankTransaction

Definitieve keuze: **B – imported source blijft apart en wordt pas bij expliciete acceptatie
aan een BankTransaction gekoppeld**.

Dit bewaart auditability, laat unresolved/ignored entries bestaan, ondersteunt hernieuwde
suggesties zonder financiële mutation en voorkomt dat parsefouten of duplicates Draft-
BankTransactions vervuilen. De promotion maakt een nieuwe BankTransaction met immutable
source-link; handmatige legacy BankTransactions houden die link nullable.

Een reconciliationdraft/suggestion is nog geen financiële waarheid. De finale actie
`Reconcile and post` lockt de entry en alle betrokken OpenItems en voert in één outer
transaction de gevalideerde promotion, Finalize, PostingEngine-posting, Settlements en unieke
entry→BankTransaction-reconciliationlink uit. Alleen een aanwezige succesvolle
BankTransactionPosting maakt de derived status `Reconciled`. Er kan dus geen UI-status
“gereconcilieerd” bestaan zonder financiële posting. Een typed failure rolt alles terug.

## Reconciliation lifecycle en audit

De minimaal duurzame lifecycle is:

- `Unresolved`: immutable entry geïmporteerd, nog geen finale disposition;
- `Ignored`: expliciete niet-financiële disposition met actor, timestamp en verplichte reason;
- `Reconciled`: afgeleid uit één actieve reconciliationlink naar een succesvol Posted
  BankTransaction;
- `Reversed`: afgeleid wanneer die BankTransaction via B3 is reversed; de source blijft
  gekoppeld en komt als correctie-required terug in de worklist.

`Suggested` is een readmodel/evidence-resultaat, geen duurzame status. `PartiallyReconciled`
wordt niet gebruikt: V1 post een entry volledig of niet. `Rejected` is voor een ongeldig
statement/batch importoutcome, niet voor een normale entryworkflow. Ignore kan via een
append-only restore-event terug naar Unresolved; history bepaalt de actuele disposition.

## Matchingstrategie

Deterministische candidates gebruiken exact gestructureerde invoice-/creditreferentie,
creditor reference, EndToEndId wanneer sourcegebonden, genormaliseerde tegenpartij-IBAN die
uniek bij één same-tenant Relation hoort, paymentreference en exacte open-itembedragen.
Een harde exacte match mag een suggestion met confidence 100 opleveren, maar V1 post ook die
niet stilzwijgend: een bevoegde gebruiker bevestigt altijd.

Heuristieken combineren relation/account, amount, remittancetekst, documentnummer en due-date-
nabijheid tot verklaarbare confidence en evidencecodes. Thresholds beïnvloeden alleen ranking
en voorselectie. Zwakke fuzzy tekst kan nooit muteren, een tenantgrens passeren of een Relation
autoritatief kiezen. De gebruiker kiest vervolgens CustomerReceipt of SupplierPayment,
Relation en bestaande PaymentAllocations. De server valideert alle actuele OpenItemfacts.

De bestaande allocationkern ondersteunt één entry naar meerdere OpenItems, meerdere entries
naar één OpenItem en partial settlement van een OpenItem. V1 vereist wel dat de som van alle
allocations exact het absolute entrybedrag is en dat ze dezelfde Relation/type/side/currency
hebben. PurchaseCredit OpenItemMatches blijven afzonderlijke documenttruth. Supplier/customer
netting, meerdere Relations, underpayment remainder en overpayment/suspense zijn deferred.

## Other bank transactions

V1 ondersteunt bankkosten, rente, belastingbetaling, interne overboeking en overige
kosten/opbrengsten als `OtherBankBookingIntent` binnen dezelfde BankTransaction aggregate-
boundary, wederzijds exclusief met Payment. De gebruiker kiest één active same-tenant
LedgerAccount als tegenrekening voor het volledige bedrag. Serverbeleid blokkeert de eigen
Bank-account, AR/AP-controlaccounts en andere niet-toegestane controlaccounts. Er is geen
vrije JournalEntry-editor en geen clientkeuze van Journal of Bank-LedgerAccount;
BankingPostingConfiguration blijft authoritative. Booking templates en automatische
classificatie zijn later.

De boeking is Debit Bank/Credit tegenrekening bij ontvangst en Debit tegenrekening/Credit Bank
bij uitgave. PostingEngine, AP guard en immutable JournalEntry blijven verplicht. B3 wordt
gericht uitgebreid zodat een Posted imported Other met nul Settlements volledig kan worden
gereversed door historische journalspiegeling; importsource en reconciliationhistory blijven
immutable.

## Datums, statementcontrol en completeness

CAMT booking date is de default Accounting PostingDate. Een bevoegde poster mag vóór posting
een expliciete override kiezen; de gekozen PostingDate wordt in de reconciliationaudit
vastgelegd. AP lock gebruikt uitsluitend deze PostingDate. ValueDate blijft immutable
bankinformatie en krijgt geen accounting- of fiscale locksemantiek.

Als CAMT opening/closing balances levert, valideert de parser per rekening/statement en
balance-type met Money:

`opening balance + signed booked movements = closing balance`.

Een mismatch levert `StatementBalanceMismatch`, blokkeert confirmation en maakt geen durable
entries of financiële facts. Het mislukte batchauditrecord en raw artifact mogen voor diagnose
blijven. Ontbrekende verplichte CAMT-identiteiten, valuta- of accountmismatch blokkeren eveneens.

Reconciliation completeness is een readmodel uit immutable entries plus dispositions en
postings: imported count/amount, reconciled/posted, unresolved, ignored en reversed/correction-
required. Niet alle entries hoeven direct geboekt te worden; een statement heet alleen
source-valid als format/account/balancecontrols groen zijn en alleen operationally complete
als geen unresolved of correction-required entries resteren. Financiële reporting blijft uit
Journal/OpenItem-truth komen.

## Correctie en reversal

Imported source wordt nooit verwijderd of gewijzigd. Een verkeerde Posted payment wordt via
B3 volledig gereversed; de reconciliationlink/history toont original en reversal en de entry
wordt correction-required. Daarna kan een nieuwe expliciete reconciliation naar een nieuwe
BankTransaction worden geappend, met uniqueness die exact één niet-reversed actuele linkage
toelaat. Wrong match wordt dus niet losgekoppeld of herschreven. Other-reclassification volgt
dezelfde full-reversal-plus-new-postingroute. Ignore/restore zijn append-only dispositions.
Een echte bank reversal/return is in V1 een nieuwe imported bankentry, niet automatisch een
B3-command. Duplicate-classificaties worden gecorrigeerd met auditmetadata, nooit door source
te wissen.

## Authorization en tenantisolatie

Hergebruik `BANKING.VIEW` voor statements/worklist/detail. Voeg drie onafhankelijke permissions
toe:

- `BANKING.IMPORT_UPLOAD`: upload, preview en bevestig source-import;
- `BANKING.RECONCILE`: suggestions beoordelen, allocation/classification voorbereiden,
  ignore/restore;
- `BANKING.IMPORT_POST`: finale reconcile-and-post actie.

`BANKING.PAYMENTS_REVERSE` blijft afzonderlijk; uploader/reconciler krijgt dat niet impliciet.
Canonical roles: `BANK_IMPORT_UPLOADER` = View+Upload, `BANK_RECONCILER` = View+Reconcile en
`BANK_IMPORT_POSTER` = View+Reconcile+ImportPost. Geen automatische production assignment.
Bij promotion gebruikt de server daarnaast dezelfde financiële invarianten als bestaande
paymentposting; permissions vervangen geen Domain/Application-validatie.

Iedere batch, statement, entry, disposition, suggestionread en reconciliationlink is
Administration-scoped. AdministrationBankAccount, OpenItem, Relation, BankTransaction,
Journal en actor-membership moeten dezelfde Administration bezitten. Alle persistence-FKs
zijn waar mogelijk composite same-tenant RESTRICT-FKs. Cross-tenant IBAN/reference-search
retourneert geen existence information. Locks en unique constraints bevatten tenant en
bankaccount waar relevant.

## Upload- en privacybeveiliging

- allowlisted FormRequest, CSRF en afzonderlijke POST-mutaties;
- configureerbare lage size-limit; streaming parser en bounded element/text counts;
- extension/MIME zijn hints, XML-content en CAMT namespace/schema worden werkelijk gevalideerd;
- external entities, DTD, network access en XInclude uit; geen permissieve entity expansion;
- encoding normaliseren/valideren en malformed/truncated XML typed weigeren;
- private non-public storage, opaque ArtifactId, authorization bij iedere read/download;
- SHA-256 over originele bytes vóór parsing;
- raw metadata nooit ongefilterd in logs; escaped/redacted Weboutput en beperkte toegang;
- retentionbeleid expliciet en wettelijke/operationele bewaartermijn configureerbaar;
- geen ZIP-upload in V1, dus geen zipbomb-oppervlak; malware scanning policy vóór retained
  attachment-download, met quarantine/failurestatus.

## Concurrency en atomiciteit

Database-uniques beslissen file-, statement- en entryduplicates; voorafgaande reads zijn alleen
UX. Import gebruikt insert-only semantics. Same-file/same-statement-races geven één winnaar en
typed `DuplicateBatch`/`DuplicateEntry` voor verliezers. Confirmation lockt batch en statements.

Reconcile-and-post lockt in vaste volgorde: entry, eventueel actuele reconciliationchain,
BankTransaction/source uniqueness, gesorteerde OpenItems en AP-period via bestaande grenzen.
Twee gebruikers op dezelfde entry leveren maximaal één Posted linkage; de verliezer krijgt
`EntryAlreadyReconciled`. OpenItemraces blijven onder bestaande balance locks vallen en geven
typed `AllocationExceedsAmount`/invalid-allocation. B3 reversal versus nieuwe reconciliation
serialiseert op BankTransaction, entrylink en OpenItems. Geen retry mag een tweede financial
truth creëren; retry is alleen toegestaan rond de volledige idempotente outer transaction.

## Logisch persistencemodel

Alle tabellen zijn additive, hebben UUID-PK, `administration_id`, timestamps en geen soft delete.

1. `bank_import_batches`: bankaccount, format/parserversion, private artifact identity,
   file SHA-256, file metadata, uploader/importedAt, parse/import status en failurecode.
   Unique tenant+bankaccount+hash; indexes op tenant/status/importedAt.
2. `bank_statements`: batch, bankaccount, source statement/message ID en sequence, period,
   typed opening/closing Money en validationstatus. Unique tenant+bankaccount+format+statementId;
   composite FKs naar batch/account.
3. `bank_statement_entries`: statement/batch/account, immutable source key kind/hash, ordinal,
   booking/value dates, signed amount/currency, counterparty/references/code en normalized JSON
   snapshot. Unique tenant+bankaccount+source key; indexes op tenant+state-read fields,
   bookingDate en reference hashes.
4. `bank_entry_reconciliations`: immutable accepted linkage from entry to BankTransaction plus
   selected PostingDate, actor/time and superseded/reversal lineage. Unique one current
   non-reversed linkage per entry, unique BankTransaction, composite tenant FKs.
5. `bank_entry_reconciliation_history`: append-only Ignore, Restore, Reconcile, ReverseObserved
   and correction audit with actor/time/reason and causal predecessor. Current disposition is
   derived with deterministic causal ordering.

Een aparte `bank_entry_suggestions`-tabel is niet nodig in V1. Suggestions worden
deterministisch als readmodel berekend; persistence zou stale confidence tot schijnwaarheid
maken. Als performance later caching vereist, is dat disposable/versioned projectiondata en
geen Domain-truth.

Handmatige BankTransactions blijven zonder importlink legacy-valid. Er is geen rewrite,
backfill of synthetic importhistory. Een nullable/import-additive linkage wordt uitsluitend
voor nieuwe promoted transactions gevuld.

## Typed outcomes

De Application/Webgrens vertaalt minimaal `UnsupportedFormat`, `UnsupportedCurrency`,
`MalformedFile`, `DuplicateBatch`, `DuplicateStatement`, `DuplicateEntry`,
`StatementBalanceMismatch`, `BankAccountNotFound`, `BankAccountMismatch`,
`EntryAlreadyReconciled`, `InvalidAllocation`, `AllocationExceedsAmount`, `OpenItemNotFound`,
`InvalidOtherAccount`, `PeriodClosed`, `NoAccountingPeriod`, `PeriodIntegrityFailure` en
`PostingFailure`. Parser-, storage-, SQL- en XML-exceptions worden intern gelogd met veilige
correlation ID en nooit rauw naar Web gelekt.

## Webflow

Onder Bank komen `Importeren`, `Afschriften` en `Te verwerken transacties`. De responsive flow:
upload → parse-preview → duplicate/integrityrapport → importbevestiging → worklist → verklaarbare
suggesties → manual allocation of Other-classificatie → expliciete reconcile-and-post →
audit/detail/reversal-link. GET is read-only. Iedere mutation is POST, CSRF-protected en typed.
Preview/import muteert geen financiële facts; alleen de finale postactie doet dat atomisch.

## Teststrategie

- Unit: CAMT fixtures/parser, hostile XML, normalization, source keys, balancecontrol,
  deterministic matching/evidence/confidence en Other-accountbeleid.
- Integration: private artifactadapter, immutable persistence, composite tenant-FKs, all-or-none
  rows, file/statement/entry idempotency, overlapstatements en statementbalansen.
- Application: preview/import, suggestions, ignore/restore, CustomerReceipt/SupplierPayment,
  multi-/partial allocations, Other, AP denials, atomische posting en B3 correction.
- Concurrency: same upload/statement, overlapping entry, double reconcile, reconcile versus B3,
  OpenItem settlement/match races en forced persistence rollback.
- Web/security: uploadlimits, XML attacks, preview, worklist, no GET mutation, UUID/CSRF,
  permission independence/revocation/inactive membership, XSS en cross-tenant probes.
- Regression: B2/B3, PurchaseCredit/OpenItem/Match, AP locks, PostingEngine, authorization en
  DEV-SAFE.

## Definitieve storysplit

### BIR-000 – Align Bank Import & Reconciliation Contracts

Doel: source identities, lifecycle, promotion/atomicity, Other intent, permissions en typed
outcomes als framework-onafhankelijke contracts vastleggen. Afhankelijk van gemergede B2/B3,
AP en DEV-SAFE. Acceptance: contracttests voor states, dedupe precedence, payable/receivable/
Other invariants en no-financial-write import. Non-scope: schema, parser en Web.

### BIR-001 – Add Immutable CAMT Source Model and Secure Parser

Doel: private file storage-port, bounded/XXE-safe CAMT.053 parser en immutable batch/statement/
entry persistence. Afhankelijk van BIR-000. Acceptance: valid fixtures, malformed/hostile XML,
EUR/account validation, tenant-FKs en raw/normalized snapshots. Non-scope: suggestions/posting.

### BIR-002 – Enforce Import Idempotency and Statement Integrity

Doel: batch/statement/entry unique keys, overlapping statement behavior, balancecontrol en
typed duplicate outcomes. Afhankelijk van BIR-001. Acceptance: retry-idempotency, concurrent
uploads, one-winner uniques, mismatch denial and rollback. Non-scope: reconciliation.

### BIR-003 – Build Matching and Reconciliation Worklist

Doel: deterministic/heuristic suggestion readmodel, explainable evidence, ignore/restore audit
en manual PaymentAllocation preparation. Afhankelijk van BIR-002. Acceptance: exact and fuzzy
ranking without mutation, partial/multiple allocations, tenant/security and stale-open-item
revalidation. Non-scope: financial posting and Other.

### BIR-004 – Integrate Atomic Posting, Other Transactions and Corrections

Doel: entry promotion, CustomerReceipt/SupplierPayment reuse, restricted Other intent,
reconcile-and-post transaction, AP guard and B3 correction linkage. Afhankelijk van BIR-003.
Acceptance: exact journals/settlements, Supplier/Customer flows, Other debit/credit, double-post
and OpenItem races, full rollback and no reconciled-without-post state. Non-scope: auto-posting,
overpayment/suspense and bank-generated automatic reversals.

### BIR-005 – Deliver Bank Import and Reconciliation Web Flow

Doel: upload/preview/confirm, statements, worklist, suggestions, manual allocation/
classification, post and audit UI. Afhankelijk van BIR-004. Acceptance: permission separation,
responsive flow, typed Dutch feedback, CSRF/UUID/XSS/no-GET-mutation and tenant isolation.
Non-scope: bank API, MT940/CSV and dashboards beyond operational completeness.

### BIR-006 – Review, Manual Acceptance and Regression

Doel: complete architecture/accounting/security/concurrency review and development readiness.
Afhankelijk van BIR-001–005. Acceptance: real CAMT fixture import/reimport, receipt/payment/Other,
partial/multiple settlement, AP denial, B3 correction, statement/completeness controls, full
suite and immutable development fingerprint. Non-scope: new capability.

## Roadmap

BIR-000 is de eerstvolgende story. Na BIR V1 blijven MT940, CSV-profielen, PSD2/API,
multi-currency/FX, overpayment/suspense en automatische classificatie afzonderlijke follow-ups.
Import/customs/Artikel 23 blijft bij Purchasing. VAT/ICP filing blijft geparkeerd. Breder
dashboard/reporting gebruikt later de BIR operational-completeness readmodels en dupliceert
geen Journal/OpenItem financial truth.
