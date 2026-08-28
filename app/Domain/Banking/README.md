# Banking Domain

Banking beheert handmatige EUR-bankbewegingen. `BankTransaction` is de Aggregate Root
en enige toekomstige postingbron. Zij bezit exact één `Payment`; Payment bezit meerdere
`PaymentAllocation` children en heeft geen zelfstandige lifecycle.

Een positief signed BankTransaction-bedrag is een CustomerReceipt, een negatief bedrag
een SupplierPayment. Payment en allocations zijn positieve EUR Money. Eén Payment hoort
bij één Relation en mag meerdere normale OpenItems van diezelfde Relation gedeeltelijk
alloceren. Finalize vereist minimaal één allocation en een som exact gelijk aan het
absolute bankbedrag; unallocated cash, overpayment, suspense en FX bestaan niet in V1.

De lifecycle is `Draft → Finalized → Posted` en `Draft → Cancelled`. B2-002 ondersteunt
create/update/finalize/cancel; B2-003 voert de enige atomische Posted-overgang uit.
Finalized bevriest bankrekening, TransactionDate, signed amount, reference, description,
Relation en allocations en bewaart actor/tijd uit de Application clock.

Finalize valideert readiness tegen actuele OpenItem-truth, maar reserveert geen saldo.
CustomerReceipt accepteert alleen Receivable/Debit, SupplierPayment alleen
Payable/Credit; alle targets zijn same-tenant, same-Relation, EUR, open en groot genoeg.
Iedere finalized allocation bewaart de immutable targetfacts type, side, Relation en
`controlLedgerAccountId`. Een later inactive control account herschrijft die historische
identity niet. Definitieve oversubscriptionbescherming gebeurt pas in B2-003 onder
deterministisch gesorteerde OpenItem locks.

`AdministrationBankAccount` is de operationele Administration-owned rekening en staat
los van RelationBankAccount en document-IBAN. Draft/Finalize vereist een actieve
same-tenant EUR-rekening, maar geen BankingPostingConfiguration; die configuratie wordt
pas bij Post gelezen.

B2-003 lockt de transaction en de unieke OpenItems in deterministische identityvolgorde,
leest actuele settlement/match-feiten opnieuw en gebruikt de actuele bankconfiguratie.
CustomerReceipt is Bank-debet/control-credit; SupplierPayment is control-debet/Bank-
credit. Ieder allocation gebruikt exact het historische `OpenItem.controlLedgerAccountId`.
Eén outer transaction bewaart één PostingEngine-JournalEntry, één Settlement per
allocation, één postinglinkage en PostedBy/PostedAt. Finalize reserveert geen saldo;
echte MySQL-locking voorkomt oversettlement en ondersteunt partial, multi-OpenItem en
meerdere betalingen over tijd. OpenItemMatch blijft onaangepast.

Bankimport, reconciliation, PSD2 en FX blijven buiten scope.

B3 definieert een Bank Payment Reversal als één atomische, volledige correctie van een
`Posted` handmatige BankTransaction. De original, haar status `Posted`, Payment,
allocations, posting, JournalEntry/lines en Applied Settlements blijven immutable;
`Teruggedraaid` is een readmodelafleiding uit een unieke tenant-scoped
BankTransactionReversal-linkage. De command vereist een expliciete ReversalPostingDate,
verplichte begrensde reason en bewaart ReversedBy/ReversedAt uit de Application clock.

De contra-JournalEntry spiegelt iedere concrete originele line op exact dezelfde
historische Journal en LedgerAccountIds, ook indien later inactive of renamed. Actuele
BankingPostingConfiguration wordt niet geherinterpreteerd en er ontstaat geen
TaxPosting. Iedere allocation-settlement krijgt exact één full-amount append-only
Reversal plus Banking-owned batchtrace. Andere settlements en OpenItemMatches blijven
staan; openAmount wordt uit de complete historie afgeleid. Een aparte
`BANKING.PAYMENTS_REVERSE`/`BANKING_REVERSAL_OPERATOR`-grens voorkomt dat Manage of Post
impliciet correctierecht geeft. Partial reversal, importreversal en period locks blijven
deferred.

B3-001 realiseert deze foundation met stable typed authorization, zonder automatische
membershipassignment, plus immutable `BankTransactionReversal`- en
`BankTransactionSettlementReversalLink`-facts. Additieve composite same-tenant FKs en
uniques borgen exact één transactionreversal en één linkage per allocation/original/
reversal settlement. De framework-onafhankelijke source-reader volgt uitsluitend
BankTransaction → Payment/allocations → posting → historical JournalEntry/lines en de
unieke allocation-settlementtrace. Eligibility is typed; andere settlements, matches,
current open balance en inactive historical masterdata blokkeren niet. B3-001 zelf
maakt geen financiële facts; de atomische command blijft exclusief B3-002.

B3-002 levert `ReverseBankTransaction` als één outer transaction. De command spiegelt
iedere original JournalEntryLine via PostingEngine op exact de historical Journal en
LedgerAccountIds, maakt daarna per allocation een OpenItem-owned full-amount settlement-
reversal, persisteert het immutable reversalrecord en sluit af met alle Banking-links.
Originals en status `Posted` blijven onaangeraakt; TaxPostings, nieuwe OpenItems en
OpenItemMatch-mutaties ontstaan niet. Sorted OpenItemlocks, fresh history en begrensde
MySQL-deadlockretry maken double reversal, nieuwe payments en creditmatching
serialiseerbaar. Het Success-readmodel maakt B3-003 zonder Weblogica in Application
mogelijk.

B2-004 maakt de bestaande contracten productmatig beschikbaar onder de permission-
scoped sectie Bank → Betalingen. View, Manage en Post blijven onafhankelijk. De Weblaag
levert alleen gevalideerde input en toont Application-readmodels; lifecycle, eligibility,
exacte allocation-som, locks, actuele open saldi, historical controlaccounts,
JournalEntry en Settlements blijven buiten Presentation. Posted detail toont de ene
postinglinkage en settlement/remaining amount per allocation. Bankimport, reconciliation,
overpayment, suspense, FX en reversal blijven bewust vervolgscope.
