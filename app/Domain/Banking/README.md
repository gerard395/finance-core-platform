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

Bankimport, reconciliation, PSD2, FX, reversal en Banking Web blijven buiten scope.
