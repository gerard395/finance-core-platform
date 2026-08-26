# PROJECT-GAP-004 – Post-B2 Product Direction Review

Reviewdatum: 26 augustus 2026. Type: design/gap review; geen productiecode,
migration, database- of permissionwijziging.

## 1. Post-B2 baseline

B2 sluit de handmatige cashcyclus. Sales ondersteunt Quotations, Orders,
SalesInvoices en full-source SalesCreditInvoices, transactionele posting naar
Receivables, customer receipts, partial/multi-OpenItem settlement en PDF/e-maildelivery.
Purchasing ondersteunt Suppliers, domestic EUR PurchaseInvoices, volledig aftrekbare
Input VAT, transactionele posting naar Payables, supplier payments en settlement.
Accounting bewaart Journals, LedgerAccounts, immutable JournalEntries/TaxPostings,
OpenItems, append-only cash Settlements en opposite-side document Matches. Banking
bewaart handmatige CustomerReceipts en SupplierPayments als BankTransactions met één
Payment en meerdere allocations.

| As | Beschikbaar | Belangrijkste resterende gaps |
| --- | --- | --- |
| Sales | Documentketen, credits, posting, delivery, receivables en cash settlement | Partial credits, refund/payment reversal, international Sales-breedte |
| Purchasing | Domestic invoice → Payable → supplier payment | Purchase Credits, international/reverse-charge/import/foreign VAT, attachments/OCR |
| Accounting | Dubbel boekhouden, open-item matching/settlement en reportingreads | Accounting periods/locks, generieke audit, explicit financial reversals |
| Banking | Handmatige EUR-bankmutaties, partial en multi-item settlement | Statementimport, provenance, reconciliation, unallocated cash/overpayment, FX, reversal |
| Fiscal | Domestic Sales/Purchase originals en Sales-credit reversals | Purchase-credit reversals, multi-leg purchase tax, deductibility, import, FX en periods |

De drie kandidaten lossen verschillende soorten completeness op. Purchase Credits
sluiten de normale documentcorrectieketen. Bankimport automatiseert een cashflow die
functioneel al handmatig bestaat. International Purchase VAT opent nieuwe wettelijke
bronstromen, maar vereist eerst een multi-leg fiscal redesign.

## 2. Purchase Credits

### Readiness en bronwaarheid

`PurchaseCreditInvoice`, `PurchaseCreditInvoiceLine` en
`PostPurchaseCreditInvoiceWithTax` bestaan als prototypes. Het aggregate kent identity,
Administration, Supplier, EUR, creditdatum, optionele source PurchaseInvoice, positieve
regels en Draft → Finalized → Posted/Cancelled. Het postingprototype maakt een
gebalanceerde tegenboeking en volledige Input-`TaxPosting`-reversals. Er is nog geen
productwaardige persistence, reconstitution, source reader, numbering/external
supplier-creditidentity, immutable supplier/address/fiscalsnapshot, actor/tijd,
postinglinkage, permission of Webflow.

De productflow moet uitsluitend persisted source truth gebruiken:

- source PurchaseInvoice en source PurchaseInvoiceLine;
- immutable Supplier/Relation-, adres- en documentcontext;
- de oorspronkelijke accountallocatie en werkelijke AP-controlaccountidentity;
- TaxCodeId, TaxRate, TaxTreatment, VatReturnClassification, taxable base, tax amount,
  fiscal reporting date en concrete Original Input TaxPosting;
- source JournalEntry/OpenItem/linkage en Currency.

Actuele Supplier-, TaxCode-, account- of PurchasePostingConfiguration-state mag deze
historie niet herinterpreteren. De actuele configuration mag alleen het actieve
Purchase Journal leveren; AP en expense/Input-VAT reversalaccounts komen uit de
werkelijk geposte source JournalEntry/TaxPostings, overeenkomstig historische truth.

### OpenItem en betalinginteractie

Het huidige Accounting-model is direct geschikt:

