# Finance Core Platform Permission Catalogue

Dit document benoemt de businessautorisaties van Finance Core Platform. Het beschrijft uitsluitend toegestane bedrijfshandelingen per capability. Rollen, technische codes, policies en implementatiedetails vallen buiten deze catalogus.

## Administration

| Permission Name | Korte beschrijving |
| --- | --- |
| View Administrations | Administratieve eenheden en hun basisgegevens raadplegen. |
| Create Administration | Een nieuwe administratieve eenheid aanmaken. |
| Change Administration | Naam, omschrijving en toegestane administratie-instellingen wijzigen. |
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

| Permission Name | Korte beschrijving |
| --- | --- |
| View Purchase Documents | Inkoopdocumenten en hun verwerkingsstatus raadplegen. |
| Register Purchase Invoice | Een ontvangen inkoopfactuur registreren. |
| Change Purchase Invoice | Een nog wijzigbare inkoopfactuur corrigeren of aanvullen. |
| Approve Purchase Invoice | Een inkoopfactuur inhoudelijk goedkeuren voor verdere verwerking. |
| Register Purchase Credit Invoice | Een ontvangen inkoopcreditfactuur registreren. |

## Accounting

| Permission Name | Korte beschrijving |
| --- | --- |
| View Ledger | Grootboekrekeningen, journaals en boekingen raadplegen. |
| Manage Chart of Accounts | Het rekeningschema beheren. |
| Create Journal Entry | Een journaalpost opstellen. |
| Post Journal Entry | Een journaalpost definitief boeken. |
| Manage Accounting Periods | Boekingsperioden openen en sluiten. |

## Tax

| Permission Name | Korte beschrijving |
| --- | --- |
| View Tax Configuration | Belastingcodes, tarieven en fiscale instellingen raadplegen. |
| Manage Tax Configuration | Belastingcodes, tarieven en fiscale instellingen beheren. |
| View Tax Returns | Fiscale aangiften en onderliggende bedragen raadplegen. |
| Prepare Tax Return | Een fiscale aangifte voorbereiden. |
| Finalize Tax Return | Een fiscale aangifte definitief vaststellen. |

## Banking

| Permission Name | Korte beschrijving |
| --- | --- |
| View Bank Accounts | Bankrekeningen en hun basisgegevens raadplegen. |
| Manage Bank Accounts | Bankrekeningen toevoegen, wijzigen en deactiveren. |
| Import Bank Statements | Bankafschriften voor verwerking aanleveren. |
| Process Bank Transactions | Bankmutaties beoordelen en verwerken. |
| Allocate Payments | Betalingen aan openstaande posten of andere bestemmingen toewijzen. |

## Documents

| Permission Name | Korte beschrijving |
| --- | --- |
| View Documents | Bedrijfsdocumenten en bijlagen raadplegen. |
| Add Document | Een nieuw bedrijfsdocument vastleggen. |
| Add Attachment | Een bijlage aan een toegestaan domeinobject toevoegen. |
| Change Document Metadata | Toegestane classificatie en metadata van een document wijzigen. |
| Archive Document | Een document duurzaam archiveren. |

## Reporting

| Permission Name | Korte beschrijving |
| --- | --- |
| View Financial Reports | Financiële standaardrapportages raadplegen. |
| View Balance Sheet | De balans voor een toegestaan tijdvak raadplegen. |
| View Profit and Loss Statement | De winst-en-verliesrekening voor een toegestaan tijdvak raadplegen. |
| View Trial Balance | De proef- en saldibalans raadplegen. |
| Export Reports | Toegestane rapportages exporteren. |
