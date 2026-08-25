# PROJECT-GAP-001 – Post-W4D Roadmap & Sales Gap Review

## 1. Current product baseline

De productbaseline op main HEAD `66958a7031320872ca7d1777061a61e2c00af1b9` bevat een tenant-veilige Sales-keten van Quotation via Order naar SalesInvoice, inclusief SalesCreditInvoice, internationale fiscale snapshots, transactionele posting en beheerde Sales-postingconfiguratie. W4, W4A, W4B en W4D zijn afgerond. Deze review wijzigt geen productcode, schema of data.

## 2. Capability matrix

| Capability | Feitelijke status | Belangrijkste grens |
|---|---|---|
| Administration | Domain/persistence en basis-/fiscale settings aanwezig | Organisation bevat juridische velden, adres en bankgegevens, maar deze zijn niet als complete document-sender settings productmatig onderhoudbaar |
| Identity & Security | Actieve memberships, rollen en permissions ondersteunen bestaande webmodules | Geen delivery-specifieke permission of auditpolicy |
| Relations | Web/persistence compleet voor Relation, Customer/Supplier, Contact, Address en BankAccount | Contact heeft alleen naam, nullable e-mail/telefoon en status; geen purpose, primary/preferred of document-recipientbeleid |
| Sales | Quotation, Order, SalesInvoice en SalesCreditInvoice inclusief W4A/W4B-conversies en posting operationeel | Geen documentrendering, distributie, deliveryhistorie, retries of deliverystatus |
| Accounting | PostingEngine, Journal/LedgerAccount/OpenItem en Sales-configuratie operationeel | Purchase-configuratie en algemene boekings-UI deferred |
| Fiscal | Immutable treatment-, VAT-return- en ICP-classificaties en TaxPostings aanwezig | Geen officiële returnperioden, rubriekengine, rounding/FX-policy of aangifteworkflow |
| Purchasing | Alleen eerste Domain-iteratie plus posting/fiscale Application-contracten | Geen persistence, readmodels, webflow, numbering, configuratie of volledige operationele keten |
| Documents | Gepland | Geen aggregate/persistence/rendering/archive/delivery capability |
| Reporting | Frameworkonafhankelijke calculators, waaronder VAT Overview, aanwezig | Geen Application/Infrastructure-selectie, Web-presentatie, officiële VAT return of ICP-report |

Conclusie: de grootste direct klantzichtbare kloof in de reeds werkende Sales-keten is duurzame documentoutput en aflevering. Purchasing en officiële fiscale reporting zijn substantieel grotere, afzonderlijke productbatches.

## 3. Sales journey gaps

De kernflow is aanwezig en veilig:

```text
Draft Quotation → Sent → Accepted → Draft Order
→ Confirmed Order → Draft SalesInvoice → Finalized → Posted
→ Draft SalesCreditInvoice → Finalized → Posted
```

`SendQuotation::execute()` roept via `QuotationMutationService` uitsluitend `Quotation::send()` aan en bewaart de status. De webactie **Offerte verzenden** betekent daarom exact: markeer een geldige Draft-offerte als `Sent`. Zij maakt geen PDF, selecteert geen ontvanger, verstuurt geen e-mail, registreert geen afleverpoging en creëert geen Order. De naam suggereert externe aflevering terwijl de feitelijke semantiek alleen lifecycle is; dit is een product/UX mismatch, geen onduidelijke implementatie.

Overige belangrijke Sales-gaps zijn refund/customer-credit payout en toepassing op toekomstige facturen, allocation reversal/order reopen na finalized cancel of credit, Draft optimistic locking, centrale Administration-bootstrap, delivery notes, recurring invoices, mutation-auditlogging, TaxCode effective dating en een expliciet roundingbeleid. Geen daarvan blokkeert het ontwerpen van documentdelivery.

## 4. Document/PDF gap

Er is geen PDF-library, renderer, render-use-case, template, documentbestand, opslagrecord, content hash, documentversie, archive record of downloadroute. Capability Documents is slechts gepland. Quotation heeft een customersnapshot en regels, maar geen adres-, fiscale of betalingssnapshot. SalesInvoice en SalesCreditInvoice hebben de sterkste documentbasis: nummer, datums, valuta, immutable customer- en invoice-addresscontext, fiscale partycontext en treatment/taxregels. Zij missen nog een formeel rendercontract, immutable rendered-output policy, issuer/paymentpresentatie en duurzame outputmetadata.