- PurchaseInvoice maakt een positieve `Payable/Credit`;
- PurchaseCredit maakt een positieve `Payable/Debit`;
- `OpenItemMatchingPolicy` vereist hetzelfde type, Administration, Relation en Currency,
  tegengestelde side en een positief bedrag binnen beide actuele open amounts;
- `MatchOpenItems::executeAvailable()` lockt het pair en matcht het minimum van beide
  actuele bedragen. Er is geen negatieve-amountworkaround nodig.

Gedrag na creditposting:

| Source-state | Match | Resterende waarheid |
| --- | --- | --- |
| Volledig open invoice | Match tot minimum; bij full credit sluiten beide | Geen cashmutatie |
| Gedeeltelijk betaald | Match alleen het resterende Payable/Credit | Overschot credit blijft open Payable/Debit supplier balance |
| Volledig betaald | Geen zero-match | Volledige credit blijft open Payable/Debit supplier balance |

Een reeds geposte betaling blijft immutable historical cashtruth. Creditposting draait
geen BankTransaction, JournalEntry of Settlement terug en maakt geen automatische
refund. Een latere expliciete supplier refund/credit-applicationflow kan het open
Payable/Debit afwikkelen.

### Full versus partial

De eerste batch ondersteunt **uitsluitend volledige source-line reversals**. Dat is
praktisch bruikbaar: een credit mag één of meer volledige oorspronkelijke regels
selecteren, maar iedere gekozen fiscale Original wordt exact eenmaal volledig
gereversed. Dit is kleiner dan uitsluitend een full-documentcredit en sluit aan op het
bestaande `TaxPostingReversalPolicy`-contract.

Partial source-line reversal blijft deferred. Zij vereist een nieuwe cumulative
remaining-creditable truth per source line, quantity/base/tax-proportionaliteit,
afrondingsbeleid, meerdere reversals per Original en concurrency-safe somguards. Het
huidige unieke `reversed_tax_posting_id`-contract modelleert bewust maximaal één full
reversal en kan partial credits niet dragen zonder expliciet redesign.

### Concurrency, audit en blockers

Verplichte implementatietests zijn sequential/concurrent duplicate supplier-credit,
double post, twee credits op dezelfde source line, credit groter dan remaining
reversible truth, credit versus payment/match en rollback op JournalEntry, TaxPosting,
OpenItem, Match, linkage en status. Locks volgen credit, source invoice, geselecteerde
source facts en daarna OpenItems in deterministische identityvolgorde. FinalizeBy/At en
PostBy/At zijn durable auditfacts.

Belangrijkste werk: de prototypes moeten op P3-niveau worden gebracht; dit is evolutie,
geen architectuurblokkade. Productwaarde: **critical next**. Zonder leverancierscredits
kan een normale documentcorrectie en Input-VAT-reversal niet productmatig worden
vastgelegd, terwijl de betalingcyclus na B2 al functioneel compleet is.

## 3. Bank Import & Reconciliation

### Feitelijke readiness en modelgrens

Er bestaat geen parser, importmodel, BankStatement-/StatementLine-code, importtabel,
fileprovenance, external transaction identity of reconciliation use case. `BankStatement`
staat alleen als begrip in het Domain Model. B2 `BankTransaction` is volledig manual:
het aggregate vereist bij constructie exact één Payment met een Relation en signed EUR
Money. Een ongereconcilieerde statementregel past daardoor niet eerlijk direct in dat
aggregate.

De veilige toekomstige grens is:

```text
BankStatement (factual import batch)
  → StatementLine (immutable bank-provenance)
  → user-confirmed promotion/reconciliation
  → BankTransaction + Payment + allocations
  → bestaande Finalize/Post/Settlement-flow
```

Statement houdt AdministrationBankAccount, format/provider, statement identity,
period, opening/closing context indien geleverd, import actor/tijd en file hash.
StatementLine houdt provider line identity, booking/value date, signed amount/currency,
counterparty, IBAN en structured/unstructured remittance. BankTransaction krijgt later
een immutable source type/origin-link; imported facts worden niet als manual vermomd.

