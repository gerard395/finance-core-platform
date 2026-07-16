# Batch – <Naam>

## Branch
`<type>/<batch-branch>` vanaf actuele `main`.

## Stories
1. `<STORY-ID> – <Titel>`
2. `<STORY-ID> – <Titel>`
3. `<STORY-ID> – <Titel>`

## Verwachte commits
- `<STORY-ID> <Commitbericht>`

## Gerichte testopdrachten
```bash
bin/validate-domain <Domain>
```

## Volledige validatie
```bash
bin/validate-batch <Domain>
```

## PR-titel
`<Titel>`

## PR-beschrijving
- Doel, Stories, commits, besluiten, testresultaten en buiten-scope.

## Mergebeleid
- Eén push en één PR na batchvalidatie.
- Normale merge zonder `--admin`.
- Auto-merge bij lopende checks; geen checks is geen fout.
