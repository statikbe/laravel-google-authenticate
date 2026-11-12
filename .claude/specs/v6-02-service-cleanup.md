---
key: v6-02-service-cleanup
status: done
mode: standalone
size: full
base: feature/v6
branch: feature/v6-02-service-cleanup
runner: (none)
wave: 1
---

## Goal
Slim `GoogleAuthService` to one find-or-create path that works with any configured user model, with zero behaviour change. Security-sensitive: linking and creation rules must stay exactly as 5.0.

## Context
- Plan section 3 (both diagrams), section 2 row for the service.
- Today: `authenticate()` returns `App\Models\User` and uses `new User` for the transaction; `findOrCreate()` (register off) and `createUser()` (register on) duplicate lookup; `fillUserData()` + `checkForGoogleData()` loop over `GOOGLE_VALUES`.
- `getUserModel()` already reads `auth.providers.users.model`.
- Existing tests: `tests/Feature/GoogleAuthServiceTest.php`, `GoogleCallbackTest.php` (owned by 03 — do not edit; it must stay green).

## Acceptance criteria
AC1. `final public function authenticate(AbstractUser $googleUser): Model` — no reference to `App\Models\User` anywhere in `src/`. The transaction uses the configured model's connection.
AC2. One lookup flow exactly as the plan section 3 flowchart: provider_id match → sync mapped columns only when `register_enabled`; email match with null provider_id → ownership check (`hd` = email domain, or `gmail.com`) → link; email linked to another Google id → 403; no row → 403 when register off, else create + dispatch `GoogleUserRegistered`.
AC3. **Absence:** `findOrCreate`, `createUser`, `checkForGoogleData` and the `GOOGLE_VALUES` constant no longer exist. `user_columns` mapping uses one `data_get($googleUser->getRaw(), …)`-style lookup per value.
AC4. Every existing test in `GoogleAuthServiceTest.php` and `GoogleCallbackTest.php` passes unchanged (this proves no behaviour change). New tests cover: a non-`App\Models\User` model works; `GoogleUserRegistered` fires only on create, never on link or sync.
AC6. Remove the `phpstan.neon.dist` ignoreErrors block for `src/GoogleAuthService.php` (named v6-02); phpstan stays green.
AC5. The `App\Models\User` alias in `tests/TestCase.php` becomes unnecessary; note it as a concern for 04 (owner of TestCase) rather than editing it.

## Out of scope / does not touch
Domain rules and D9 guard (05); controller (03); provider (04); `assertLoginAllowed` semantics.

## Risks & assumptions
| Risk | Provisional decision | Cost if wrong |
|---|---|---|
| Subclasses override `findOrCreate`/`createUser` | breaking, documented in upgrade guide (08) | none for package |
| `final` on `authenticate` blocks extension | keep `final` (5.0 already has it) | none |

## Tasks
- [x] 1. Test: custom user model class (not aliased) authenticates and gets created — fails today on return type — AC1, AC4
- [x] 2. Test: event fires on create only, not on link or provider_id sync — AC4
- [x] 3. Refactor to the single flow; delete AC3 items; `data_get` mapping — test: whole suite green — AC2, AC3

## Gate Ledger
| Gate | Command | Result | Evidence |
|---|---|---|---|
| Style | `vendor/bin/pint --test` | PASS | exit 0 (orchestrator, c5a01b5) |
| Static | `vendor/bin/phpstan analyse --no-progress` | PASS | exit 0, own ignore block removed |
| Tests | `vendor/bin/pest` | PASS | 78 passed (225 assertions) |

## Change Log
| Date | Change | Why | Approved by |
|---|---|---|---|

## Rulings
- Round 1: claim lookup restricted to the old 8 keys (zero behaviour change); literal words stay literal (test).
- Accepted: register off + email linked to another Google id now 403 "E-mail linked to another Google account" (plan flowchart order; test). provider_id lookup before email (plan order).
- Security review finding (provider_id path syncs `email` onto a value another row holds) is pre-existing 5.0 behaviour → moved to v6-05 AC7.
- Squashed to c5a01b5, merged.