### Idempotency en formatkeuze

Harde uniqueness moet rusten op bankgeleverde identity, bijvoorbeeld
`AdministrationBankAccount + provider/format + statementId + entryReference`, met een
format-specifieke fallback uitsluitend wanneer de standaard zelf een duurzame sequence
voorschrijft. Een file hash detecteert exact dezelfde upload, maar vervangt geen
line-identity wanneer dezelfde statementinhoud anders verpakt wordt. Datum + bedrag +
omschrijving is alleen een duplicate warning en nooit hard financial identity.

| Format | Nederlandse waarde | Structuur/remittance | Identity | Complexiteit | Prioriteit |
| --- | --- | --- | --- | --- | --- |
| CAMT.053 | Moderne ISO 20022-bankstandaard | Rijk en typed | Sterkste statement/entry references | Hoog maar testbaar met schemafixtures | 1 |
| MT940 | Brede legacy-bankondersteuning | Minder uniform, bankspecifieke varianten | Redelijk, variantafhankelijk | Middel/hoog | 2 |
| CSV | Handige escape hatch | Bankspecifiek en arm | Vaak zwak | Laag parsertechnisch, hoog configuratierisico | 3 |

PSD2/API volgt later; het verandert transport/credentials maar niet de importidentity-
en provenancecontracten.

### Reconciliation en auto-matchgrens

Import, reconciliation en settlement zijn afzonderlijk. Import bewaart bankfeiten.
Reconciliation suggereert Relation, PaymentType en allocations. Settlement ontstaat pas
na user-confirmed BankTransaction Finalize/Post via B2. Exact invoice/reference,
structured payment reference, exact amount en een same-tenant RelationBankAccount-IBAN
zijn verklaarbare signalen. Geen fuzzy score wordt financial truth zonder bevestiging.

Belangrijkste blockers zijn nieuw statement/provenancemodel, formatparsers, harde
idempotency, unallocated imported lines, permissions, operational audit en een Web
confirmationqueue. Productwaarde: **high-value automation**. Zonder import blijft veel
dagelijkse invoer, maar er bestaat sinds B2 een correcte handmatige route.

## 4. International Purchase VAT

### Actuele modelgap

De huidige `PurchaseTaxSnapshot` en `TaxClassification` laten voor Input uitsluitend
domestic standard/reduced/zero/exempt/outside-scope toe. Iedere PurchaseInvoiceLine
heeft één taxsnapshot en één tax calculation. `TaxPosting` heeft één direction en één
nullable tax JournalEntryLine. `PostPurchaseInvoice` maakt per source line één baseleg
en hoogstens één Input-VAT-leg. Eén reverse-charge bronregel met gelijktijdige Output-
liability en (mogelijk gedeeltelijke) Input-deduction is dus niet representabel.

Een fiscal predecessor moet minstens ontwerpen:

- regime/treatment voor intra-EU goods, EU/non-EU B2B services, domestic reverse charge,
  import VAT/artikel 23 en foreign VAT;
- één taxable source base met meerdere typed fiscal legs;
- afzonderlijke Output- en Input-TaxPostings plus gekoppelde trace;
- configured Output-VAT liability en Input-VAT asset accounts;
- deductibility als onderbouwd percentage/allocationmodel, niet een boolean;
- regime-specifieke fiscal date en EUR-conversion policy;
- volledige reversal van ieder gerealiseerd fiscal leg door latere credits.

### Deductibility, datum en FX

Volledige, gedeeltelijke en niet-aftrekbare VAT moeten afzonderlijk worden gemodelleerd.
De verschuldigde reverse-charge Output VAT blijft volledig rapporteerbaar; alleen de
Input-leg wordt beperkt. Het verschil beïnvloedt kosten/asset en mag niet verdwijnen in
een taxboolean.