PDF capability: **nee**. Invoice document readiness: **gedeeltelijk/hoog voor renderinginput, niet aflevergereed**. Credit document readiness: **gedeeltelijk/hoog en source-consistent, niet aflevergereed**. Quotation readiness: **gedeeltelijk**, omdat documentadres, geldigheids-/voorwaardenpresentatie, recipient en issuercontext nog expliciet moeten worden vastgesteld.

## 5. Email/delivery gap

Laravel/Symfony biedt generieke mailtransportconfiguratie en een globale environment-`from`, maar de applicatie bevat geen Sales Mailable/Notification, deliveryport, queue/retrypolicy, providergrens, attachmentflow, delivery-attempt persistence, failurestate, idempotencykey of audittrail. Er is ook geen bewijs van succesvolle externe aflevering. Email delivery capability: **nee**.

Een toekomstige deliveryactie mag `Sent` niet als transportbewijs behandelen. Status, rendered artifact en delivery attempt/result moeten afzonderlijke waarheden zijn. De batch moet expliciet beslissen of een succesvolle aflevering de Quotation-overgang veroorzaakt, of dat de bestaande status “marked sent” blijft en delivery apart wordt vastgelegd. Mislukte aflevering mag nooit als succesvolle delivery worden gepresenteerd.

## 6. Recipient/sender gap

Recipient readiness is **onvoldoende voor automatische keuze**. Relation Contacts leveren syntactisch bruikbare e-mailadressen en active/inactive-status, maar hebben geen functie/purpose, primary/preferred-vlag of documenttypevoorkeur. Duplicate e-mails zijn toegestaan. Een expliciete actieve contactselectie per send-command is veilig; automatische selectie of “eerste actieve contact” is niet toegestaan zonder productbeleid. Invoice-addresssnapshots zijn postadressen en geen e-mailrecipient.

Sender/settings readiness is **onvoldoende voor administration-scoped levering**. `config/mail.php` configureert technisch transport en een globale environment-afzender. Administration settings beheren naam, omschrijving, VAT-ID, jurisdictie en Sales-postingmapping. Organisation kan juridische naam/vorm, KvK, VAT, vrij primary-addressveld, IBAN en BIC dragen, maar er is geen complete product-UI/readinesscontract voor documentissuer, afzendernaam/e-mail, reply-to, documenttaal, betaalinstructie, logo of voorwaarden. Globale transportsettings mogen niet stilzwijgend als tenant-owned businessafzender gelden.

## 7. Purchase gap

Purchasing heeft frameworkonafhankelijke PurchaseInvoice/PurchaseCreditInvoice-aggregates en Application-contracten om fiscale postingrequests te orkestreren. Er zijn geen Purchasing repositories, migrations, durable numbering, readmodels, HTTP-routes, views, permissions-handhaving, masterdata/configurationflow of complete post/open-item-webketen. Purchasing is daardoor een domain/application foundation en geen operationele productmodule. Dit is een belangrijke toekomstige batch, maar niet de kleinste afsluiting van de huidige klantzichtbare Sales journey.

## 8. VAT/ICP parking decision

ICP is technisch **mogelijk als volgende ontwerp/implementatierichting voor de ondersteunde facts**, omdat immutable TaxPostings expliciete ICP-classificatie, customer VAT-ID, jurisdiction, goods/services-betekenis, postingdatum en Original/Reversal-truth bewaren. Er bestaat echter nog geen ICP-aggregator, periodeworkflow, correctie-/reconciliatie-UI of export.

Een volledige officiële Nederlandse VAT return is technisch **nog niet mogelijk**. De code heeft VAT Overview-bronwaarheid en classificaties, maar mist aangifteperioden/statusworkflow, rubriekmapping, reporting rounding, reconciliatie/auditreadmodel, export/indiening en voor non-EUR een expliciete historische FX→EUR-policy. Fiscal-unit context en TaxCode effective dating zijn eveneens vervolg.

Productbesluit: Dutch VAT & ICP Reporting blijft nu geparkeerd. De bronfacts zijn waardevol en voldoende om later verantwoord voort te bouwen, maar documentoutput en delivery sluiten eerst de reeds operationele offerte-/factuurjourney voor gebruikers en klanten. Parkeren betekent geen herclassificatie of verwijdering van fiscale waarheid.

