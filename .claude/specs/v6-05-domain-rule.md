---
key: v6-05-domain-rule
status: done
mode: standalone
size: full
base: feature/v6
branch: feature/v6-05-domain-rule
runner: (none)
wave: 2
---

## Goal
One fail-closed privilege rule (`isAllowedDomain`) exposed through a facade and the event, plus the D9 guard that stops an empty allow-list from silently opening registration. Security-sensitive.

## Context
- Plan D7, D9, D10; section 6.1.
- `src/GoogleAuthService.php` (after 02): private `assertLoginAllowed()` (verified email, allow/deny lists, `hd`), private `emailDomain()`.
- `src/Events/GoogleUserRegistered.php`: `readonly class` with `$user`, `$googleUser`, `$hostedDomain` (lowercased, `''` for personal accounts).
- Service is a singleton (04).

## Acceptance criteria
AC1. `GoogleAuthService::isAllowedDomain(string $email, ?string $hostedDomain = null): bool` returns true only when: `domains.allowed` non-empty AND email domain not in `domains.disabled` AND email domain in `allowed` AND (`$hostedDomain === null` OR `$hostedDomain` in `allowed`). Comparison case-insensitive. `''` hosted domain → false.
AC2. Facade `Statikbe\GoogleAuthenticate\Facades\GoogleAuthenticate`, accessor `GoogleAuthService::class`, registered as alias `GoogleAuthenticate` in `composer.json` `extra.laravel.aliases`. `GoogleAuthenticate::isAllowedDomain()` works (test).
AC3. `GoogleUserRegistered::isFromAllowedDomain(): bool` delegates to the service with the user's email (from the mapped email column) and `$hostedDomain`.
AC4. D9: when `register_enabled` is true, `domains.allowed` is empty and `domains.allow_any` is false, a Google login that would **create** a user is refused (403, existing error flow) and the misconfiguration is passed to `report()`. Existing users (provider_id match or link) still log in. `allow_any = true` restores 5.x behaviour (tests for all three).
AC5. **Absence:** `assertLoginAllowed()` does not call `isAllowedDomain()`; the facade has no role/assign methods.
AC7. Hardening (security review on v6-02): on the provider_id-match sync path, when the incoming Google email differs from the row's email and another row (different key) already has it, refuse with `GoogleAuthenticationException` (403, "E-mail linked to another account") and save nothing. Test: two rows, linked user's Google email changes to the other row's email → 403, neither row changed.
AC6. Tests in `tests/Feature/DomainRuleTest.php`: empty `allowed`, `disabled` hit, email domain mismatch, `hd` mismatch, `hd` null, `hd` `''`, mixed case.

## Out of scope / does not touch
Listener (06), gate (07), provider.

## Risks & assumptions
| Risk | Provisional decision | Cost if wrong |
|---|---|---|
| Event needs the email but the column is configurable | read via the `user_columns.email` mapping's target column | wrong column on custom mappings — test with a renamed column |

## Tasks
- [x] 1. `DomainRuleTest` for AC1 + AC6 (fail first) — AC1, AC6
- [x] 2. `isAllowedDomain()` in service — AC1
- [x] 3. Facade + composer alias + facade test — AC2
- [x] 4. Event method + test — AC3
- [x] 5. D9 tests in `GoogleAuthServiceTest.php`, then guard in the create branch — AC4
- [x] 6. Absence checks (grep-style test or review note) — AC5

## Gate Ledger
| Gate | Command | Result | Evidence |
|---|---|---|---|
| Style | `vendor/bin/pint --test` | PASS | exit 0 (orchestrator, 673303e) |
| Static | `vendor/bin/phpstan analyse --no-progress` | PASS | exit 0 (worktree needed 1G; main checkout default OK) |
| Tests | `vendor/bin/pest` | PASS | 114 passed (284 assertions), also on feature/v6 after merge 372c1d5 |
| Composer | `composer validate --strict` | PASS | valid |

## Change Log
| Date | Change | Why | Approved by |
|---|---|---|---|
| 2026-11-12 | SPEC GAP: D9 broke 9 legacy callback tests (empty `allowed`). Ruling: TestCase default `allow_any=true`; D9 tests set false; +1 callback-level D9 test. 05 owns TestCase + GoogleCallbackTest for this after v6-07 merged | test suite assumed 5.x empty-list semantics | orchestrator ruling |
| 2026-11-12 | AC7 added: refuse email sync onto another row's email | automated security review of v6-02; pre-existing 5.0 behaviour, hardened here because 05 owns the service in wave 2 | orchestrator ruling |

## Rulings
- Ruling: login check and D9 guard stay separate from `isAllowedDomain()` — empty `allowed` means "anyone may log in" there but "nobody is privileged" here — cost if wrong: one helper to merge later.
