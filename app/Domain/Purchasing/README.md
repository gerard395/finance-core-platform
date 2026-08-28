# Purchasing Domain

## Doel

Purchasing beheert ontvangen inkoopdocumenten als zelfstandige,
frameworkonafhankelijke aggregates. `PurchaseInvoice` bezit zijn eigen regels en heeft
geen runtime-afhankelijkheid op Sales, Accounting of Fiscal persistence.

## PurchaseInvoice

De duurzame PurchaseInvoice legt de externe leveranciersfactuur als factual truth vast:
Administration, Supplier/Relation-snapshot, case-sensitive extern factuurnummer, vijf
document-/fiscale datums, EUR, expliciet overgenomen documentadres, regels en totalen.

```text
Draft → Finalized → Posted
Draft → Cancelled
Finalized → Cancelled
```

P3-002 biedt Create, coherente Draft-replacement, Finalize en pre-post Cancel.
`Posted` is reconstitueerbaar voor P3-003, maar er bestaat in P3-002 geen Application-
transition naar Posted. Paymentstatus is later uitsluitend afgeleid van Payable en is
geen PurchaseInvoice-status.

## Invarianten

- `SupplierInvoiceNumber` trimt alleen Unicode-boundary whitespace, bewaart case,
  punctuation en interne whitespace en is per Administration + Supplier database-uniek.
- Supplier is bij Create active en same-tenant. Naam, Supplier/Relation-identiteit,
  nummer, VAT ID en jurisdictie worden gesnapshot; latere masterdatawijziging vernieuwt
  een bestaand document nooit stilzwijgend.
- Het adres is expliciete received-document-input (`line1`, nullable `line2`, postcode,
  plaats, land); er is geen first-/purpose-/countryfallback.
- SupplierInvoiceDate en ReceivedDate zijn verplicht. SupplyDate is nullable,
  FiscalReportingDate is exact de latere van InvoiceDate/ReceivedDate en DueDate ligt
  niet vóór InvoiceDate. PostingDate is pas expliciete P3-003-use-case-input.
- P3 gebruikt uitsluitend EUR, positieve Quantity en net-exclusive Money zonder floats.
- Iedere regel verwijst bij Draft-mutatie naar een active same-tenant Expense/Asset-
  rekening en een active Input TaxCode. Accountidentiteit/type/code/naam en volledige
  taxcode/rate/treatment/VAT/ICP-snapshot worden vastgelegd.
- Alleen domestic standard/reduced volledig aftrekbare positieve Input VAT en expliciete
  zero/exempt/outside-scope zero VAT zijn ondersteund. Output, international,
  reverse-charge en positieve partial/non-deductible VAT worden geweigerd.
- Net, tax en gross worden exact uit de regels opgeteld. Finalize vereist minstens één
  regel en bewaart de Domain UserId en applicatie-clock timestamp éénmaal.
- Finalized, Posted en Cancelled zijn inhoudelijk immutable. Cancelled documenten
  blijven duurzaam en geven de duplicate identity niet vrij.

## Persistence en grenzen

Application repository- en readcontracten zijn tenant-scoped; Infrastructure bewaart
header en regels atomair met same-tenant foreign keys en RESTRICT delete-policy. List en
detail lezen historische snapshots. Selectors lezen uitsluitend actuele actieve
Suppliers, Expense/Asset-rekeningen en ondersteunde Input TaxCodes.

Create en Finalize maken geen JournalEntry, TaxPosting, OpenItem of postinglinkage en
vereisen geen PurchasePostingConfiguration. P3-003 `PostPurchaseInvoice` leest de
volledige Finalized snapshot plus actuele configuration en levert via PostingEngine in
één transaction de geposte Purchase JournalEntry, immutable Input TaxPostings, één
Payable/Credit OpenItem, één duurzame linkage en de status Posted. Fouten rollen alle
facts terug; headerlocking en linkage-uniciteit maken double post idempotent.

P3-004 ontsluit deze contracten in Web zonder financiële Presentation-logica. De
request-scoped navigatie en routes scheiden View, Draft Manage, Finalize en Post exact.
Draft-formulieren gebruiken uitsluitend same-tenant actieve Supplier-, Expense/Asset- en
ondersteunde Input-TaxCode-selectors; detail blijft historische snapshots tonen. Post
vereist een expliciete PostingDate en presenteert daarna linkage en het Payable/Credit
OpenItem. Er bestaat geen GET-mutatie, automatische finalize/post of paymentactie.

## PurchaseCreditInvoice contract (PC-000)

Een PurchaseCreditInvoice is een ontvangen leverancierscreditnota tegen exact één
same-tenant, same-supplier Posted PurchaseInvoice. PC V1 selecteert één of meer volledige
source PurchaseInvoiceLines; iedere source line is maximaal eenmaal creditable en wordt
zonder actuele Supplier-, TaxCode-, account- of configurationsinterpretatie exact
gereversed. Partial quantity/amount/tax en source-less of cross-invoice credits zijn
uitgesteld.

De credit heeft een eigen extern, case-sensitive suppliercreditnummer met afzonderlijke
namespace en unieke Administration + Supplier + nummer-identity. Zij gebruikt de
historische supplier/address/line/fiscal snapshots en de werkelijk geboekte source-
accounts. De lifecycle is Draft → Finalized → Posted of pre-post Cancelled, met actor-
en clockaudit bij Finalize/Post.

Post maakt via Accounting/Fiscal een gebalanceerde historical-account reversal,
Input/Reversal TaxPostings en een positieve Payable/Debit. Die wordt automatisch tot
het actuele source-openbedrag via OpenItemMatch tegen de Payable/Credit gematcht. Een
overschot blijft open supplier credit balance; bestaande settlements/cash worden nooit
teruggedraaid. De volledige contracten en concurrencyvolgorde staan in PC-000.

PC-001 persisteert nu header en volledige source-line snapshots, inclusief de typed
Original TaxPosting-reference en exacte source Payable/Credit OpenItem-reference.
Create, Draft-update, Finalize en Cancel zijn tenant-scoped en schrijven geen financiële
facts. Alleen PC-002 introduceert de posted source-line claim en Post-overgang.
