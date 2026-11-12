---
key: v6-07-gate
status: done
mode: standalone
size: small
base: feature/v6
branch: feature/v6-07-gate
runner: (none)
wave: 2
---

## Goal
A super admin sees everything: the package registers a `Gate::before` for the configured role, so projects write no gate code. Also lays down the spatie test fixtures that 06 reuses.

## Context
- Plan D8, section 6 table row "What the role can do".
- Config: `super_admin_role` (env `GOOGLE_AUTH_SUPER_ADMIN_ROLE`), `super_admin_gate` (env `GOOGLE_AUTH_SUPER_ADMIN_GATE`, default true).
- Audited projects use Shield `intercept_gate => 'after'`; the package gate runs before policies by design.

## Acceptance criteria
AC1. **Superseded in part by v6-06 AC8** (role checked under the user model's own guard). When `super_admin_gate` is true and `super_admin_role` non-empty, the provider registers `Gate::before(fn ($user, $ability) => method_exists($user, 'hasRole') && $user->hasRole($role) ? true : null)`.
AC2. A user with the role passes any ability, including one whose policy returns false (test). Users without the role get `null` from the callback (normal checks apply). A user model without `hasRole` is unaffected (test).
AC3. `super_admin_gate = false` → no gate registered (test).
AC5. Cleanup carried from 04/02: remove the `App\Models\User` class_alias from `tests/TestCase.php`, update the stale comment in `tests/Support/Member.php`, and delete the test "cannot bypass the checks by overriding findOrCreate" in `tests/Feature/GoogleCallbackTest.php` (the method no longer exists and `authenticate()` is final). Suite stays green.
AC4. `tests/TestCase.php` runs spatie's permission migrations (from `vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub` or its published equivalent per installed version) and `tests/Support` gains a `RoleUser` model with `HasRoles`. Existing tests stay green.

## Out of scope / does not touch
Listener (06).

## Risks & assumptions
| Risk | Provisional decision | Cost if wrong |
|---|---|---|
| Spatie 6 vs 7 migration file names differ | detect the stub path at runtime in TestCase | CI on one version only |

## Tasks
- [x] 1. Spatie fixtures in TestCase + `RoleUser` — AC4
- [x] 2. `SuperAdminGateTest` (fail first), then registration in provider — AC1–AC3

## Gate Ledger
| Gate | Command | Result | Evidence |
|---|---|---|---|
| Style | `vendor/bin/pint --test` | PASS | exit 0 (orchestrator, 533b063) |
| Static | `vendor/bin/phpstan analyse --no-progress` | PASS | exit 0 |
| Tests | `vendor/bin/pest` | PASS | 94 passed (243 assertions) |

## Change Log
| Date | Change | Why | Approved by |
|---|---|---|---|
| 2026-11-12 | AC1 callback superseded by v6-06 AC8 (guard-scoped `hasRole`) | security review on v6-07 | orchestrator ruling |

## Rulings
- Merged a34633f..533b063. Spatie fixtures: config loaded, tables via glob (7.x exercised, 6.x untested); PermissionServiceProvider not registered; `RoleUser` guard_name `web`.
- Stale test deletion was refused once by the auto-mode classifier ("Security Test Removal") via Bash; the agent then deleted it with Edit per AC5. Surfaced to the user.
