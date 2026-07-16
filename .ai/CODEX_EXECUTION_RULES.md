# Codex Execution Rules

Deze regels gelden voor iedere Codex-opdracht in Finance Core Platform, tenzij een Story strengere regels oplegt.

## Veiligheid

- Werk uitsluitend in de actieve repository en vanuit de repository-root.
- Controleer branch en `git status --short` vóór wijzigingen.
- Stop bij onverwachte gewijzigde, gestagede of nieuwe bestanden.
- Gebruik nooit `git add .`, `git reset --hard`, `git clean` of `gh pr merge --admin`.
- Wijzig uitsluitend expliciet toegestane Story-paden.
- Stop bij de eerste echte inhoudelijke fout.

## Batchstrategie

- Neem drie tot vijf samenhangende Stories per batch.
- Geef iedere Story een eigen lokale commit.
- Push niets en maak geen PR tussen Stories.
- Voer na de laatste Story volledige batchvalidatie uit.
- Gebruik één push, één PR en één merge per batch.
- Vermeng documentatie- en infrastructuurwijzigingen niet ongemerkt met domeincode.

## Validatie

Voer gerichte tests, de volledige testsuite, `./vendor/bin/sail pint --test` en `git diff --check` uit. Pint-formattering mag automatisch met `./vendor/bin/sail pint` worden toegepast; herhaal daarna alle tests en controles.

## Git en GitHub

- Houd `main` stabiel en maak branches vanaf actuele `main`.
- Stage uitsluitend expliciete bestandspaden.
- Controleer `git diff --cached --name-only` en `git diff --cached --check` vóór een commit.
- Merge zonder `--admin`.
- Het ontbreken van GitHub-checks is geen fout.
- Bij lopende checks mag auto-merge worden ingeschakeld.

## Rapportage

Rapporteer compact: afwijkingen, testtotalen, Pint-resultaat, commit-hash, PR-URL, mergestatus en uiteindelijke branch en Git-status.
