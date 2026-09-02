# Development Workflow

De bindende veiligheidsregels staan in [Codex Execution Rules](../.ai/CODEX_EXECUTION_RULES.md); domeinwerk volgt ook de [Architecture Guard](../.ai/ARCHITECTURE_GUARD.md).

## 1. Sessie starten

Werk vanuit de repository-root en controleer `git branch --show-current` en `git status --short`. Stop bij afwijkingen.

## 2. Docker en Sail controleren

```bash
bin/check-environment
```

Start Sail zo nodig met `./vendor/bin/sail up -d`.

## 3. Actuele main ophalen

```bash
git switch main
git pull --ff-only origin main
```

## 4. Batchbranch maken

```bash
# Accounting
git switch -c feature/A5-accounting-foundation

# Sales
git switch -c feature/S5-sales-settlement
```

## 5. Story implementeren

Gebruik `.ai/templates/DOMAIN_STORY.md` of `.ai/templates/DOCUMENTATION_STORY.md`. Wijzig alleen expliciete Story-paden.

## 6. Gerichte en volledige validatie

```bash
bin/validate-domain Accounting
bin/validate-domain Sales
```

Voer na Pint-formattering alle validaties opnieuw uit.

### Databaseveiligheid bij tests

Gebruik voor tests uitsluitend:

```bash
./vendor/bin/sail artisan test
```

`--env=testing` selecteert alleen de Laravel-environment en injecteert niet automatisch de
databasevariabelen uit `phpunit.xml`. Een los commando zoals `artisan migrate:fresh
--env=testing` kan zonder aanvullende bescherming daarom nog steeds de developmentdatabase
uit `.env` selecteren.

Directe destructieve Artisancommands (`migrate:fresh`, `migrate:refresh`, `migrate:reset`,
`migrate:rollback` en `db:wipe`) worden centraal geblokkeerd, tenzij environment,
geconfigureerde database én daadwerkelijk verbonden database exact `testing` zijn. Een
onbekende database of mismatch faalt gesloten. Normale `migrate` en `migrate:status` blijven
beschikbaar voor development.

## 7. Lokale Story-commit

```bash
git add path/to/explicit-file.php tests/path/to/explicit-test.php .ai/stories/STORY-ID.md
git diff --cached --check
git diff --cached --name-only
git commit -m "STORY-ID Add focused capability behavior"
```

Gebruik nooit `git add .`.

## 8. Volgende Story

Controleer een schone werkmap en implementeer de volgende Story op dezelfde batchbranch. Push niets tussen Stories.

## 9. Capabilityreview

Gebruik `.ai/templates/CAPABILITY_REVIEW.md` voor ontbrekende onderdelen, doublures, statusmachines, architectuur, geldrepresentatie, afhankelijkheden, tests, ADR's en technische schuld.

## 10. Batch afronden

```bash
bin/validate-batch Accounting

bin/finish-batch \
  --domain Accounting \
  --base main \
  --title "Add accounting foundation" \
  --body-file /pad/naar/pr-body.md
```

Sales-voorbeeld zonder merge:

```bash
bin/finish-batch \
  --domain Sales \
  --base main \
  --title "Add sales settlement" \
  --body-file /pad/naar/pr-body.md \
  --no-merge
```

Het script gebruikt nooit `--admin`. Bij lopende checks probeert het auto-merge; ontbreken van checks is geen fout.

## 11. Terug naar main

Na directe merge werkt `finish-batch` de lokale basisbranch bij. Controleer tot slot branch, `git status --short` en `git log --oneline -8`.
