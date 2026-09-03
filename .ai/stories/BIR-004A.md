# BIR-004A – Generalize BankTransaction Financial Intent and Reversal Source

## Aanleiding en grens

`BankTransaction` vereiste historisch altijd een `Payment`. Daardoor kon BIR-004 geen
legitieme Other-bankmutatie zonder OpenItem verwerken en veronderstelde ook de B3
reversal-readstack altijd PaymentAllocations en Settlements. BIR-004A generaliseert
uitsluitend deze financiële kern; importpromotie, reconciliationlinks en Web blijven
BIR-004-scope.

## Typed intent en persistence

Een BankTransaction heeft exact één intent: `Payment` of `Other`. Payment behoudt de
bestaande PaymentAllocation/Settlement-flow. Other bevat exact één contra-LedgerAccount
en positief EUR Money dat gelijk is aan het absolute BankTransaction-bedrag. Beide intents
tegelijk en geen van beide zijn ongeldig; callers hoeven geen nullable Payment te raden.

Migration `000064` voegt uitsluitend de tenant-scoped
`other_bank_transaction_intents`-childtable toe. Bestaande financiële facts worden niet
gewijzigd of gebackfilld. Een legacy BankTransaction zonder Other-row blijft Payment-backed
wanneer exact één geldige Payment bestaat. Omdat een cross-table XOR niet betrouwbaar als
databaseconstraint kan worden uitgedrukt, weigert repository/reconstitution fail-closed
zowel beide als geen intent; B3 vertaalt corruptie naar `FinancialStateInvalid`.

## Other posting en accountpolicy

Other incoming boekt via PostingEngine Bank debet en Contra credit; outgoing boekt Contra
debet en Bank credit. BankingPostingConfiguration en AccountingPeriodPostingGuard blijven
authoritative. BankTransaction, Other-child, JournalEntry, postinglinkage en Posted-audit
committen in één outer transaction. Other creëert geen Payment, Allocation, Settlement,
OpenItem of TaxPosting.

De server-owned policy vereist een actieve same-tenant LedgerAccount en weigert
bankcontrol, AR, AP, input-VAT, VAT-payable en geregistreerde OpenItem-controlaccounts.
Een interne overboeking wordt niet als Other gemodelleerd.

## B3 reversal, integriteit en concurrency

De source ondersteunt Payment-backed en Other-backed transactions. Payment behoudt
allocations, Settlements en OpenItem-heropening. Other spiegelt uitsluitend de immutable
originele JournalEntry met historische LedgerAccountIds en bedragen, zonder actuele
configuratieherinterpretatie of SettlementReversal. De originele transaction blijft
`Posted`.

Fault injection na de BankTransaction-row, na de Other-child en bij de journalgrens bewijst
volledige rollback. Echte MySQL-races leveren `Success` plus `AlreadyPosted` respectievelijk
`Success` plus `AlreadyReversed`. Alle reads en writes zijn Administration-scoped;
cross-tenant requests lekken geen bestaan.

## BIR-004 boundary

BIR-004 hoeft het intentmodel niet opnieuw te wijzigen. Het bezit nog uitsluitend imported
source → BankTransaction-promotie, prepared PaymentAllocation-persistence, actieve en
historische reconciliationlinks, atomische source/financial linkage, imported-source
reversalcoördinatie en re-reconciliation.
