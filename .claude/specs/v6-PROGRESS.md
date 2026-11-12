---
key: v6
status: done
mode: standalone
size: full
base: origin/develop
branch: feature/v6
runner: (none) — vendor/bin/pest, vendor/bin/pint
---

# v6.0 rework — programme

Source of truth for *why*: `docs/plans/2026-11-10-rework-plan.md` (sections and decisions D1–D11 are referenced by number in every spec).
This file holds *how the work is split*: waves, file ownership, shared contracts and conventions every spec inherits.

## Request (verbatim)
"ok make the specs and split into small workable issues so we can let multiple agents run on this new upgrade"

## Branching
- Integration branch `feature/v6`, cut from `origin/develop` (`develop` = `main` = 5.0.0). It merges back into `develop`; release to `main`.
- Before any build: commit `docs/plans/2026-11-10-rework-plan.md` and `.claude/specs/v6-*.md` on `feature/v6`, so worktree agents can read them.
- One branch per issue: `feature/v6-NN-<slug>` from the current `feature/v6`, built in its own git worktree, merged back (`--no-ff`) by the orchestrator after its gates pass.
- A wave starts only when every issue of the previous wave is merged into `feature/v6`.

## Waves and file ownership

An agent edits only the files its spec owns. Needing another file = `SPEC GAP`, stop.

| Wave | Spec | Owns (exclusive within the wave) | Depends on |
|---|---|---|---|
| 0 | `v6-01-structure` | `composer.json`, every moved/deleted file, `config/google-authenticate.php`, `routes/web.php`, `tests/**` namespace updates | — |
| 1 | `v6-02-service-cleanup` | `src/GoogleAuthService.php`, `tests/Feature/GoogleAuthServiceTest.php`, `phpstan.neon.dist` (delete own entries only) | 01 |
| 1 | `v6-03-controller` | `src/Http/GoogleAuthController.php`, `routes/web.php`, `phpstan.neon.dist` (delete own entries only), `tests/Feature/GoogleRedirectTest.php`, `tests/Feature/GoogleCallbackTest.php` | 01 |
| 1 | `v6-04-zero-config` | `src/GoogleAuthenticateServiceProvider.php`, `database/migrations/*`, `config/google-services.php` (new), `tests/TestCase.php`, `tests/Feature/ServiceProviderTest.php` (new) | 01 |
| 1 | `v6-08-docs` | `readme.md`, `upgrade-guide.md`, `changelog.md`, `docs/plans/2026-11-10-rework-plan.md` | 01 (only for final paths) |
| 1 | `v6-09-boost-skill` | `resources/boost/skills/google-authenticate-upgrade/SKILL.md` | — |
| 2 | `v6-05-domain-rule` | `src/GoogleAuthService.php`, `src/Facades/GoogleAuthenticate.php` (new), `src/Events/GoogleUserRegistered.php`, `composer.json` (`extra.laravel.aliases` only), `tests/Feature/DomainRuleTest.php` (new), `tests/Feature/GoogleAuthServiceTest.php` (D9 cases) | 02, 04 |
| 2 | `v6-07-gate` | `src/GoogleAuthenticateServiceProvider.php`, `tests/TestCase.php` (spatie tables + `HasRoles` user, alias removal), `tests/Support/*`, `tests/Feature/GoogleCallbackTest.php` (delete one stale test), `tests/Feature/SuperAdminGateTest.php` (new) | 04 |
| 3 | `v6-06-listener` | `tests/TestCase.php`, `tests/Support/*` (alone in wave 3), `src/Listeners/GrantSuperAdminRole.php` (new), `src/GoogleAuthenticateServiceProvider.php`, `phpstan.neon.dist` (delete own entry only), `tests/Feature/GrantSuperAdminRoleTest.php` (new) | 05, 07 |
| 4 | docs sync (`v6-08` round 2) | update `readme.md`, `upgrade-guide.md`, `changelog.md` and the Boost `SKILL.md` against the final merged code: guard-scoped gate (AC8), test-only behaviours, any rulings since wave 1 | 06 |
| 4 | verify on `feature/v6` | full gates + final branch review | all |
| 5 | dry run (user) | run the rewritten skill on project A, project B, project C | all |

Max parallel agents: 5 (wave 1), 2 (wave 2), 1 (wave 3).

## Shared contracts (verbatim — every spec builds against these)

```php
// namespaces after 01
Statikbe\GoogleAuthenticate\GoogleAuthService
Statikbe\GoogleAuthenticate\Http\GoogleAuthController
Statikbe\GoogleAuthenticate\GoogleAuthenticateServiceProvider
Statikbe\GoogleAuthenticate\Events\GoogleUserRegistered
Statikbe\GoogleAuthenticate\Exceptions\GoogleAuthenticationException
Statikbe\GoogleAuthenticate\Facades\GoogleAuthenticate          // 05
Statikbe\GoogleAuthenticate\Listeners\GrantSuperAdminRole       // 06

// GoogleAuthService (02 changes the return type, 05 adds the method)
final public function authenticate(AbstractUser $googleUser): Model
public function isAllowedDomain(string $email, ?string $hostedDomain = null): bool

// GoogleUserRegistered (05)
public function isFromAllowedDomain(): bool

// GrantSuperAdminRole (06)
public function handle(GoogleUserRegistered $event): void
protected function assignRole(Model $user, string $roleName): void
```

