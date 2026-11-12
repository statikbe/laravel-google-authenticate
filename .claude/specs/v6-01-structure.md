---
key: v6-01-structure
status: done
mode: standalone
size: full
base: feature/v6
branch: feature/v6-01-structure
runner: (none)
wave: 0
---

## Goal
Move the package to the 6.0 layout and requirements without changing behaviour, so every later issue edits files at their final paths. Behaviour changes belong to later specs; this one is pure structure.

## Context
- Plan sections 2 (file table), 5 (config), D1, D2, D6.
- Today: `src/Services/GoogleAuthService.php`, `src/GoogleAuthenticateController.php`, `src/config/google-authenticate.php`, `src/routes.php`, `src/Traits/HasGoogleAuth.php`, `resources/views/auth/*`, `database/migrations/add_google_provider_to_user_table.php.stub`.
- Tests: `tests/TestCase.php`, `tests/Feature/{GoogleAuthServiceTest,GoogleCallbackTest,GoogleRedirectTest}.php`, `tests/Support/User.php`. TestCase aliases `App\Models\User` because the service still returns it (02 removes that need).
- Service provider loads routes/config/lang and publishes `google-views`, `google-lang`, `google-config`, migrations.

## Acceptance criteria
AC1. `composer.json`: `illuminate/support` and `illuminate/routing` `^11.0|^12.0|^13.0`; `"php": "^8.2"`; testbench `^9.0|^10.0|^11.0`; `spatie/laravel-permission` `^6.0|^7.0` in `suggest` and `require-dev`; `doctrine/dbal` suggest removed. `composer validate --strict` passes.
AC2. Files live at: `src/GoogleAuthService.php`, `src/Http/GoogleAuthController.php`, `config/google-authenticate.php`, `routes/web.php`. Old paths no longer exist.
AC3. **Absence:** `src/Traits/HasGoogleAuth.php`, `resources/views/`, and the `google-views` publish tag do not exist. A test asserts `class_exists('Statikbe\GoogleAuthenticate\Traits\HasGoogleAuth')` is false.
AC4. `config/google-authenticate.php` contains exactly the key set of plan section 5 (including `domains.allow_any`, `super_admin_role`, `super_admin_listener` = `GrantSuperAdminRole::class` imported by `use`, no env var for it, `super_admin_gate`). The listener class does not exist yet: reference it as a string-safe `::class` constant (no autoload at config load).
AC5. Route names `google.auth.login` and `google.auth.callback` and their URIs are unchanged (test).
AC6. Full existing suite passes with only namespace/path updates in tests. No assertion is weakened.
AC7. PHPStan via `larastan/larastan` (require-dev, version matching the Laravel range) with `phpstan.neon.dist`: level 6, paths `src`, `config`, `routes`, `database`, larastan extension included. `composer analyse` script runs `vendor/bin/phpstan analyse --no-progress`. It passes **without a baseline**: fix real errors in `src/`; an error that only a later spec's rewrite removes may be fixed minimally or ignored with a targeted `ignoreErrors` entry carrying a comment naming that spec.

## Out of scope / does not touch
Behaviour of service, controller, provider registration logic beyond path updates; migration auto-loading (04); `guest` middleware (03); docs (08).

## Risks & assumptions
| Risk | Provisional decision | Cost if wrong |
|---|---|---|
| `::class` on a missing class in config | `::class` resolves at compile time, no autoload; safe | none |
| `src/Exceptions`, `src/Events` paths | unchanged | none |

## Tasks
- [x] 1. composer.json per AC1; `composer update` locally to check it resolves — test: `composer validate --strict` — AC1
- [x] 2. `git mv` service, controller, config, routes to AC2 paths; update namespaces, provider paths and test imports — test: existing suite green — AC2, AC6
- [x] 3. Delete trait, `resources/views`, `google-views` publish block; add absence test in `tests/Feature/StructureTest.php` — AC3
- [x] 4. Rewrite `config/google-authenticate.php` to plan section 5 (keep existing comments style) — test: `StructureTest` asserts key list — AC4
- [x] 5. Route name/URI test in `StructureTest` — AC5
- [x] 6. Larastan + `phpstan.neon.dist` + `analyse` script; make it pass — test: `vendor/bin/phpstan analyse --no-progress` exit 0 — AC7

## Gate Ledger
| Gate | Command | Result | Evidence |
|---|---|---|---|
| Style | `vendor/bin/pint --test` | PASS | exit 0 (orchestrator run, ba6ecff) |
| Static | `vendor/bin/phpstan analyse --no-progress` | PASS | `ok`, level 6, no baseline; targeted ignores for v6-02/03/06 |
| Tests | `vendor/bin/pest` | PASS* | 67 passed, 2 failed — both failures pre-exist on feature/v6 8b55ccc (64 passed, 2 failed): `GoogleCallbackTest.php:312,330` |
| Composer | `composer validate --strict` | PASS | `./composer.json is valid` |

## Change Log
| Date | Change | Why | Approved by |
|---|---|---|---|

## Rulings
- Ruling: merge with the 2 pre-existing failures in `GoogleCallbackTest.php:312,330` (controller catch-all from ea66db0 swallows listener exceptions; tests expect rethrow) — v6-03 owns both files and rewrites them (AC6) — cost if wrong: suite red on feature/v6 until wave 1 merges.
- Merged: cee3948..ba6ecff, merge commit 6b77495.