## 9. Dependency graph

```text
Relations Contacts ──┐
                     ├─> recipient selection/readiness ─┐
Administration +     │                                  │
Organisation data ──┴─> issuer/sender readiness ───────┤
                                                        ├─> immutable render input
Sales snapshots + Fiscal wording ──────────────────────┤        │
                                                                 ├─> PDF artifact/storage
Technical mail transport ─> delivery port/policy ───────────────┤        │
                                                                          └─> delivery attempts, audit, retry and Web actions

TaxPostings/classifications ─> reporting periods/rounding/FX ─> VAT/ICP reporting (parked)
Purchasing Domain/Application ─> persistence/numbering/config/UI ─> Purchasing Web (deferred)
```

## 10. Recommended next batch

Exact één aanbevolen volgende batch: **W4E – Sales Document Delivery (PDF & Email)**.

Deze batch sluit de grootste zichtbare end-to-end Sales-kloof, hergebruikt stabiele snapshots en bestaande tenant-/permissionpatronen, en vereist geen wijziging aan financiële waarheid. De batch begint met expliciete semantics/readiness en bouwt daarna rendering en delivery; zij mag geen generieke Documents-capability of marketingmailplatform vooruit ontwerpen.

## 11. Proposed story split

1. **W4E-000 – Sales document delivery design & lifecycle semantics**: bepaal artifact-/deliverytruth, relatie tot Quotation `Sent`, ondersteunde documentstatussen, idempotency, failure/retry en permissiongrenzen.
2. **W4E-001 – Issuer, sender & recipient readiness contracts**: administration-scoped issuer/sender/readiness en expliciete actieve Contact-recipientselectie; geen heuristische primary.
3. **W4E-002 – Immutable Sales document render models**: typed renderinput voor Quotation, SalesInvoice en SalesCreditInvoice, inclusief fiscale wording en ontbrekende-field failures.
4. **W4E-003 – PDF rendering, artifact persistence & secure download**: deterministische renderer, content metadata/hash, tenantownership, opslagpolicy en geautoriseerde download.
5. **W4E-004 – Email delivery contracts & durable attempts**: transportport, attachment, idempotente attempts, succes/falen, retrypolicy en audittrace zonder statusclaim bij falen.
6. **W4E-005 – Quotation delivery Web flow**: expliciete recipient, preview/download/send en correcte `Sent`-semantiek volgens W4E-000.
7. **W4E-006 – Invoice & credit delivery Web flow**: alleen geschikte definitieve documenten, preview/download/send en onafhankelijke deliveryhistorie.
8. **W4E-007 – Review, regression & roadmap closure**: tenantisolatie, authorization, snapshots, failure/retry, attachmentintegriteit, accessibility en brede regressie.

## 12. Risks

- Een ongewijzigd label **Offerte verzenden** kan status-only gedrag blijven overbeloven; W4E-000 moet vóór deliveryimplementatie het contract vastleggen.
- Automatische recipientselectie zou niet-bestaand masterdatabeleid verzinnen.
- Globale environment-mailconfiguratie kan tenant-owned senderidentity verwarren met technisch transport.
- Regenereren uit later gewijzigde masterdata kan historische documenten wijzigen; renderinput/artifactpolicy moet immutable en auditbaar zijn.
- E-mailprovideracceptatie is geen bewijs van ontvangst; resultaatstaten moeten precies benoemd worden.
- PDF/archivering kan onbedoeld een te brede generieke Documents-capability worden; W4E blijft beperkt tot Sales-documentoutput.

## 13. Deferred capabilities

- Dutch VAT & ICP Reporting, inclusief perioden, rubrieken, rounding, reconciliatie, FX, export en latere elektronische indiening.
- Purchasing persistence/Web/postingconfiguratie en de operationele Purchase-keten.
- Refund/customer-credit payout en toepassing op toekomstige facturen.
- Allocation reversal/order reopen, recurring invoices en delivery notes.
- Centrale Administration-bootstrap, Draft optimistic locking en cross-cutting mutation audit.
- VIES, fiscale-eenheidcontext, TaxCode effective dating en automatische tax decision support.
- Een generieke Documents/attachments/archive capability buiten de minimale Sales-deliverygrens.