- Route names stay: `google.auth.login` (GET `login/google`), `google.auth.callback` (GET `login/google/callback`). All three audited projects use them.
- Config file `config/google-authenticate.php`, key set exactly as plan section 5, with `'super_admin_listener' => GrantSuperAdminRole::class` (no env var). 01 writes every key; later specs only read them.
- Container: `GoogleAuthService` is a singleton (04 binds it); the facade accessor is `GoogleAuthService::class`.

## Conventions (inherited by every spec)
- No `declare(strict_types=1);`.
- Pint `laravel` preset (`vendor/bin/pint`), Pest tests, Orchestra Testbench.
- TDD: failing test first, note the expected failure reason, then green.
- No new runtime dependencies. `spatie/laravel-permission` `^6.0|^7.0` only in `suggest` + `require-dev` (D1).
- Commits: Conventional Commits, scope `v6`, e.g. `feat(v6): add GoogleAuthenticate facade`. Stage explicit paths, never `git add .`.
- Each agent returns: status, commit SHAs, one-line test summary, concerns. No files with reports.

## Gate set (per issue branch, then once on `feature/v6`)
| Gate | Command |
|---|---|
| Style | `vendor/bin/pint --test` |
| Tests | `vendor/bin/pest` (full suite) |
| Static analysis | `vendor/bin/phpstan analyse --no-progress` (added in 01; from wave 1 on, every issue keeps it green, no new baseline entries) |
| Composer | `composer validate --strict` (01, 05 only) |
| Final | senior branch review over `git diff origin/develop...feature/v6` |

Branches: `develop` (integration) and `main` (release). `master` is retired — never base on, merge to, or mention it.

## State
| Spec | Status | Branch | Commits |
|---|---|---|---|
| v6-01-structure | done | feature/v6-01-structure | cee3948..ba6ecff, merge 6b77495 |
| v6-02-service-cleanup | done | feature/v6-02-service-cleanup | c5a01b5 |
| v6-03-controller | done | feature/v6-03-controller | bde0618..52c2fbf, merge a434852 |
| v6-04-zero-config | done | feature/v6-04-zero-config | d499480, merge 4cdbe3e |
| v6-05-domain-rule | done | feature/v6-05-domain-rule | 15af6e4..673303e, merge 372c1d5 |
| v6-06-listener | done | feature/v6-06-listener | 7ad7303..989bc6d, merge 959942a |
| v6-07-gate | done | feature/v6-07-gate | a34633f..533b063 |
| v6-08-docs | done | feature/v6-08-docs | 858204b..bc37228, merge 2e18a3c |
| v6-09-boost-skill | done | feature/v6-09-boost-skill | 3e10552 |

## Open decisions
None. All D1–D11 decided in the plan (D1 changed to optional spatie on 2026-11-12).

## Close-out
Built and verified 2026-11-12. Release branch `feature/v6-release` (5 commits on `origin/develop`): pint OK, phpstan level 6 no errors, pest 166 passed, composer valid. Final review 🟡 → all fixes applied; re-review findings fixed (MySQL locking retry), route throttle added on user request. Open: user dry run of the Boost skill on three client projects (wave 5); MySQL/Postgres behaviour of the locking retry and password-length preservation not verified on a real database.

## Change Log
| Date | Change | Why | Approved by |
|---|---|---|---|
| 2026-11-12 | Programme created from the rework plan | user asked for parallel agent work | — |
| 2026-11-12 | Plan D10 text: login check and D9 guard do not use `isAllowedDomain()` | they decide login, not privileges; empty `allowed` means different things | — |
| 2026-11-12 | Specs accepted; PHPStan (Larastan) added to 01 and to every gate | user: "include phpstan" | user |
| 2026-11-12 | v6-01 merged with 2 pre-existing test failures; v6-03 AC6 fixes them. phpstan ignore cleanup added to 02/03/06 | gates evidence in v6-01 ledger | orchestrator ruling |
| 2026-11-12 | Wave 1 merged; feature/v6 green: pint 0, phpstan OK, pest 90/90 | gates on 2e18a3c | orchestrator |
| 2026-11-12 | Docs sync step added before final verify | user: update the readme afterwards so it is up to date | user |
| 2026-11-12 | Wave 3 + docs sync (f6c185e) merged; feature/v6 3254e49: pint 0, phpstan OK, pest 135/135, composer valid | orchestrator gates | orchestrator |
| 2026-11-12 | Final reviews: senior 🟡, security no High, smell 1 false alarm. Fix list F1–F13, D1–D6 in `docs/reviews/2026-11-12-v6-final-review.md`. Client names scrubbed; integrate as one squashed commit | user decisions: scrub+squash, GoogleAccountLinked event, unique index only when safe | user |
| 2026-11-12 | Branch model: `develop`/`main`, `master` retired | user | user |
| 2026-11-12 | Re-review 🟡 → fixes: MySQL locking retry, spec/review follow-ups, external sync wording | re-review | orchestrator |