PurchaseInvoice bewaart SupplierInvoiceDate, ReceivedDate, nullable SupplyDate,
FiscalReportingDate en later PostingDate, maar de huidige domestic policy
`max(invoiceDate, receivedDate)` is niet universeel. EU-goederen en diensten kennen
verschillende tax points en uiterste factuurmomenten; import steunt op douane-/invoerfacten.
Er zijn aanvullende typed regime-events en evidence nodig.

Een EUR-only predecessor kan treatment, multi-leg calculation, deductibility en date
policy veilig ontwerpen en non-EUR bij de boundary weigeren. Productwaardige
internationale invoer in vreemde valuta vereist echter een immutable koersbron,
koersdatum en EUR fiscal amounts naast transaction accounting. Volledige FX accounting
hoeft niet eerst af te zijn, maar een expliciete fiscal conversion capability is een
harde dependency voor non-EUR.

### Actuele autoritatieve broncontrole

Gecontroleerd op **26 augustus 2026**:

- [Belastingdienst – goederen en diensten afnemen uit andere EU-landen](https://www.belastingdienst.nl/wps/wcm/connect/bldcontentnl/belastingdienst/zakelijk/btw/zakendoen_met_het_buitenland/goederen_en_diensten_afnemen_uit_andere_eu_landen/): EU-goederen zijn doorgaans een Nederlandse intracommunautaire verwerving; EU-diensten zijn vaak naar de afnemer verlegd. Ontwerpconclusie: beide vereisen self-assessed Output VAT en alleen voor het aftrekbare deel een Input-leg.
- [Belastingdienst – aangifte EU-afnamen](https://www.belastingdienst.nl/wps/wcm/connect/bldcontentnl/belastingdienst/zakelijk/btw/zakendoen_met_het_buitenland/goederen_en_diensten_afnemen_uit_andere_eu_landen/aangifte_doen/): EU-afnamen worden in rubriek 4b aangegeven en aftrekbare voorbelasting in 5b; goederen en diensten hebben verschillende tijdstip- en valutaregels. Ontwerpconclusie: returnclassification, fiscal date en EUR conversion moeten regime-owned snapshots zijn.
- [Belastingdienst – verlegde btw aftrekken](https://www.belastingdienst.nl/wps/wcm/connect/bldcontentnl/belastingdienst/zakelijk/btw/btw_aftrekken/welke_btw_is_aftrekbaar/verlegde_btw_aftrekken): verlegde btw moet worden aangegeven, maar is alleen aftrekbaar voor zover aan de aftrekvoorwaarden wordt voldaan. Ontwerpconclusie: Output liability en Input deduction zijn afzonderlijke legs.
- [Belastingdienst – hoe werkt btw verleggen?](https://www.belastingdienst.nl/wps/wcm/connect/bldcontentnl/belastingdienst/zakelijk/btw/btw_berekenen_aan_uw_klanten/waarover_btw_berekenen/verleggingsregeling/hoe_werkt_btw_verleggen): ten onrechte gefactureerde VAT bij verplichte verlegging is niet als normale voorbelasting aftrekbaar. Ontwerpconclusie: een expliciete treatment/evidence-keuze is nodig; land of taxrate alleen volstaat niet.
- [Belastingdienst – invoer uit niet-EU-landen aangeven](https://www.belastingdienst.nl/wps/wcm/connect/bldcontentnl/belastingdienst/zakelijk/btw/zakendoen_met_het_buitenland/zakendoen_buiten_de_eu/aangifte_doen_als_u_zakendoet_buiten_de_eu/aangifte_doen_als_u_goederen_importeert_uit_niet_eu_landen/): invoer-btw wordt normaal bij Douane betaald en is slechts naar rato aftrekbaar; met artikel 23 loopt verschuldigdheid via de VAT return. Ontwerpconclusie: importdocument/evidence en artikel-23-status zijn andere source facts dan een supplier invoice.
- [Belastingdienst – welke btw mag u aftrekken?](https://www.belastingdienst.nl/wps/wcm/connect/bldcontentnl/belastingdienst/zakelijk/btw/btw_aftrekken/welke_btw_is_aftrekbaar/welke_btw_mag_u_aftrekken): gemengd belaste/vrijgestelde inzet beperkt aftrek. Ontwerpconclusie: deductibility vereist een auditable ratio/allocationbasis.

Productwaarde: **important fiscal predecessor**. Zij is noodzakelijk voor internationale
Purchase en latere VAT-return completeness, maar heeft de hoogste dependency- en
architectuurrisico's van de drie kandidaten.

## 5. Dependency matrix

| Criterium | A Purchase Credits | B Bank Import & Reconciliation | C International Purchase VAT predecessor |
| --- | --- | --- | --- |
| Productwaarde | Hoge normale correctiecompleteness | Hoge dagelijkse efficiency | Hoog voor internationale gebruikers |
| Accounting necessity | Tegenboeking + Payable/Debit | Bestaande cashposting hergebruiken | Multi-leg VAT posting redesign |
| Fiscal necessity | Reversal bestaande Input truth | Geen nieuwe fiscal truth | Hoog en wettelijk regimespecifiek |
| Architectuurreadiness | Hoog: prototypes + P3 + Sales-creditpattern | Middel: B2 basis, geen importmodel | Laag/middel: huidige one-leg model blokkeert |
| Dependencies | P3, TaxPosting reversal, Match | Statement/provenance/parser + B2 promotion | Fiscal legs, deductibility, dates, config, conversion |
| Migrationcomplexiteit | Middel | Hoog | Hoog |
| Concurrencycomplexiteit | Hoog maar bekend | Hoog: file/line idempotency + confirmation | Hoog: multi-leg identity/reversal |
| Webcomplexiteit | Middel | Hoog | Middel/hoog settings + invoice UX |
| Risico | Middel | Middel/hoog | Hoog |
| Geschatte batchomvang | 5 stories | 6 stories | Minstens 5 predecessorstories |
| Unlocks | Purchase corrections, Input reversal, credit balances | Snellere dagelijkse bankverwerking | International Purchase en fiscale completeness |
| Blocks later | Volledige purchase-document/VAT-correctionketen | Geen correctnessflow; wel automationroadmap | VAT/ICP reporting en international purchase |

## 6. Product/accounting completeness

| Completeness | A | B | C |
| --- | --- | --- | --- |
| Document | **Sluit normale leverancierscorrecties** | Geen | Nieuwe international treatments, geen creditcorrectie |
| Cash | Geen nieuwe cashtruth; gebruikt B2/matching | Automatiseert bestaande cashflow | Geen |
| Automation | Beperkt | **Grootste winst** | Beperkt |
| Tax | Reversal van domestic Input originals | Geen | **Nieuwe materiële VAT-bronstromen** |

Afhankelijkheden zijn niet cyclisch. Purchase Credits en Bank Import zijn wederzijds
onafhankelijk. International VAT is niet nodig voor domestic full-line credits; credits
moeten later wel multi-leg originals volledig kunnen reversen. International VAT kan
technisch zonder Purchase Credits starten, maar zonder credits blijft de nieuwe fiscale
truth niet corrigeerbaar. Daarom is Purchase Credits de betere predecessor.

Inkomende documentattachments zijn voor geen kandidaat een blocker. Purchase Credits
kunnen source PurchaseInvoice-truth gebruiken; bankimport gebruikt het bankbestand als
eigen provenance; international VAT kan handmatig evidence registreren. OCR blijft
apart.

Payment reversal is belangrijke correctnessdebt: een foutief geposte BankTransaction
kan nog niet via een expliciete tegenboeking/Reversal-settlement worden gecorrigeerd.
Posted facts blijven wel immutable en nieuwe flows hoeven bestaande cash niet te
muteren. Het is daarom geen blocker voor Purchase Credits, importdesign of fiscal
predecessor, maar moet vóór brede Banking-automation of production-readiness als aparte
correctiebatch worden gepland.

`FiscalYear` en `AccountingPeriod` bestaan alleen conceptueel; er is geen duurzame
periode-/lockfoundation. Dit blokkeert geen append-only Purchase Credit of importfact,
maar alle drie moeten expliciete dates bewaren. Voor VAT-returnfinalisatie en
production-grade postingcontrols is period locking wel een harde latere predecessor.

Audit: Purchase Credit vereist FinalizedBy/At en PostedBy/At; import vereist importer,
source/format, file hash en importtijd; reconciliation vereist suggester/confirmactor en
confirmation time; international VAT vereist gekozen regime, evidence, deductibility-
basis en actor/tijd. Generic field-level audit blijft afzonderlijke debt.

## 7. Recommended next batch

**Exact gekozen volgende implementatiebatch: PC – Purchase Credits.**

Na B2 is cash functioneel compleet via een veilige handmatige flow. Het eerstvolgende
correctnessgat is nu de ontbrekende leverancierscreditnota: een normale zakelijke
correctie, fiscale Input-reversal en Payable/Debit supplier balance zijn niet duurzaam
productmatig beschikbaar. PC heeft de hoogste readiness door bestaande prototypes,
P3-snapshots/posting, Sales-creditpatterns en het bewezen OpenItemMatch-model. De batch
blijft beheersbaar door alleen volledige source-line reversals toe te staan.

## 8. Definitive story split

1. **PC-000 – Align Purchase Credit Contracts**
   Definieer source eligibility, external supplier-creditidentity, full source-line
   selection, immutable snapshots, open/partial/paid gedrag, audit en concurrency.
2. **PC-001 – Purchase Credit Authorization, Persistence & Application**
   Typed onafhankelijke permissions/rollen zonder assignments; tenant-safe aggregate,
   regels, source-line uniqueness, lifecycle, readers en echte duplicate concurrency.
3. **PC-002 – Transactional Purchase Credit Posting & Tax Reversal**
   Eén outer transaction voor historical-account tegenboeking, volledige Input-
   TaxPosting reversals, Payable/Debit, postinglinkage, status, rollback en at-most-once.
4. **PC-003 – Purchase Credit Matching & Web Flow**
   Permission-aware source selector/create/detail/finalize/post; match remaining
   Payable/Credit, toon supplier credit balance en voer geen cash/refundactie uit.
5. **PC-004 – Purchase Credit Review & Regression**
   Full/partial/paid sourcecases, credit/payment/matchraces, tenant/auth/security,
   migration review en volledige batchvalidatie.

## 9. Follow-up order

2. **International Purchase VAT predecessor** – wettelijke en reporting-relevante
   functionele uitbreiding; start na PC zodat toekomstige multi-leg originals ook een
   bewezen correctiepad kunnen krijgen.
3. **Bank Import & Reconciliation** – zeer waardevolle dagelijkse automation, maar B2
   biedt al een correcte handmatige fallback. Start met CAMT.053 en een afzonderlijk
   Statement/Line-provenancemodel; MT940 en CSV volgen daarna.

Geen parallelle uitvoering.

## 10. VAT/ICP reconsideration condition

VAT/ICP reporting blijft geparkeerd. Herbeoordeling volgt pas wanneer domestic en
international Sales/Purchase invoices én credits duurzame Original/Reversal-
TaxPostings leveren, reverse-charge/import/deductibility en EUR-conversion zijn
gemodelleerd, correcties volledig traceerbaar zijn en fiscale perioden/locks plus
afrondingsbeleid bestaan. Bankimport is geen fiscale predecessor.

## 11. Deferred risks/debt

- BankTransaction payment reversal en Reversal-settlementflow;
- partial Purchase/Sales credits en cumulative rounding;
- BankStatement import, CAMT.053/MT940/CSV, reconciliation en unallocated cash;
- international/reverse-charge/import/foreign VAT, deductibility en FX;
- Accounting/Fiscal periods en locks;
- generic mutation audit;
- incoming attachments/OCR;
- VAT/ICP return generation/finalization.
