# <BATCH-ID> – <Naam>

## Doel
<Samenhangend batchresultaat.>

## Branch
- Basis: `main`
- Batchbranch: `<type>/<naam>`

## Stories en commits
| Volgorde | Story | Doel | Verwachte commit |
| --- | --- | --- | --- |
| 1 | `<STORY-ID>` | `<doel>` | `<commitbericht>` |

## Toegestane scope
- `<pad of patroon>`

## Gerichte validatie
`bin/validate-domain <Domain>`

## Batchvalidatie
`bin/validate-batch <Domain>`

## Capabilityreview
- Resultaat: `<pad>`
- Blokkades/ADR's: `<geen of lijst>`

## Pull request
- Titel: `<titel>`
- Body: `<bestand>`
- Basis: `main`

## Mergebeleid
- Normale merge zonder `--admin`.
- Auto-merge alleen bij lopende checks.
- Geen push, PR of merge tussen Stories.

## Resultaat
- Tests/Pint: `<resultaat>`
- Commits: `<hashes>`
- PR/merge: `<URL en status>`
