# Architecture Decisions

## ADR-0011 – Configureerbare decimale precisie

**Status:** Accepted

### Besluit

- Administraties ondersteunen een configureerbare precisie van 0 tot en met 8 decimalen.
- De productstandaard is 2 decimalen en wordt expliciet door de aanroeper aangeleverd.
- Aanvullend beleid voor wijziging van de precisie nadat financiële transacties bestaan, volgt later.

### Consequenties

- Decimale precisie is een afzonderlijk immutable value object.
- Precisie bepaalt niet het afrondingsbeleid; afronding wordt afzonderlijk ontworpen.

## ADR-0012 – Production runtime op single-host Docker Compose

**Status:** Accepted

### Aanleiding

Finance Core heeft naast Laravel Web een duurzame MySQL-database, private artifacts,
een databasequeue, een bewaakte documentworker, scheduling en een reproduceerbare
Node/Puppeteer/Chrome-runtime nodig. Sail definieert geen production lifecycle,
backups, TLS, supervision of durable artifactstorage.

### Besluit

- V1 gebruikt één operator-owned Linux-host met een afzonderlijk production Docker
  Compose-contract. Dit target is portable tussen on-premise hardware en hosted IaaS
  en maakt geen commerciële hostingkeuze.
- Eén immutable application image bevat PHP/Laravel, Vite-assets en de gepinde
  Node/Puppeteer/Chrome-runtime. Web, worker en scheduler gebruiken dezelfde release.
- Afzonderlijke services verzorgen reverse proxy/Web, één Sales-documentworker en één
  scheduler. Compose restart policies bewaken crash en hostreboot; deployment herstart
  workers expliciet.
- De v1-queue blijft Laravel `database`, queue `sales-document-delivery`. Redis is geen
  productiondependency zolang geen concrete capability dit vereist.
- MySQL en private `sales_documents` staan buiten disposable applicationcontainers op
  afzonderlijke durable volumes. Database en artifacts vereisen samenhangende, geteste
  backup en restore. Objectstorage is een later schaalbaar alternatief.
- Het workercontract is:

  ```text
  php artisan queue:work database --queue=sales-document-delivery --sleep=3 --tries=1 --timeout=75 --max-time=3600 --memory=512
  ```

  Businessretry blijft outbox-owned. `DB_QUEUE_RETRY_AFTER` is groter dan de timeout
  (minimaal 90 seconden). Deployment gebruikt graceful `php artisan queue:restart`.
- De scheduler draait bewaakt met `php artisan schedule:work`.
- W4E-003A implementeert een installation-level heartbeat: een bestaand containerproces
  bewijst niet dat Laravel, queue en database functioneel worden bediend.
- TLS en DNS behoren tot reverse proxy/infrastructure. Secrets worden runtime
  geïnjecteerd en nooit in image, Composebestand of Git opgeslagen.
- Zero-downtime is geen v1-eis. Migrations zijn forward-only; rollback gebruikt nooit
  automatisch destructieve `down`-migrations.

### Alternatieven

- Linux + systemd is geschikt maar reproduceert browser/system-libraryruntime minder
  sterk en verdeelt releasebeheer over de host.
- Supervisor beheert PHP-workers, maar vormt geen compleet Web/browser/storage/release-
  contract en introduceert een tweede procesmodel.
- Kubernetes is later schaalbaar voor multi-host/high-availability, maar nu overkill.

### Consequenties

- W4E-003A mag worker-, heartbeat-, readiness- en recoverycontracts tegen dit target
  implementeren. W4E-004 blijft geblokkeerd totdat W4E-003A gereed is.
- Een production image/Composebestand, backupautomatisering en hostprovisioning zijn
  deploymentdeliverables na deze designstory; Sail wordt niet hergebruikt.
