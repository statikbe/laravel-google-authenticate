---
key: v6-06-listener
status: done
mode: standalone
size: full
base: feature/v6
branch: feature/v6-06-listener
runner: (none)
wave: 3
---

## Goal
Ship the default super admin listener, overridable per role library through one protected method, registered from config. Security-sensitive: grants a privileged role.

## Context
- Plan section 6 (diagram, spatie table, sketch), D3, D4, D5, D11, D1.
- `GoogleUserRegistered::isFromAllowedDomain()` exists (05). Spatie test tables + `HasRoles` test user exist in `tests/TestCase.php` / `tests/Support` (07).
- Config: `super_admin_role` (env), `super_admin_listener` (class string, no env, default `GrantSuperAdminRole::class`; must be that class or a subclass).

## Acceptance criteria
AC1. `handle()` returns without action when `super_admin_role` is empty or `isFromAllowedDomain()` is false; otherwise calls `$this->assignRole($event->user, $roleName)`.
AC2. Default `assignRole()`: returns without grant when spatie is not installed (`! class_exists(PermissionRegistrar::class)`) or `config('permission.teams')` is true; throws `LogicException` when the user lacks `assignRole` (no `HasRoles`); else `findOrCreate` via `app(PermissionRegistrar::class)->getRoleClass()` with `Guard::getDefaultName($user)` and `assignRole`.
AC3. Provider registers `Event::listen(GoogleUserRegistered::class, $listener)` only when `super_admin_listener` and `super_admin_role` are both non-empty. A configured class that does not exist, or that does not extend `GrantSuperAdminRole`, throws at boot with a clear message (tests).
AC4. A subclass set as `super_admin_listener` that overrides only `assignRole()` runs instead of the default, and still skips users outside `allowed` (test).
AC5. Tests (real spatie tables): grant with default and custom role name; role created when missing, existing reused, custom Role model respected; no grant without `allowed`, on `hd` mismatch, on a linked row, when `super_admin_listener` is null; teams on → no grant and login succeeds; missing `HasRoles` → new user rolled back and login fails.
AC6. **Absence:** listener does not implement `ShouldQueue`.
AC8. Gate hardening (security review on v6-07): the provider's `Gate::before` checks the role under the user model's own guard: `$user->hasRole($role, Guard::getDefaultName($user))`. Test: a role with the same name under another guard does not pass the gate.
AC7. Remove the `phpstan.neon.dist` ignore for `GrantSuperAdminRole` in `config/google-authenticate.php` (named v6-06); phpstan stays green.

## Out of scope / does not touch
Gate (07), domain rule (05), docs (08). Spatie-not-installed path is covered by code review + a unit test that fakes the class check if feasible; no separate CI job in this issue.

## Risks & assumptions
| Risk | Provisional decision | Cost if wrong |
|---|---|---|
| Testing "spatie missing" with spatie in require-dev | extract the check into `protected function permissionsAvailable(): bool` and override it in a test subclass | one untested branch if not feasible — record ruling |

## Tasks
- [x] 1. `GrantSuperAdminRoleTest` covering AC1, AC2, AC5 (fail first) — AC1, AC2, AC5
- [x] 2. `src/Listeners/GrantSuperAdminRole.php` per plan section 6 sketch — AC1, AC2, AC6
- [x] 3. Provider registration + missing-class test + subclass test — AC3, AC4

## Gate Ledger
| Gate | Command | Result | Evidence |
|---|---|---|---|
| Style | `vendor/bin/pint --test` | PASS | exit 0 (orchestrator, 989bc6d) |
| Static | `vendor/bin/phpstan analyse --no-progress` | PASS | exit 0 |
| Tests | `vendor/bin/pest` | PASS | 135 passed (317 assertions); same on feature/v6 after merge 959942a |

## Change Log
| Date | Change | Why | Approved by |
|---|---|---|---|
| 2026-11-12 | AC8 added: guard-scoped gate check | automated security review of v6-07; gate stays default-on (user decision D8) | orchestrator ruling |
| 2026-11-12 | `super_admin_listener` must extend `GrantSuperAdminRole`, boot throws otherwise (AC3 updated) | keeps the domain check in the package; a fully custom listener uses `null` + own registration | Kristof |

## Rulings
- "Spatie missing" via overridable `permissionsAvailable()` (listener and provider); real `class_exists` branch not exercised with spatie in require-dev — cost if wrong: an untested branch.
- TestCase default `super_admin_listener = null`; listener tests enable it.
- Round 1: gate crashed for non-spatie apps with own `hasRole()` → `hasRoleOnOwnGuard()` falls back to plain `hasRole($role)` (test). Config `register_enabled` comment fixed.
