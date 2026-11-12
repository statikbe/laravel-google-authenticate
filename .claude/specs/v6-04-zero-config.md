---
key: v6-04-zero-config
status: done
mode: standalone
size: full
base: feature/v6
branch: feature/v6-04-zero-config
runner: (none)
wave: 1
---

## Goal
Make install three steps (require, env vars, migrate): `services.google` defaults that survive `config:cache`, an auto-loaded idempotent migration, and the service bound as a singleton for the facade.

## Context
- Plan sections 1, 5 (the `services.google` note), 2 (migration row).
- `src/GoogleAuthenticateServiceProvider.php`: `boot()`, `register()`, `bootForConsole()` with publish tags `google-lang`, `google-config`, migrations.
- Migration today: `database/migrations/add_google_provider_to_user_table.php.stub`, publish-only. Audited projects already have `provider`/`provider_id` columns (published 5.x migration or own consolidated migration).
- `tests/TestCase.php` creates the `users` table itself and aliases `App\Models\User` (02 makes the alias unnecessary).

## Acceptance criteria
AC1. With no `services.google` set in the app, `config('services.google')` equals `client_id` = `env('GOOGLE_CLIENT_ID')`, `client_secret` = `env('GOOGLE_CLIENT_SECRET')`, `redirect` = `/login/google/callback`, read from a package file `config/google-services.php` merged in `register()`. An app that sets `services.google` keeps its own values, key by key (test both).
AC2. The migration is an anonymous class at `database/migrations/add_google_provider_to_users_table.php` (undated), loaded with `loadMigrationsFrom` and published with the current date under tag `google-migrations`. It adds `provider` and `provider_id` only when missing (`Schema::hasColumn`), so running it on a table that has them is a no-op (test: run twice / on pre-existing columns).
AC2b. The migration makes `users.password` nullable when it exists and is not nullable yet (`Schema::getColumns` + `->nullable()->change()`); no-op otherwise. `down()` is a no-op: it never drops `provider`/`provider_id` (they may predate this migration) and never reverts `password`.
AC3. `GoogleAuthService` is bound as a singleton (test: two resolves return the same instance).
AC4. `tests/TestCase.php` drops the `App\Models\User` alias if 02 is merged and the suite stays green; otherwise leave it and note.
AC5. **Absence:** no `.stub` migration remains.

## Out of scope / does not touch
Gate, listener registration (07, 06); D9 guard (05); config key set (01 owns it).

## Risks & assumptions
| Risk | Provisional decision | Cost if wrong |
|---|---|---|
| `mergeConfigFrom` only merges top-level keys | merge `services.google` manually: package defaults overlaid by app values | wrong precedence = broken login; covered by AC1 tests |
| Fixed migration timestamp sorts before app's users table | Migration uses the configured user model's table and fails loudly when it is missing (review F5, supersedes the earlier no-op) | a missing table must not be recorded as run — tested |

## Tasks
- [x] 1. Tests AC1 (both cases), AC3 in `tests/Feature/ServiceProviderTest.php` — AC1, AC3
- [x] 2. `config/google-services.php` + merge in `register()`; singleton binding — AC1, AC3
- [x] 3. Migration rewrite + idempotency tests; remove stub; publish tag — AC2, AC5
- [x] 4. TestCase alias cleanup — AC4

## Gate Ledger
| Gate | Command | Result | Evidence |
|---|---|---|---|
| Style | `vendor/bin/pint --test` | PASS | exit 0 on feature/v6 after merge 4cdbe3e |
| Static | `vendor/bin/phpstan analyse --no-progress` | PASS | `[OK] No errors` |
| Tests | `vendor/bin/pest` | PASS | 90 passed (239 assertions) on feature/v6 |

## Change Log
| Date | Change | Why | Approved by |
|---|---|---|---|
| 2026-11-12 | AC2b added: password nullable; `down()` no-op | round-1 review: fresh apps have NOT NULL password (Google users have none); rollback would wipe pre-existing provider columns | orchestrator ruling |
| 2026-11-12 | Migration file undated (add_google_provider_to_users_table.php), still auto-loaded; publishing names the copy with the current date | user wants published copy dated today; migration is hard required so stays auto-loaded | Kristof |

## Rulings
- AC4 (TestCase alias) moved to v6-07, which owns TestCase in wave 2.
- User approved soft-reset of unpushed 6958e1d to work within guard-files.sh; final commit d499480.
| 2026-11-12 | Migration uses configured model table, fails loudly when missing; password length kept; unique provider_id index when safe | final review F5–F7 | user (approved fix list) |
