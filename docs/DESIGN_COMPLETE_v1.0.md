# Finance Core Platform

## DESIGN COMPLETE v1.0

**Status:** DESIGN COMPLETE v1.0  
**Buildmodus:** Actief  
**Documenttype:** Gecontroleerde ontwerpbaseline  
**Doelversie:** Finance Core Platform 1.0  

## 1. Managementsamenvatting

Finance Core Platform wordt een professioneel Nederlands boekhoud- en financieel administratieplatform voor meerdere bedrijven of administraties binnen één installatie.

Kernprincipes:

1. Multi-administratie is verplicht.
2. Iedere gebruiker heeft alleen toegang tot expliciet toegewezen administraties.
3. Iedere financiële mutatie loopt via de Posting Engine.
4. De Accounting Engine is volledig framework-onafhankelijk.
5. Definitieve journaalposten worden niet stilzwijgend gewijzigd.
6. Correcties verlopen via nieuwe, traceerbare boekingen.
7. Code is de primaire waarheid.
8. Documentatie groeit mee met de code.
9. Iedere sprint levert werkende, geteste software op.
10. `main` blijft stabiel en wordt alleen via gecontroleerde pull requests bijgewerkt.

Technische stack:

- Laravel 13
- PHP 8.5
- MySQL 8.4 LTS
- Redis
- Docker
- GitHub
- PHPUnit
- PHPStan
- Pint
- Livewire
- Alpine.js
- Tailwind CSS
- Vite

## 2. Doel en doelgroep

Het platform ondersteunt de volledige administratieve en financiële cyclus:

- relaties;
- klanten en leveranciers;
- offertes en orders;
- urenregistratie;
- verkoop- en inkoopfacturen;
- betalingen;
- banktransacties;
- grootboek;
- btw;
- rapportages;
- documenten;
- logging en audittrail.

Doelgroep:

- Nederlandse mkb-bedrijven;
- administratiekantoren;
- financiële medewerkers;
- ondernemers met meerdere administraties;
- accountants en controleurs.

Belangrijkste rollen:

- Platformbeheerder
- Administratiebeheerder
- Financieel beheerder
- Boekhouder
- Verkoopmedewerker
- Inkoopmedewerker
- Urenregistratiegebruiker
- Rapportagelezer
- Accountant/controleur
- Integratieaccount

## 3. Scope versie 1.0

Verplicht:

- Administratiebeheer
- Gebruikers, rollen en rechten
- Relatiebeheer
- Klanten
- Leveranciers
- Offertes
- Orders
- Urenregistratie
- Verkoopfacturen en creditfacturen
- Inkoopfacturen en creditfacturen
- Betalingen
- Grootboek
- Btw-verwerking
- Banktransacties
- Documentbeheer
- Rapportages
- Dashboard
- Instellingen
- Logging en audittrail
- Back-up en herstel
- Framework-onafhankelijke Accounting Engine
- Posting Engine
- Multi-administratie en tenant-isolatie

Latere versie:

- Automatische bankkoppelingen
- OCR voor inkoopfacturen
- Elektronische facturatie
- Consolidatie
- Budgettering
- Cashflowprognoses
- Periodieke facturatie
- Projectadministratie
- Accountantportal

## 4. Functionele modules

### 4.1 Administratiebeheer

Doel: meerdere administraties binnen één installatie beheren.

Functies:

- administratie aanmaken, wijzigen, blokkeren en archiveren;
- juridische en fiscale basisgegevens beheren;
- boekjaar en standaardvaluta instellen;
- gebruikers aan administraties koppelen;
- administratie wisselen;
- administraties strikt isoleren.

Acceptatiecriteria:

- gebruiker ziet alleen toegestane administraties;
- iedere administratiegebonden tabel bevat `administration_id`;
- ongeautoriseerde toegang wordt centraal geblokkeerd;
- administratiecontext is beschikbaar voor web, CLI, queue en API.

### 4.2 Gebruikers en rollen

- gebruikersbeheer;
- rollen per administratie;
- permissions;
- accountblokkering;
- sessiebeheer;
- wachtwoordherstel;
- optionele MFA;
- audit van inlog- en rechtenwijzigingen.

### 4.3 Relatiebeheer

