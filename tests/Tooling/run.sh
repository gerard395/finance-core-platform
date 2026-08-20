#!/usr/bin/env bash
set -Eeuo pipefail

project_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd -P)"
tests_run=0
fail() { printf 'FAIL: %s\n' "$*" >&2; exit 1; }
pass() { tests_run=$((tests_run + 1)); printf 'PASS: %s\n' "$1"; }
expect_failure() { local label="$1"; shift; if "$@" >/dev/null 2>&1; then fail "$label unexpectedly succeeded"; fi; pass "$label"; }
new_repo() {
    local repo; repo="$(mktemp -d)"
    git -C "$repo" init -q -b main
    git -C "$repo" config user.email tooling-tests@example.invalid
    git -C "$repo" config user.name 'Tooling Tests'
    mkdir -p "$repo/bin"
    cp "$project_root/bin/commit-story" "$repo/bin/commit-story"
    chmod +x "$repo/bin/commit-story"
    printf 'base\n' > "$repo/base.txt"
    git -C "$repo" add base.txt bin/commit-story
    git -C "$repo" commit -qm initial
    printf '%s\n' "$repo"
}

repo="$(new_repo)"; git -C "$repo" switch -qc feature/test
expect_failure 'commit-story rejects missing --file' bash -c "cd '$repo' && bin/commit-story --story T-001 --message test"

repo="$(new_repo)"; printf 'change\n' >> "$repo/base.txt"
expect_failure 'commit-story rejects main' bash -c "cd '$repo' && bin/commit-story --story T-001 --message test --file base.txt"

repo="$(new_repo)"; git -C "$repo" switch -qc feature/test
printf 'foreign\n' > "$repo/foreign.txt"; git -C "$repo" add foreign.txt; printf 'change\n' >> "$repo/base.txt"
expect_failure 'commit-story rejects foreign staged file' bash -c "cd '$repo' && bin/commit-story --story T-001 --message test --file base.txt"

repo="$(new_repo)"; git -C "$repo" switch -qc feature/test
printf 'change\n' >> "$repo/base.txt"; printf 'remaining\n' > "$repo/remaining.txt"
output="$(cd "$repo" && bin/commit-story --story T-001 --message 'T-001 test commit' --file base.txt)"
[[ "$(git -C "$repo" show --pretty='' --name-only HEAD)" == base.txt ]] || fail 'commit contained a file outside explicit scope'
[[ "$(git -C "$repo" status --short)" == '?? remaining.txt' ]] || fail 'other unstaged file was changed'
[[ "$output" =~ Story\ commit\ created:\ [0-9a-f]{40} ]] || fail 'commit hash was not shown'
pass 'commit-story scopes staging and preserves other changes'

repo="$(new_repo)"; git -C "$repo" switch -qc feature/test; printf 'trailing whitespace   \n' >> "$repo/base.txt"
expect_failure 'commit-story blocks diff-check failure' bash -c "cd '$repo' && bin/commit-story --story T-001 --message test --file base.txt"

