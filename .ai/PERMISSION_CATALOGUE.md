# Finance Core Platform Permission Catalogue

Dit document benoemt de businessautorisaties van Finance Core Platform. Het beschrijft uitsluitend toegestane bedrijfshandelingen per capability. Rollen, technische codes, policies en implementatiedetails vallen buiten deze catalogus.

## Administration

| Permission Name | Korte beschrijving |
| --- | --- |
| View Administrations | Administratieve eenheden en hun basisgegevens raadplegen. |
| Create Administration | Een nieuwe administratieve eenheid aanmaken. |
| Change Administration Settings | Naam, omschrijving en andere expliciet ondersteunde masterdata-instellingen van uitsluitend de actieve administratie raadplegen en wijzigen (`ADMINISTRATION.SETTINGS_UPDATE`). Geeft geen recht op verwijderen, memberships, rollen, users, accounting-postings of Sales-beheer. |
| Manage Organisation Details | De juridische en contactgegevens van de gekoppelde organisatie beheren. |
| Manage Number Sequences | Documentnummerreeksen configureren, activeren en deactiveren. |

## Identity

| Permission Name | Korte beschrijving |
| --- | --- |
| View Users | Gebruikers en hun status raadplegen. |
| Manage Users | Gebruikersgegevens en gebruikersstatus beheren. |
| View Administration Memberships | Toegang van gebruikers tot administraties raadplegen. |
| Manage Administration Memberships | Administratielidmaatschappen aanmaken, activeren en deactiveren. |
| Manage Roles | Rollen en hun status beheren. |

## Relations

| Permission Name | Korte beschrijving |
| --- | --- |
| View Relations | Relaties en hun basisgegevens raadplegen. |
| Create Relation | Een nieuwe klant, leverancier of andere zakelijke relatie vastleggen. |
| Change Relation | Identificatie-, contact- en adresgegevens van een relatie wijzigen. |
| Manage Customer Classification | Een relatie als klant classificeren en klantgegevens beheren. |
| Manage Supplier Classification | Een relatie als leverancier classificeren en leveranciersgegevens beheren. |

## Sales

| Permission Name | Korte beschrijving |
| --- | --- |
| View Sales Documents | Offertes, verkooporders, verkoopfacturen en verkoopcreditfacturen raadplegen. |
| Manage Quotations | Offertes opstellen, wijzigen en via hun toegestane zakelijke statusovergangen beheren. |
| Manage Sales Orders | Verkooporders vastleggen, wijzigen en via hun toegestane zakelijke statusovergangen verwerken. |
| Manage Sales Invoice Drafts | Concept-verkoopfacturen aanmaken en wijzigen en hun regels beheren zolang de documentstatus dit toestaat; geeft geen finalisatie- of postingbevoegdheid. |
| Issue Sales Invoices | Verkoopfacturen finaliseren en definitief uitgeven; geeft geen financiële of fiscale postingbevoegdheid. |
| Post Sales Invoices | Reeds definitieve verkoopfacturen via de daarvoor bestemde Sales-, Accounting- en Fiscal-orchestratie financieel en fiscaal posten; geeft geen draftbeheer- of finalisatiebevoegdheid. |
| Manage Sales Credit Invoice Drafts | Concept-verkoopcreditfacturen aanmaken en wijzigen en hun regels beheren zolang de documentstatus dit toestaat; geeft geen finalisatie-, posting- of reversalbevoegdheid. |
| Issue Sales Credit Invoices | Verkoopcreditfacturen finaliseren en definitief uitgeven; geeft geen financiële posting- of fiscale reversalbevoegdheid. |
| Post Sales Credit Invoices | Reeds definitieve verkoopcreditfacturen via de daarvoor bestemde creditposting-orchestratie financieel en fiscaal posten en uitsluitend daarbij de gekoppelde oorspronkelijke fiscale posting reversen; geeft geen draftbeheer, finalisatie of algemene reversalbevoegdheid. |

## Purchasing

P3-001 implementeert de volgende typed, onafhankelijke permissions voor de eerste
domestic PurchaseInvoice-flow.

| Permission Code | Korte beschrijving |
| --- | --- |
| `PURCHASING.VIEW` | Inkoopfacturen, status, postinglink en Payable read-only raadplegen. |
| `PURCHASING.INVOICES_DRAFT_MANAGE` | Domestic Draft-inkoopfacturen aanmaken, wijzigen en vóór posting annuleren; geeft geen finalisatie- of postingrecht. |
| `PURCHASING.INVOICES_FINALIZE` | Een valide Draft inhoudelijk vaststellen als immutable Finalized document; geeft geen postingrecht. |
| `PURCHASING.INVOICES_POST` | Een Finalized factuur via de enige transactionele Purchase-postingorchestrator financieel/fiscaal posten; geeft geen draftbeheer of finalisatierecht. |