- organisatie of persoon;
- adressen;
- contactpersonen;
- communicatiegegevens;
- betaalgegevens;
- documenten;
- notities;
- relatieclassificatie.

### 4.4 Klanten

- debiteurnummer;
- betalingstermijn;
- factuuradres;
- btw-behandeling;
- standaardgrootboekrekening;
- kredietlimiet;
- openstaande posten.

### 4.5 Leveranciers

- crediteurnummer;
- betaalcondities;
- IBAN;
- btw-behandeling;
- standaardkostenrekening;
- openstaande posten.

### 4.6 Offertes

- offerteheader en regels;
- versies;
- geldigheidsduur;
- statusflow;
- omzetting naar order;
- PDF;
- verzending;
- audittrail.

### 4.7 Orders

- orderregels;
- planning;
- levering;
- koppeling met uren;
- omzetting naar factuur;
- statusbeheer.

### 4.8 Urenregistratie

- timer en handmatige invoer;
- goedkeuring;
- factureerbaar/niet-factureerbaar;
- kostprijs en verkoopprijs;
- factuurvoorstel.

### 4.9 Facturatie

- concept en definitief;
- nummerreeks;
- factuurregels;
- kortingen;
- btw;
- PDF;
- verzending;
- openstaande post;
- automatische journaalpost via Posting Engine.

### 4.10 Inkoopfacturen

- factuurheader en regels;
- kosten- en activarekeningen;
- btw;
- betaalstatus;
- documentbijlage;
- openstaande post;
- journaalpost via Posting Engine.

### 4.11 Betalingen

- deelbetalingen;
- meerdere facturen per betaling;
- betalingsverschillen;
- terugbetalingen;
- aflettering;
- boeking via Posting Engine.

### 4.12 Grootboek

- rekeningschema;
- dagboeken;
- journaalposten;
- boekingsregels;
- proef- en saldibalans;
- grootboekkaart;
- periodecontrole;
- correctieboekingen;
- afsluiting.

### 4.13 Btw-verwerking

- btw-codes;
- binnenland, EU en overige landen;
- verkoop- en inkoop-btw;
- rapportage per aangiftetijdvak;
- correcties;
- aansluiting met grootboek.

Actuele fiscale details worden vóór productiegebruik gecontroleerd tegen geldende wet- en regelgeving.

### 4.14 Banktransacties

- handmatige import;
- CAMT/MT940 waar relevant;
- herkenningsregels;
- matching met facturen;
- boekingsvoorstellen;
- definitieve verwerking via Posting Engine.

### 4.15 Documentbeheer

- uploads;
- metadata;
- versiebeheer;
- veilige download;
- autorisatie per administratie;
- bestandstypecontrole;
- bewaartermijnen.

### 4.16 Rapportages

Minimaal:

- balans;
- winst-en-verliesrekening;
- proef- en saldibalans;
- grootboekkaart;
- openstaande debiteuren;
- openstaande crediteuren;
- btw-overzicht;
- omzetrapport;
- urenrapport.

### 4.17 Dashboard

- liquide middelen;
- openstaande debiteuren;
- openstaande crediteuren;
- omzet;
- kosten;
- resultaat;
- openstaande taken;
- signaleringen.

### 4.18 Instellingen

- nummerreeksen;
- betalingstermijnen;
- standaardrekeningen;
- btw-codes;
- documenttemplates;
- e-mailinstellingen;
- boekingsperioden.

### 4.19 Logging en audittrail

Minimaal loggen:

- gebruiker;
- administratie;
- actie;
- objecttype;
- object-ID;
- oude en nieuwe waarden waar toegestaan;
- datum/tijd;
- request-ID;
- IP-adres waar passend;
- reden bij correcties.

### 4.20 Back-up en herstel

- geautomatiseerde databaseback-ups;
- documentback-ups;
- versleutelde opslag;
- retentiebeleid;
- herstelprocedure;
- periodieke restore-test;
- scheiding van productie en test.

## 5. Boekhoudkundig ontwerp

### 5.1 Dubbel boekhouden

Iedere definitieve financiële transactie resulteert in een gebalanceerde journaalpost:

`Totaal debet = totaal credit`

De Posting Engine weigert ongebalanceerde transacties.

