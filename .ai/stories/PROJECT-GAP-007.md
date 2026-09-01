# PROJECT-GAP-007 – International Purchase VAT Predecessor Review

Reviewdatum en fiscale verificatiedatum: **1 september 2026**. Type: design-, Fiscal-
contract- en architectuurreview. Deze review wijzigt geen productiecode, schema,
permissions, TaxCode-data of developmentdata en start de predecessor niet.

## 1. Besluit

International Purchase VAT is nog niet implementation-ready. De kleinste noodzakelijke
predecessor is:

**IPV-000 – Tax Treatment & Multi-Leg Architecture Alignment.**

De huidige domestic Purchase-keten is feitelijk single-leg Input VAT. Eén gekozen
TaxCode levert per PurchaseInvoiceLine één berekening, één Input TaxPosting en hoogstens
één Input-VAT-journalregel. Reverse charge vereist daarentegen een volledige verschuldigde
VAT-leg én een afzonderlijke, mogelijk beperkte, deductible Input-leg op dezelfde base.
Dat verschil mag niet worden gefaket met twee unrelated TaxCodes of source lines.

## 2. Autoritatieve baseline

De review omvat PROJECT-GAP-005/006, AP-000 t/m AP-004, P3-000 t/m P3-004, PC-000
t/m PC-004(R), Domain Model, Permission Catalogue, Product Roadmap en de actuele Fiscal,
Purchasing, Accounting en Reporting-code.

### Huidig line- en taxmodel

Per PurchaseInvoiceLine geldt feitelijk:

- exact één `tax_code_id` en één immutable `PurchaseTaxSnapshot`;
- exact één `TaxCalculation`: `net × rate = tax`, `gross = net + tax`;
- exact één Original/Input `TaxPosting`, ook wanneer tax nul is;
- hoogstens één VAT-journalleg: debit configured Input VAT bij positieve tax;
- één expense/asset-debit voor net en één documentbrede AP-credit voor gross;
- positive tax is alleen toegestaan wanneer de input als volledig aftrekbaar is gekozen;
  `fullyDeductible` wordt niet als duurzame ratio/snapshot bewaard.

De Input VAT-rekening komt uit `PurchasePostingConfiguration`. TaxCode ID/code/name,
rate, Input-direction, treatment, VAT-return- en ICP-classificatie worden op de line
gesnapshot en later als TaxPosting gerealiseerd. De huidige `TaxClassification` weigert
internationale treatments voor Input TaxCodes. Het bestaande `VatOverview` telt
TaxPostings uitsluitend als generieke Input of Output en kent geen Nederlandse
purchase-due-rubrieken 2a/4a/4b.

PurchaseCredit kopieert de historische line- en Original TaxPosting-truth, leest de
werkelijk gebruikte base-, Input-VAT- en AP-accounts uit de geposte JournalEntry/OpenItem
en maakt exact één Input/Reversal TaxPosting per selected source line. Current TaxCode of
configuration wordt terecht niet opnieuw geïnterpreteerd. Dit patroon is uitbreidbaar,
maar de huidige singular source-TaxPosting-link en `taxableBase + tax = gross`-aanname
kunnen nog geen multi-leg treatment exact reversen.

**Current single-leg model sufficient: NEE.**

## 3. Officiële fiscale baseline

Actuele officiële Belastingdienstbronnen, gecontroleerd op 1 september 2026:

- [Intracommunautaire verwerving](https://www.belastingdienst.nl/wps/wcm/connect/bldcontentnl/belastingdienst/zakelijk/btw/zakendoen_met_het_buitenland/goederen_en_diensten_afnemen_uit_andere_eu_landen/btw_berekenen_over_goederen_en_diensten/intracommunautaire_verwerving/intracommunautaire_verwerving): EU-goederen die vanuit een andere lidstaat in Nederland aankomen zijn in Nederland belast; zelf berekende VAT hoort in 4b en aftrekbare voorbelasting in 5b.
- [Aangifte goederen en diensten uit andere EU-landen](https://www.belastingdienst.nl/wps/wcm/connect/bldcontentnl/belastingdienst/zakelijk/btw/zakendoen_met_het_buitenland/goederen_en_diensten_afnemen_uit_andere_eu_landen/aangifte_doen/aangifte-doen-van-goederen-en-diensten-uit-andere-eu-landen): goederen en algemene diensten uit de EU worden in 4b aangegeven; aftrek bestaat alleen voor zover gebruikt voor belaste omzet en hoort in 5b.
- [Uitzonderingen voor EU-diensten](https://www.belastingdienst.nl/wps/wcm/connect/bldcontentnl/belastingdienst/zakelijk/btw/zakendoen_met_het_buitenland/goederen_en_diensten_afnemen_uit_andere_eu_landen/diensten_uit_andere_eu_landen_uitzonderingen): onder meer onroerende zaken, evenementen, kortdurende vervoermiddelenhuur, personenvervoer en restaurant/catering kunnen een andere plaats-/VAT-behandeling hebben.
- [Diensten van leveranciers buiten de EU](https://www.belastingdienst.nl/wps/wcm/connect/bldcontentnl/belastingdienst/zakelijk/btw/zakendoen_met_het_buitenland/zakendoen_buiten_de_eu/aangifte_doen_als_u_zakendoet_buiten_de_eu/aangifte_doen_als_u_diensten_afneemt_van_leveranciers_uit_niet_eu_landen): VAT is meestal naar de Nederlandse afnemer verlegd, verschuldigd in 4a en alleen bij aftrekrecht tevens voorbelasting.
- [Verlegde btw aftrekken](https://www.belastingdienst.nl/wps/wcm/connect/bldcontentnl/belastingdienst/zakelijk/btw/btw_aftrekken/welke_btw_is_aftrekbaar/verlegde_btw_aftrekken): verschuldigde verlegde VAT blijft volledig aangegeven; aftrek in 5b bestaat alleen onder de aftrekvoorwaarden.
- [Belaste en vrijgestelde omzet](https://www.belastingdienst.nl/wps/wcm/connect/bldcontentnl/belastingdienst/zakelijk/btw/btw_aftrekken/belaste_en_vrijgestelde_omzet/): kosten voor belaste omzet zijn volledig, voor vrijgestelde omzet niet en voor gemengd gebruik gedeeltelijk aftrekbaar.
- [Buitenlandse btw](https://www.belastingdienst.nl/wps/wcm/connect/bldcontentnl/belastingdienst/zakelijk/btw/btw_aftrekken/welke_btw_is_aftrekbaar/btw_aftrekken_als_u_zakendoet_met_het_buitenland): in een ander land betaalde VAT is niet aftrekbaar in de Nederlandse aangifte; daarvoor kan een buitenlandse aangifte of afzonderlijk refundproces gelden.
- [Invoer uit niet-EU-landen](https://www.belastingdienst.nl/wps/wcm/connect/bldcontentnl/belastingdienst/zakelijk/btw/zakendoen_met_het_buitenland/zakendoen_buiten_de_eu/aangifte_doen_als_u_zakendoet_buiten_de_eu/aangifte_doen_als_u_goederen_importeert_uit_niet_eu_landen/) en [Vergunning artikel 23](https://www.belastingdienst.nl/wps/wcm/connect/bldcontentnl/belastingdienst/zakelijk/btw/zakendoen_met_het_buitenland/zakendoen_buiten_de_eu/aangifte_doen_als_u_zakendoet_buiten_de_eu/aangifte_doen_als_u_goederen_importeert_uit_niet_eu_landen/vergunning_artikel_23_aanvragen): import-VAT ontstaat bij de douaneaangifte; Article 23 verplaatst betaling naar de VAT-return en vereist vergunning/eigen importadministratie.
- [Berekening VAT bij invoer](https://www.belastingdienst.nl/wps/wcm/connect/bldcontentnl/belastingdienst/zakelijk/btw/zakendoen_met_het_buitenland/zakendoen_buiten_de_eu/btw_berekenen/btw_berekenen_bij_invoer_goederen_uit_niet_eu_landen) en [importadministratie](https://www.belastingdienst.nl/wps/wcm/connect/bldcontentnl/belastingdienst/zakelijk/btw/zakendoen_met_het_buitenland/zakendoen_buiten_de_eu/administratie_bijhouden/): importbase kan douanewaarde, bijkomende kosten en heffingen bevatten en vereist invoeraangifte, uitnodiging tot betaling en andere douanedocumenten.

Deze bronnen beschrijven hoofdregels met uitzonderingen. Software mag regime, plaats van
dienst, aankomstland, aftrekrecht of toepasselijk Nederlands tarief niet alleen uit
supplierland, adres of TaxCode-naam afleiden.

## 4. V1-scenarioselectie

### Gekozen voor International Purchase VAT V1

1. **EU goods arriving in the Netherlands**: intracommunautaire verwerving, uitsluitend
   wanneer B2B-status, vervoer/aankomst in Nederland en toepasselijk Nederlands tarief
   expliciet als facts/evidence zijn vastgelegd. Verschuldigd 4b; aftrekbaar deel 5b.
2. **EU B2B services – general rule only**: supplierfactuur zonder VAT/`btw verlegd`,
   verschuldigd 4b en aftrekbaar deel 5b. De bekende plaats-van-dienstuitzonderingen zijn
   typed unsupported en worden niet stilzwijgend als general-rule service verwerkt.
3. **Non-EU B2B services – general rule only**: waar de Nederlandse hoofdregel de VAT
   naar de afnemer verlegt, verschuldigd 4a en aftrekbaar deel 5b. Uitzonderingen blijven
   typed unsupported.

V1 blijft EUR-only zolang geen immutable FX-rate source/date/EUR fiscal amount bestaat.

### Deferred of geblokkeerd

- **Import goods / Article 23**: deferred. Een supplier invoice is niet de fiscale bron
  voor douanewaarde, invoeraangifte, uitnodiging tot betaling, importdatum, importeur,
  bijkomende kosten of Article-23-vergunning. Hiervoor is eerst een Incoming/Customs
  ImportTaxDocument-contract nodig.
- **Foreign VAT charged by supplier**: veilig V1-beleid is typed unsupported/block.
  Buitenlandse VAT wordt nooit Nederlandse Input VAT. Een latere foreign-VAT-reclaim-
  module kan het suppliergross, land, foreign amount/currency en refundstatus modelleren;
  een ongemarkeerde gross-expense workaround is geen fiscale waarheid.
- **Domestic reverse charge**: dezelfde multi-legengine moet dit later kunnen dragen
  (verschuldigd 2a, mogelijke aftrek 5b), maar het is geen International V1-scenario en
  vereist eigen typed treatment/evidence. Geen stille scope-uitbreiding.
- speciale EU goods/services, triangulation, installatie/montage, nieuwe vervoermiddelen,
  accijns/KOR, onroerende zaken, evenementen, vervoer, restaurant/catering en overige
  plaats-van-dienstuitzonderingen.

## 5. Multi-leg en payablebesluit

Voor EUR 100 tegen 21% reverse charge en volledig aftrekrecht is vereist:

```text
Debit   Expense/Asset                 100.00
Debit   Deductible Input VAT           21.00
Credit  Accounts Payable              100.00
Credit  VAT payable/reverse charge      21.00
```

Eén huidige TaxPosting kan dit niet representeren: direction, amount, classification en
tax-journalregel zijn singular. De supplier heeft geen EUR 21 gefactureerd; documentgross,
Payable/OpenItem en latere payment blijven daarom **EUR 100**, niet EUR 121. De EUR 21
payable- en deductible legs zijn fiscale/accountingfacts buiten supplier payable.

De bestaande Payable-, payment-, settlement- en matchingsemantiek is herbruikbaar mits
PurchaseInvoice `supplierGross` voortaan treatment-aware wordt onderscheiden van
calculated tax legs. Domestic `100 + 21 = payable 121` blijft exact compatibel;
reverse-charge `supplierGross = 100`.

## 6. Deductibility

Het predecessorcontract ondersteunt een immutable deductible ratio/allocation van 0%
tot 100%, met rationale/policyversie en authoritative actor/tijd wanneer de gebruiker de
ratio bepaalt. Bij EUR 21 VAT en 50% aftrek:

```text
Debit   Expense/Asset base            100.00
Debit   Deductible Input VAT           10.50
Debit   Non-deductible tax cost         10.50
Credit  Accounts Payable              100.00
Credit  VAT payable/reverse charge      21.00
```

VAT payable blijft altijd EUR 21. Alleen EUR 10,50 is Input VAT/5b. Het niet-aftrekbare
deel is geen fictieve deductible taxleg en verhoogt economisch de expense/asset cost. V1
boekt het daarom als afzonderlijk traceerbare cost-journalleg naar dezelfde door de line
gekozen Expense/Asset-rekening; een latere expliciete capitalization/allocationpolicy kan
hiervan afwijken. Het bedrag mag niet in de taxable base worden verstopt en niet uit een
globale VAT-accountconfiguratie worden afgeleid.

## 7. Voorkeursarchitectuur

Voorkeur: **C – een nieuw frameworkonafhankelijk tax calculation resultmodel**, met
additieve uitbreiding van `TaxPosting` als gerealiseerd legfeit. Geen child
`TaxPostingLeg`: TaxPosting is al de immutable, journal-linked fiscale leg.

Conceptueel:

```text
Selected TaxCode / TaxTreatment snapshot
  -> PurchaseTaxTreatmentPolicy
  -> PurchaseTaxCalculationResult
       supplierNet / supplierVat / supplierGross
       taxableBase / appliedDutchRate
       deductibleRatio
       nonDeductibleCost
       TaxLeg[]

TaxLeg
  role: VatPayable | DeductibleInput
  direction: Output | Input
  taxableBase / rate / amount
  reportingClassification
  ledgerAccountRole
  source treatment/rule-version snapshot
```

Eén gebruikerskeuze blijft gewenst, bijvoorbeeld `EU diensten 21% verlegd`. De TaxCode
selecteert één typed treatmentplan; de gebruiker combineert nooit losse payable/input
legs. De huidige `TaxCode.direction` en `TaxClassification` zijn leg-georiënteerd en
moeten in IPV-000 worden uitgelijnd: selection eligibility/treatment is niet hetzelfde
als de directions van de gegenereerde legs.

Nieuwe TaxPostings krijgen minimaal een immutable treatment-event/group identity,
leg-role, treatment/rule version, fiscal reporting classification, deductible ratio en
source snapshot. Iedere leg behoudt haar concrete JournalEntry-/JournalEntryLine-links.
Een group voorkomt dat unrelated Input/Output-postings als pair worden geïnterpreteerd.

## 8. Reportingfacts en datums

Deterministische latere reporting vereist ten minste afzonderlijke classifications voor:

- domestic supplier-charged deductible Input VAT;
- EU acquisition/general service VAT due (4b);
- non-EU general service VAT due (4a);
- domestic reverse-charge VAT due (2a, deferred productscope);
- deductible Input VAT (5b);
- import VAT due/deductible (deferred customs scope).

Het bestaande `DomesticStandard`/`EuServices`-model is onvoldoende en deels op Sales/ICP
gericht. Purchase acquisitions zijn geen ICP-output van de koper. VAT-return Web,
rounding, reconciliation, filing en fiscal locks blijven buiten V1, maar de immutable
facts moeten later zonder code-/naamheuristiek per rubriek aggregeerbaar zijn.

AP blijft volledig gescheiden:

- Accounting `PostingDate` bepaalt JournalEntry en `AccountingPeriodPostingGuard`;
- Fiscal `FiscalReportingDate` bepaalt de taxfacts en een toekomstige fiscal/VAT lock.

De huidige kolom/getter `TaxPosting.postingDate` draagt voor Purchase feitelijk de fiscale
datum. IPV-000 moet dit contract expliciet als `FiscalReportingDate` benoemen zonder AP-
semantiek te wijzigen. International treatments vereisen daarnaast een typed fiscal-date
policy en bewijsinputs; de domestic `max(invoiceDate, receivedDate)`-regel mag niet
automatisch worden hergebruikt voor EU/non-EU scenarios.

## 9. Accounts en configuration

Benodigd:

- Expense/Asset: blijft line-owned;
- Accounts Payable: bestaande configuration, suppliergross only;
- Input VAT: bestaande configuration, alleen werkelijk deductible amount;
- **VAT payable/reverse-charge**: nieuwe active same-tenant Liability-reference in
  PurchasePostingConfiguration of een typed Fiscal account-role configuration.

Voorkeur is een capabilityneutrale typed Fiscal account-role boundary wanneer meerdere
capabilities dezelfde VAT-controlaccounts gaan gebruiken; IPV-000 beslist ownership.
Historical TaxPostings bewaren concrete account-/journal-line-links en treatmentleg-
snapshots, zodat configwijzigingen nooit PurchaseCredit-reversal herinterpreteren.

## 10. PurchaseCredit historische reversal

PurchaseCredit blijft full-source-line in V1 en reverseert exact het gerealiseerde
treatment-event:

- expense/asset base;
- volledige VAT-payable leg;
- werkelijk deductible Input-leg;
- non-deductible costleg;
- iedere reportingclassification;
- supplier Payable tegen suppliergross.

Iedere reversal verwijst één-op-één naar haar Original TaxPosting; de creditline bewaart
de treatment-group/source links en de reader haalt concrete historical journal accounts
en amounts op. Current TaxCode, deductible ratio, rate, configuration of reportingpolicy
wordt nooit opnieuw toegepast. De huidige `tp_one_reversal_unique` blijft per leg nuttig;
group-completeness plus source-line claim moet voorkomen dat slechts een subset commit.

## 11. Additive migration en compatibiliteit

Geen bestaande domestic TaxPosting, PurchaseInvoice, PurchaseCredit of financiële fact
wordt herschreven. Voorkeursstrategie:

1. additieve nullable/versioned treatment-group- en leg-rolevelden;
2. nieuwe international facts gebruiken altijd het nieuwe contract;
3. null/legacy betekent uitsluitend de bestaande domestic single-input semantics;
4. readers/calculators ondersteunen legacy en new-model parallel;
5. pas na bewezen deterministische classificatie kan een afzonderlijke migratie legacy
   metadata materialiseren; geen correctness-afleiding uit TaxCode-naam/code;
6. bestaande PurchaseCredits blijven exact hun singular Original reversen; nieuwe credits
   kunnen een complete treatment-group reversen.

`VatOverview` moet backward compatible blijven en later legs per reportingclassification
aggregeren zonder double-counting van taxable bases. VAT-returnimplementatie blijft
deferred.

## 12. Authorization, Web en acceptance

Geen nieuwe permission is nodig wanneer de gebruiker binnen bestaande PurchaseInvoice-
DraftManage uitsluitend een toegestane TaxCode/treatment per line kiest. Finalize en Post
blijven door de bestaande onafhankelijke Purchasing-permissions bewaakt. Alleen wanneer
later organization-wide deductibility policy/configuration wordt beheerd, is een aparte
settingspermission opnieuw te beoordelen; niet vooraf invoeren.

Web V1 toont één treatmentselector en duidelijke regime/evidencevelden. Geen manual tax-
of Journal-leg editor, geen body-owned Administration en geen automatic treatment uit
supplierland. Unsupported uitzonderingen, foreign VAT, non-EUR en import krijgen typed
feedback vóór Finalize/Post.

Manual acceptance vereist per scenario zichtbare suppliergross/payable, beide taxlegs,
deductibility/non-deductible cost, reportingclassifications, fiscal versus PostingDate,
Close/NoPeriod-denial zonder writes, exact PurchaseCredit-reversal en payment uitsluitend
tegen supplier payable. Developmentcatalogue/config/roles worden expliciet gecontroleerd;
geen production auto-assignment of synthetic financial bootstrap.

## 13. Toekomstige regressiematrix

| Scenario | Verwachte duurzame waarheid |
| --- | --- |
| Domestic 100 + 21 | Expense 100, Input 21, Payable 121; legacy compatibel |
| EU reverse charge 100, 100% | VAT due 21 + Input 21; Payable 100 |
| Reverse charge 100, 0% | VAT due 21, Input 0, cost +21; Payable 100 |
| Reverse charge 100, 50% | VAT due 21, Input 10,50, cost +10,50; Payable 100 |
| PurchaseCredit | Complete exact historical group reversal, geen current reinterpretation |
| Payment | Settlement uitsluitend supplier Payable, nooit VAT control legs |
| AP period | Accounting PostingDate lock onafhankelijk van FiscalReportingDate |
| Concurrency | Post/credit/payment locks, unique source claims en group completeness intact |
| Unsupported | import/foreign VAT/exceptions/non-EUR fail typed vóór writes |

Tests moeten daarnaast rounding, 9%/21%, zero, multiple lines/treatments, tenantisolatie,
configurationdeactivation, idempotency, forced rollback en real-MySQL races bewijzen.

## 14. Definitieve predecessor en storysplit

0. **IPV-000 – Tax Treatment & Multi-Leg Architecture Alignment**: fiscale scenario-
   en datepolicy, TaxCode-selection versus legdirection, calculation/resultcontract,
   deductibility/non-deductible cost, reportingclassifications, account ownership,
   treatment-group/reversal en additive compatibility definitief vastleggen.
1. **IPV-001 – Tax Leg Persistence, Configuration & Calculation**: additieve schema-
   evolutie, typed treatmentcatalogue, VAT-payable config, multi-leg calculator, legacy
   readers en unit/integrationtests; geen Purchase-posting Web.
2. **IPV-002 – International Purchase Posting Integration**: gekozen A/B/C-scenarios,
   evidence/datepolicy, treatment-aware suppliergross, atomic PostingEngine/TaxPostings/
   Payable en AP-denial/concurrency.
3. **IPV-003 – Purchase Credit Reversal & Web Flow**: complete historical group reversal,
   single-selector/evidence UX, typed unsupported feedback en permission/securitytests.
4. **IPV-004 – Review, Manual Acceptance & Regression**: domestic compatibility,
   full/zero/partial deduction, credit/payment/AP, databaseintegriteit en full validation.

Import/customs krijgt later een afzonderlijke predecessor, bijvoorbeeld
`IMP-000 – Import & Customs Fiscal Source Facts`, vóór Article 23-productscope.

## 15. Blockercheck

| Vraag | Antwoord |
| --- | --- |
| current single-leg model sufficient | NEE |
| multi-leg predecessor required | JA |
| deductibility model required | JA |
| import/customs predecessor required | JA, uitsluitend vóór importgoods/Article 23; niet voor gekozen A/B/C V1 |
| current Purchase payable semantics reusable | JA, mits suppliergross treatment-aware losstaat van taxlegs |
| PurchaseCredit historical reversal extensible | JA, na treatment-group/complete-leg contract |
| AP foundation sufficient | JA |
| International Purchase VAT implementation-ready | NEE |

Exacte blocker: het ontbreken van één immutable treatmentplan dat uit één gebruikerskeuze
meerdere correlated VAT-legs, deductibility/non-deductible cost, reportingrubrieken,
accountrollen, fiscal-datepolicy en complete historical reversal voortbrengt.