validation_repo="$(mktemp -d)"; git -C "$validation_repo" init -q -b feature/test
git -C "$validation_repo" config user.email tooling-tests@example.invalid; git -C "$validation_repo" config user.name 'Tooling Tests'
mkdir -p "$validation_repo/bin" "$validation_repo/vendor/bin"
cp "$project_root/bin/validate-batch" "$validation_repo/bin/validate-batch"; chmod +x "$validation_repo/bin/validate-batch"
printf '#!/usr/bin/env bash\nexit 0\n' > "$validation_repo/bin/check-environment"
printf '#!/usr/bin/env bash\nprintf "domain:%%s\\n" "$1" >> calls.log\n' > "$validation_repo/bin/validate-domain"
printf '#!/usr/bin/env bash\nprintf "sail:%%s\\n" "$*" >> calls.log\n' > "$validation_repo/vendor/bin/sail"
chmod +x "$validation_repo/bin/check-environment" "$validation_repo/bin/validate-domain" "$validation_repo/vendor/bin/sail"
printf 'base\n' > "$validation_repo/base.txt"; git -C "$validation_repo" add .; git -C "$validation_repo" commit -qm initial
(cd "$validation_repo" && bin/validate-batch Accounting >/dev/null)
grep -qx 'domain:Accounting' "$validation_repo/calls.log" || fail 'Domain mode did not invoke validate-domain'
pass 'validate-batch Domain mode remains compatible'
: > "$validation_repo/calls.log"; (cd "$validation_repo" && bin/validate-batch --all >/dev/null)
grep -qx 'sail:artisan test' "$validation_repo/calls.log" || fail '--all omitted full suite'
grep -qx 'sail:pint --test' "$validation_repo/calls.log" || fail '--all omitted Pint'
if grep -q '^domain:' "$validation_repo/calls.log"; then fail '--all invoked Domain validation'; fi
pass 'validate-batch --all avoids Domain path'
expect_failure 'validate-batch rejects invalid arguments' bash -c "cd '$validation_repo' && bin/validate-batch --invalid"

expect_failure 'finish-batch rejects --domain with --all' "$project_root/bin/finish-batch" --domain Accounting --all --base main --title test --body-file /dev/null
expect_failure 'finish-batch rejects missing mode' "$project_root/bin/finish-batch" --base main --title test --body-file /dev/null

finish_repo="$(mktemp -d)"; git -C "$finish_repo" init -q -b main
git -C "$finish_repo" config user.email tooling-tests@example.invalid; git -C "$finish_repo" config user.name 'Tooling Tests'
mkdir -p "$finish_repo/bin" "$finish_repo/stubs"; cp "$project_root/bin/finish-batch" "$finish_repo/bin/finish-batch"; chmod +x "$finish_repo/bin/finish-batch"
printf '#!/usr/bin/env bash\nprintf "validate:%%s\\n" "$*" >> calls.log\n' > "$finish_repo/bin/validate-batch"; chmod +x "$finish_repo/bin/validate-batch"
printf 'base\n' > "$finish_repo/base.txt"; printf 'body\n' > "$finish_repo/pr-body.md"; : > "$finish_repo/calls.log"
git -C "$finish_repo" add .; git -C "$finish_repo" commit -qm initial
real_git="$(command -v git)"
printf '#!/usr/bin/env bash\nif [[ "$1" == push ]]; then exit 0; fi\nexec %q "$@"\n' "$real_git" > "$finish_repo/stubs/git"
printf '#!/usr/bin/env bash\nif [[ "$1 $2" == "pr list" ]]; then printf "https://example.invalid/pr/1\\n"; exit 0; fi\nexit 1\n' > "$finish_repo/stubs/gh"
chmod +x "$finish_repo/stubs/git" "$finish_repo/stubs/gh"
git -C "$finish_repo" add stubs; git -C "$finish_repo" commit -qm stubs; git -C "$finish_repo" switch -qc feature/test
PATH="$finish_repo/stubs:$PATH" bash -c "cd '$finish_repo' && bin/finish-batch --all --base main --title test --body-file pr-body.md --no-merge >/dev/null"
grep -qx 'validate:--all' "$finish_repo/calls.log" || fail 'finish-batch did not pass --all through'
pass 'finish-batch passes --all without GitHub mutation'
: > "$finish_repo/calls.log"
PATH="$finish_repo/stubs:$PATH" bash -c "cd '$finish_repo' && bin/finish-batch --domain Accounting --base main --title test --body-file pr-body.md --no-merge >/dev/null"
grep -qx 'validate:Accounting' "$finish_repo/calls.log" || fail 'finish-batch changed Domain invocation'
pass 'finish-batch Domain mode remains compatible'

printf 'Tooling tests: %d passed\n' "$tests_run"