### 5.2 Grootboekrekeningen

Iedere grootboekrekening bevat minimaal:

- administratie-ID;
- rekeningnummer;
- naam;
- rekeningtype;
- balans- of resultaatrekening;
- debet/credit-natuur;
- actief-status;
- rapportageclassificatie.

### 5.3 Dagboeken

Minimaal:

- verkoopboek;
- inkoopboek;
- bank;
- kas;
- memoriaal;
- openingsbalans.

### 5.4 Journaalposten

Een journaalpost bevat:

- administratie;
- boekjaar;
- periode;
- dagboek;
- datum;
- boekingsnummer;
- omschrijving;
- bron;
- status;
- regels;
- auditgegevens.

Statussen:

- draft;
- validated;
- posted;
- reversed.

Een definitief geboekte journaalpost wordt niet verwijderd of stilzwijgend aangepast.

### 5.5 Factuurboekingen

Verkoopfactuur:

- Debet: Debiteuren
- Credit: Omzet
- Credit: Te betalen btw

Inkoopfactuur:

- Debet: Kosten/activa
- Debet: Te vorderen btw
- Credit: Crediteuren

### 5.6 Betalingsboekingen

Ontvangst klant:

- Debet: Bank
- Credit: Debiteuren

Betaling leverancier:

- Debet: Crediteuren
- Credit: Bank

### 5.7 Creditfacturen

Creditfacturen zijn zelfstandige transacties met eigen nummer en audittrail. Zij boeken tegengesteld aan de oorspronkelijke factuur.

### 5.8 Openstaande posten

Openstaande posten worden via betalingen, verrekeningen of correcties verminderd en blijven herleidbaar naar hun bron.

### 5.9 Btw-codes

Een btw-code bepaalt minimaal:

- percentage;
- richting;
- aangifterubriek;
- grootboekrekeningen;
- land/regio;
- geldigheidsperiode;
- verleggingsgedrag.

### 5.10 Boekjaren en periodes

- iedere administratie heeft eigen boekjaren;
- boekjaar bevat perioden;
- perioden kunnen open, voorlopig gesloten of definitief gesloten zijn;
- definitief gesloten perioden accepteren geen normale boekingen;
- heropening vereist expliciet recht en auditregistratie.

### 5.11 Openingsbalans

Openingsbalansen worden als traceerbare journaalposten verwerkt in een speciaal dagboek.

### 5.12 Correctieboekingen

Correcties verlopen via:

- tegenboeking;
- reversal;
- aanvullende correctieboeking.

De oorspronkelijke boeking blijft behouden.

### 5.13 Audittrail

Iedere financiële mutatie moet herleidbaar zijn van:

bronobject → posting request → journaalpost → boekingsregels → rapportage.

## 6. Technische architectuur

### 6.1 Hoofdstructuur

```text
app/
├── Application/
├── Domain/
│   ├── Administration/
│   ├── Identity/
│   ├── Relations/
│   ├── Sales/
│   ├── Purchasing/
│   ├── Banking/
│   └── Reporting/
├── Infrastructure/
├── Http/
├── Livewire/
├── Models/
├── Policies/
└── Providers/

packages/
└── AccountingCore/
    ├── src/
    │   ├── Domain/
    │   ├── Application/
    │   └── Contracts/
    └── tests/
```

### 6.2 Accounting Engine

De Accounting Engine:

- bevat geen Laravel-afhankelijkheden;
- kent geen Eloquent;
- kent geen HTTP;
- kent geen Livewire;
- werkt via interfaces en value objects;
- bevat de Posting Engine en boekhoudkundige validaties.

### 6.3 Applicatielagen

Presentation:

- Livewire
- controllers
- API-resources
- viewmodels
- formulieren

Application:

- use cases
- commands
- queries
- DTO's
- transactieregie

Domain:

- entities
- value objects
- domain services
- events
- invarianten

Infrastructure:

- Eloquent repositories
- database-adapters
- documentopslag
- queue-adapters
- e-mail
- externe integraties

### 6.4 Multi-administratie

Iedere administratiegebonden tabel bevat `administration_id`.

Tenant-isolatie wordt afgedwongen via:

1. Administration Context
2. Middleware
3. Policies
4. Query scopes of repositories
5. Foreign keys
6. Tests
7. Audit logging

### 6.5 Authenticatie

- veilige wachtwoordhashing;
- rate limiting;
- sessieregeneratie;
- accountblokkering;
- optionele MFA;
- veilige password reset.

### 6.6 Autorisatie

- rollen per administratie;
- permissions;
- policies;
- centrale administratietoegang;
- aparte platformrechten;
- deny-by-default.

### 6.7 Sessiebeheer

Redis wordt gebruikt voor sessies.

### 6.8 API

API-basis:

```text
/api/v1/...
```

Regels:

- authenticatie;
- scopes;
- administratiecontext;
- idempotency waar relevant;
- rate limiting;
- request-ID;
- audit logging;
- consistente foutcodes.

### 6.9 Foutafhandeling

- geen technische details naar eindgebruikers;
- request-ID in foutrespons;
- centrale logging;
- domeinfouten apart van infrastructuurfouten;
- financiële validatiefouten expliciet en reproduceerbaar.

### 6.10 Documentopslag

Documenten worden buiten de publieke webroot opgeslagen en alleen via geautoriseerde routes of signed URLs aangeboden.

### 6.11 Omgevingen

- local
- test
- staging
- production

Geen productiegegevens in ontwikkel- of testomgevingen zonder gecontroleerde anonimisering.

## 7. Databaseontwerp

### 7.1 Sleutelstrategie

Interne primaire sleutel:

```text
BIGINT UNSIGNED AUTO_INCREMENT
```

Publieke identificatie waar nodig:

```text
public_id UUID
```

### 7.2 Auditvelden

Standaard waar relevant:

- `created_at`
- `updated_at`
- `deleted_at`
- `created_by`
- `updated_by`
- `deleted_by`

### 7.3 Systeemgegevens

- users
- roles
- permissions
- role_permission
- system_settings
- jobs
- failed_jobs
- cache
- sessions

### 7.4 Administratiegebonden stamgegevens

- administrations
- administration_user
- contacts
- customers
- suppliers
- addresses
- payment_terms
- number_sequences
- tax_codes
- ledger_accounts
- journals

### 7.5 Operationele transacties

- quotations
- quotation_lines
- orders
- order_lines
- time_entries
- sales_invoices
- sales_invoice_lines
- purchase_invoices
- purchase_invoice_lines
- payments
- payment_allocations
- bank_transactions

### 7.6 Financiële transacties

- fiscal_years
- accounting_periods
- journal_entries
- journal_entry_lines
- open_items
- open_item_allocations
- tax_returns
- tax_return_lines
- posting_batches
- posting_requests
- posting_failures

### 7.7 Documenten

- documents
- document_versions
- document_links

### 7.8 Logging

- audit_logs
- authentication_logs
- integration_logs
- security_events

### 7.9 Unieke beperkingen

- administratiecode globaal uniek;
- rekeningnummer uniek per administratie;
- klantnummer uniek per administratie;
- leveranciersnummer uniek per administratie;
- factuurnummer uniek per administratie en nummerreeks;
- journaalboekingsnummer uniek per administratie, boekjaar en dagboek.

### 7.10 Indexstrategie

Minimaal indexeren:

- `administration_id`;
- foreign keys;
- status;
- boekingsdatum;
- factuurnummer;
- relatiecodes;
- open-item status;
- samengestelde tenant-indexen.

## 8. Beveiliging

- moderne wachtwoordhashing;
- rate limiting;
- parameterbinding;
- standaard output escaping;
- CSRF voor browsermutaties;
- tokenauthenticatie voor API;
- veilige uploads;
- TLS in productie;
- secrets buiten Git;
- versleutelde back-ups;
- dataminimalisatie;
- toegangslogging;
- aparte test- en productiedata.

## 9. Teststrategie

Testlagen:

- unit tests;
- integration tests;
- feature tests;
- architecture tests;
- security tests;
- accounting invariant tests;
- end-to-end tests.

Verplichte Accounting Engine-tests:

- debet = credit;
- lege journaalpost geweigerd;
- gesloten periode blokkeert boeking;
- administratiegrenzen kunnen niet worden overschreden;
- reversal verwijst naar oorspronkelijke boeking;
- boeking is idempotent waar vereist;
- afrondingsverschillen worden gecontroleerd verwerkt.