De canonieke rolebundles zijn: `PURCHASING_MANAGER` bevat View,
DraftManage en Finalize; `PURCHASING_POSTER` bevat alleen View en Post. Er is geen
impliciete permissionhiërarchie, role-name authorization of automatische
membershipassignment. PurchasePostingConfiguration gebruikt de bestaande
`ADMINISTRATION.SETTINGS_UPDATE`-permission. Credit- en paymentpermissions blijven
buiten P3. De stabiele permission-IDs zijn respectievelijk
`6e854eb8-7cc4-4c61-8328-6aa41cb0ac01`,
`0f950710-7de5-42e7-a716-08ae09b17b5c`,
`7593b1f9-39b1-480e-ab8b-46792141d4bb` en
`c4926113-c69a-49b2-94a3-0f98bcaee9b3`. Role-IDs zijn
`8c0eb3c2-2c80-4960-85bd-8649508cba83` en
`09544538-b049-4cf5-a691-8b88427f7b31`.

PC-001 implementeert daarnaast drie onafhankelijke PurchaseCredit-permissions.
Bestaande invoicepermissions worden niet verbreed.

| Permission Code | Korte beschrijving |
| --- | --- |
| `PURCHASING.CREDITS_DRAFT_MANAGE` | Draft leverancierscreditnota's tegen een eligible source invoice aanmaken, wijzigen en vóór posting annuleren; uitsluitend volledige source-line selectie. |
| `PURCHASING.CREDITS_FINALIZE` | Een valide Draft creditnota inhoudelijk vaststellen als immutable Finalized document; geeft geen postingrecht. |
| `PURCHASING.CREDITS_POST` | Een Finalized creditnota atomisch financieel/fiscaal posten en exact tegen haar source matchen voor zover daar saldo beschikbaar is; geeft geen draftbeheer of finalisatierecht. |

`PURCHASING.VIEW` geldt ook voor creditlist/detail/source-read. Na PC-001 bevat
`PURCHASING_MANAGER` daarnaast credit DraftManage en Finalize; `PURCHASING_POSTER`
daarnaast credit Post. Er volgt geen automatische membershipassignment. De stabiele
creditpermission-IDs zijn `3a85b19c-8196-47bb-90e2-94c4aa72c101`,
`3a85b19c-8196-47bb-90e2-94c4aa72c102` en
`3a85b19c-8196-47bb-90e2-94c4aa72c103`.

## Accounting

| Permission Code / Name | Korte beschrijving |
| --- | --- |
| View Ledger | Grootboekrekeningen, journaals en boekingen raadplegen. |
| Manage Chart of Accounts | Het rekeningschema beheren. |
| Create Journal Entry | Een journaalpost opstellen. |
| Post Journal Entry | Een journaalpost definitief boeken. |
| `ACCOUNTING.PERIODS_VIEW` | BookYears, AccountingPeriods, actuele status, readiness en immutable statushistorie raadplegen. |
| `ACCOUNTING.PERIODS_MANAGE` | Expliciete BookYear-/periodsetup, planvalidatie, labels en uitsluitend lege setupcorrecties beheren; geeft geen Close/Reopen-recht. |
| `ACCOUNTING.PERIODS_CLOSE` | Een Open AccountingPeriod met verplichte reden sluiten; geeft geen Manage- of Reopen-recht. |
| `ACCOUNTING.PERIODS_REOPEN` | Een Closed AccountingPeriod met verplichte reden heropenen; high-impact en onafhankelijk van Manage/Close/Post. |

AP-001 implementeert de canonical role `ACCOUNTING_PERIOD_MANAGER`
(`a9020000-0000-4000-8000-000000000001`) met uitsluitend Period View + Manage + Close
en `ACCOUNTING_PERIOD_REOPENER` (`a9020000-0000-4000-8000-000000000002`) met uitsluitend
Period View + Reopen. De vier permission-IDs lopen stabiel van
`a9010000-0000-4000-8000-000000000001` tot en met `...0004`; definitions en
role-permissionlinks zijn idempotent en collision-safe. Er is geen permissionhiërarchie, role-name authorization of
automatische production-membershipassignment. AP-003 handhaaft iedere permission op de
eigen Webroutes en gebruikt uitsluitend effective permission-IDs; navigatie gebruikt
View en nooit een role-name. Voor manual acceptance zijn beide canonical rollen expliciet
en uitsluitend in de developmentdatabase aan de actieve membership van
`dev-admin@financecore.local` toegewezen. Dit is developmentdata, geen seeder- of
production auto-assignment; AP-003 voert assignments nooit automatisch uit.

