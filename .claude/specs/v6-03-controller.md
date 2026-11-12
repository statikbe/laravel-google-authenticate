---
key: v6-03-controller
status: done
mode: standalone
size: small
base: feature/v6
branch: feature/v6-03-controller
runner: (none)
wave: 1
---

## Goal
Make the package controller good enough that projects stop subclassing it: intended redirect, `guest` on the routes, unexpected errors reported. one audited project's only reason for its controller subclass is the intended redirect.

## Context
- Plan section 2 row for the controller, build step 5.
- `src/Http/GoogleAuthController.php` (after 01): `redirectToProvider()`, `handleProviderCallback(GoogleAuthService $service)`, `sanitiseLoginUrl()`; `guest` middleware is set in the constructor today.
- `routes/web.php`: names `google.auth.login`, `google.auth.callback`, middleware from `config('google-authenticate.middleware')`.
- Tests: `tests/Feature/GoogleRedirectTest.php`, `GoogleCallbackTest.php` (return-URL guard and error paths covered in 5.0).

## Acceptance criteria
AC1. Both routes carry `guest` in the route definition; the constructor no longer sets middleware. A logged-in user hitting `google.auth.login` is redirected (test).
AC2. After a successful callback the user lands on `redirect()->intended(config('google-authenticate.redirect_url'))`: intended URL when set, else `redirect_url` (two tests).
AC3. An unexpected `Throwable` in the callback is passed to `report()` and the user gets the existing generic error redirect (test with `Exceptions::fake()` or equivalent asserting it was reported). `GoogleAuthenticationException` (expected 403s) is **not** reported.
AC4. The 5.0 return-URL guard (`sanitiseLoginUrl`) behaviour is unchanged; its tests pass unchanged.
AC5. Route names and URIs unchanged.
AC6. The two tests failing since ea66db0 (`GoogleCallbackTest.php` "does not log in when a registered listener throws", "registers again after a listener failed the first attempt") are rewritten to the 6.0 behaviour: a throwing listener → no login, new row rolled back, exception passed to `report()`, user redirected with the generic error; the second attempt registers cleanly. Suite fully green after this issue.
AC7. Remove the `phpstan.neon.dist` ignoreErrors entries that name v6-03; phpstan stays green.

## Out of scope / does not touch
Service internals (02), provider (04). Edits to `phpstan.neon.dist` limited to deleting the v6-03 entries.

## Risks & assumptions
| Risk | Provisional decision | Cost if wrong |
|---|---|---|
| `intended` vs the flashed Referer return URL on errors | errors keep using the sanitised return URL; success uses `intended` | one redirect target to adjust |

## Tasks
- [x] 1. Tests for AC1–AC3 (fail first) — AC1, AC2, AC3
- [x] 2. Move `guest` to `routes/web.php`; `intended()`; `report()` for non-domain throwables — test: full suite — AC1–AC5

## Gate Ledger
| Gate | Command | Result | Evidence |
|---|---|---|---|
| Style | `vendor/bin/pint --test` | PASS | exit 0 (orchestrator, 52c2fbf) |
| Static | `vendor/bin/phpstan analyse --no-progress` | PASS | exit 0 |
| Tests | `vendor/bin/pest` | PASS | 74 passed (215 assertions) |

## Change Log
| Date | Change | Why | Approved by |
|---|---|---|---|

## Rulings
- Merged bde0618..52c2fbf, merge a434852.
- Agent choice kept: `instanceof Authenticatable` guard before `Auth::login()` (LogicException → reported, generic error); `InvalidStateException` not reported (user-caused).