CI-kwaliteitspoort:

- Composer install
- npm ci
- PHPUnit
- PHPStan
- Pint
- frontend build
- architecture tests

## 10. Git- en releaseproces

Branches:

- `main`
- `feature/...`
- `bugfix/...`
- `release/...`

Werkwijze:

1. Werkmap schoon
2. `main` bijwerken
3. featurebranch maken
4. implementeren
5. lokaal testen
6. commit
7. push
8. pull request
9. CI groen
10. merge
11. lokaal `main` bijwerken
12. volgende taak starten

Versies volgen Semantic Versioning.

## 11. Ontwikkelfasen

1. Analyse en requirements — afgerond
2. Technische basis — afgerond
3. Administratie- en gebruikersbeheer
4. Relatiebeheer
5. Offertes en orders
6. Urenregistratie
7. Facturatie
8. Financiële administratie
9. Btw en rapportages
10. Bank
11. Beveiliging en audit
12. Testen en acceptatie
13. Migratie en ingebruikname
14. Onderhoud

## 12. Definition of Done

Een taak is pas gereed wanneer:

- code is geïmplementeerd;
- requirements aantoonbaar zijn gehaald;
- relevante tests bestaan;
- PHPUnit groen is;
- PHPStan groen is;
- Pint groen is;
- frontend build groen is waar relevant;
- autorisatie is gecontroleerd;
- tenant-isolatie is gecontroleerd;
- documentatie is bijgewerkt;
- pull request is beoordeeld;
- GitHub Actions groen is;
- wijziging is gemerged naar `main`.

## 13. Risico's

Belangrijkste risico's:

- tenant-lekken;
- boekhoudkundige inconsistenties;
- fiscale veroudering;
- te sterke Laravel-koppeling;
- onvoldoende herstelbaarheid;
- scopegroei.

Mitigatie:

- centrale context;
- policies;
- alle financiële mutaties via Posting Engine;
- immutable posted entries;
- actuele fiscale controle;
- framework-onafhankelijk Accounting Core;
- back-ups en restore-tests;
- strikte versie-1.0-scope.

## 14. Openstaande productbeslissingen

- exacte gebruikersrollen en standaardrechten;
- factuurlayout en huisstijl;
- rapportage-indeling;
- bankformaten of bankkoppelingen;
- bewaartermijnen;
- definitieve btw-scenario's;
- importvereisten;
- productie-RPO en RTO;
- goedkeuringsworkflows.

## 15. Huidige bouwstatus

Sprint 0: afgerond.

Gereed:

- Git en GitHub
- Docker
- Laravel 13
- PHP 8.5
- MySQL 8.4
- Redis
- Composer
- PHPUnit
- PHPStan
- Pint
- Vite
- Tailwind
- GitHub Actions

Sprint 1:

Actieve epic:

```text
Administration Foundation
```

Volgorde:

1. S1-001 Administration Persistence
2. S1-002 User–Administration Membership
3. S1-003 Administration Context
4. S1-004 Tenant Middleware
5. S1-005 Policies en permissions
6. S1-006 Administration Switcher
7. S1-007 Audit Foundation

## 16. Architectuurregels

1. Geen financiële boeking buiten de Posting Engine.
2. Geen Laravel-afhankelijkheid in Accounting Core.
3. Geen administratiegebonden data zonder `administration_id`.
4. Geen ongeautoriseerde query zonder centrale controle.
5. Geen wijziging van definitief geboekte journaalposten.
6. Geen nieuwe feature voordat de vorige pull request groen en gemerged is.
7. Geen secrets in Git.
8. Geen productiegegevens in tests.
9. Geen merge zonder geautomatiseerde kwaliteitspoort.
10. Code en tests zijn de primaire waarheid; documentatie volgt de code.

## 17. Eerstvolgende drie stappen

1. S1-001 afronden en mergen naar `main`.
2. S1-002 User–Administration Membership implementeren.
3. S1-003 Administration Context bouwen en tenant-isolatie via tests afdwingen.

**Einde DESIGN COMPLETE v1.0**