## Tax

| Permission Name | Korte beschrijving |
| --- | --- |
| View Tax Configuration | Belastingcodes, tarieven en fiscale instellingen raadplegen. |
| Manage Tax Configuration | Belastingcodes, tarieven en fiscale instellingen beheren. |
| View Tax Returns | Fiscale aangiften en onderliggende bedragen raadplegen. |
| Prepare Tax Return | Een fiscale aangifte voorbereiden. |
| Finalize Tax Return | Een fiscale aangifte definitief vaststellen. |

## Banking

B2 gebruikt drie onafhankelijke typed permissions voor handmatige bankbetalingen. B3
voegt een afzonderlijke high-impact reversalpermission toe.

| Permission Code | UUID | Korte beschrijving |
| --- | --- | --- |
| `BANKING.VIEW` | `b2010000-0000-4000-8000-000000000001` | Handmatige BankTransactions, Payments, allocations en settlementresultaat read-only raadplegen. |
| `BANKING.PAYMENTS_MANAGE` | `b2010000-0000-4000-8000-000000000002` | Draft manual payments aanmaken/wijzigen, allocations beheren, finaliseren en Draft annuleren; geeft geen postingrecht. |
| `BANKING.PAYMENTS_POST` | `b2010000-0000-4000-8000-000000000003` | Een immutable Finalized Payment via de transactionele Banking-postingorchestrator posten en de OpenItems settelen. |
| `BANKING.PAYMENTS_REVERSE` | `b2010000-0000-4000-8000-000000000004` | Eén Posted handmatige BankTransaction volledig, append-only en atomisch corrigeren; geeft geen manage- of algemeen postingrecht. |

De canonieke rollen zijn `BANKING_MANAGER` (`b2020000-0000-4000-8000-000000000001`,
View + Payments Manage) en `BANKING_POSTER`
(`b2020000-0000-4000-8000-000000000002`, View + Payments Post), zonder automatische membershipassignment of
role-name authorization. B3 reserveert daarnaast `BANKING_REVERSAL_OPERATOR`
(`b2020000-0000-4000-8000-000000000003`, View + Payments Reverse). De bestaande
`BANKING_POSTER` krijgt reverse niet impliciet: correctie blijft afzonderlijk
toekenbaar en intrekbaar. Operationele Administration-bankrekeningen en
BankingPostingConfiguration gebruiken `ADMINISTRATION.SETTINGS_UPDATE`. Import,
reconciliation en suspense/overpayment blijven buiten scope. Geen van deze rollen wordt
automatisch aan production-memberships toegekend.

## Documents

| Permission Name | Korte beschrijving |
| --- | --- |
| View Documents | Bedrijfsdocumenten en bijlagen raadplegen. |
| Add Document | Een nieuw bedrijfsdocument vastleggen. |
| Add Attachment | Een bijlage aan een toegestaan domeinobject toevoegen. |
| Change Document Metadata | Toegestane classificatie en metadata van een document wijzigen. |
| Archive Document | Een document duurzaam archiveren. |

## Delivery Operations

| Permission Name | Korte beschrijving |
| --- | --- |
| Resolve Ambiguous Delivery Outcomes | Een `OutcomeUnknown` documentdelivery onder uitsluitend de actieve Administration expliciet en auditbaar operationeel afhandelen (`DELIVERY.OUTCOME_RESOLVE`). Geeft geen recht op documentmutatie/verzending, posting, recipient- of sendersettings, SMTP-configuratie, users, memberships, rollen of algemene Administration-settings. De canonieke rol `DELIVERY_OPERATOR` bevat uitsluitend deze permission en wordt nooit automatisch aan een membership toegekend. |

## Reporting

| Permission Name | Korte beschrijving |
| --- | --- |
| View Financial Reports | Financiële standaardrapportages raadplegen. |
| View Balance Sheet | De balans voor een toegestaan tijdvak raadplegen. |
| View Profit and Loss Statement | De winst-en-verliesrekening voor een toegestaan tijdvak raadplegen. |
| View Trial Balance | De proef- en saldibalans raadplegen. |
| Export Reports | Toegestane rapportages exporteren. |
