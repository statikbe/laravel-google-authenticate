# v6.0 final review — findings and rulings

Date: 2026-11-12 · Branch: `feature/v6` (2e2159c) · Base: `origin/develop`

Three read-only reviews ran in parallel over `git diff origin/develop...feature/v6`:

| Review | Verdict |
|---|---|
| Senior branch review | 🟡 Ship after fixes (1 High, 1 Medium, 3 Low) |
| Security review | No Critical or High; 3 Medium, 11 Low |
| Smell audit | 1 🔴 (false alarm, see S1), 4 🟡, 2 ❓ |

Gates at review time: Pint pass, PHPStan level 6 no errors, Pest 135 passed (317 assertions).

## Fix list (round 1)

IDs are referenced by the fix commits.

### Code

| ID | Source | File | Problem | Fix |
|---|---|---|---|---|
| F1 | smell, security L5 | `config/google-authenticate.php` | `GOOGLE_AUTH_ALLOWED_DOMAINS="a.com, b.com"` keeps `" b.com"`, which never matches | `array_map('trim', ...)` before `array_filter` |
| F2 | security L2 | `src/GoogleAuthService.php` | `provider_id` lookups ignore `provider` | add `provider = 'google'` to every `provider_id` lookup, including the "linked to another account" check |
| F3 | security L3 | `src/GoogleAuthService.php` | email lookups use a hard-coded `email` column and are case-sensitive on Postgres/SQLite | use the mapped email column and compare lowercased |
| F4 | security L1 | `src/Http/GoogleAuthController.php` | `parse_url` differential: `http://evil.com\@app.com/` passes the host allow-list | reject return URLs with `\`, control characters, or user/password parts |
| F5 | security L9, smell | migration | hard-coded `users` table; silent return when it is missing | use the configured user model's table; a missing table fails with a clear message instead of being recorded as run |
| F6 | security L9 | migration | `password->nullable()->change()` resets length/default | keep the existing column length when making it nullable |
| F7 | security M3 (user: add only when safe) | migration + service | no unique index on `provider_id`; double callback can create two rows | add a unique index only when none exists and there are no duplicate values; otherwise skip. Service retries the lookup once on a unique violation |
| F8 | senior L3, security L6 | `src/GoogleAuthService.php` | `register_enabled` read once in the constructor of a singleton (stale under Octane/tenancy) | read config at call time; drop the constructor property |
| F9 | security L7 | service, controller | refused logins leave no trace; D9 `report()` fires on every refused visitor | `Log::warning` on refused logins with the Google `sub` (never the email); report the D9 misconfiguration at most once per hour |
| F10 | smell, security L11 | `src/Listeners/GrantSuperAdminRole.php` | teams on skips the grant silently | log a warning when the grant is skipped because teams are on |
| F11 | security M1 (user: event + docs) | service | linking an existing row leaves the old sessions and API tokens valid | dispatch new event `GoogleAccountLinked(Model $user, AbstractUser $googleUser)` inside the transaction on every link |
| F12 | smell S1 | tests | claim: guest `Gate::allows()` crashes in `Gate::before` | add a guest Gate test that proves it does not (Laravel skips before-callbacks for guests when `$user` is untyped) |
| F13 | senior L4 | tests | `google-migrations` publish tag untested | assert the tag publishes the migration |

### Docs and packaging

| ID | Source | File | Fix |
|---|---|---|---|
| D1 | senior L5 | `readme.md` | add `allow_any` to the `domains` sample |
| D2 | senior M2 | `readme.md`, `SKILL.md` | a `GrantSuperAdminRole` subclass in `app/Listeners` is auto-discovered by Laravel and also registered from config, so it runs twice and `null` does not turn it off: tell users to place it outside `app/Listeners` (e.g. `app/Auth/`) |
| D3 | F11 | readme, upgrade guide, changelog | document `GoogleAccountLinked`, revoking sessions/tokens there, and the `AuthenticateSession` middleware |
| D4 | security L8 | readme | the event carries the OAuth access/refresh token: never queue a listener that receives it as-is |
| D5 | F7 | upgrade guide, changelog | unique index on `provider_id` and how to clean up duplicates when the migration skips it |
| D6 | senior H1 | `.gitattributes` | `export-ignore` for `/.claude`, `/docs`, `/tests` and dev config files |

## Rulings (no code change)

| Source | Finding | Ruling |
|---|---|---|
| senior H1 | specs and plan name audited client projects | names replaced by neutral labels in the specs and plan; `feature/v6` was never pushed and integrates into `develop` as one squashed commit, so the earlier commits never leave the local machine (user decision) |
| smell S1 | guest crash in `Gate::before` | false alarm: Laravel does not call before-callbacks for guests when the first parameter is untyped without a default. F12 adds the proof |
| security M2, smell ❓ | password wiped on link | intended since 5.0 (blocks pre-registration takeover), documented in the readme |
| smell ❓ | breaking changes | covered by the upgrade guide (13 items) |
| smell 🟡 | event uses `app()` to reach the service | kept: `isFromAllowedDomain()` is the documented API for custom listeners |
| security L4 | soft-deleted users are recreated | documented; a "refuse blocked users" hook is a possible later feature |
| security L7 | `report()` of a `QueryException` can log bound emails | accepted: standard Laravel behaviour, the app's log channel decides |
| security L10 | no PKCE, no throttle, relative redirect URI | hardening only; Socialite validates `state`, Google rejects unregistered redirect URIs |
| security L11 | gate and listener on by default | user decision D4/D8, called out in the upgrade guide |

## Follow-up decisions (round 1)

| Topic | Decision |
|---|---|
| `provider_id` lookups (F2) | Match `provider = 'google' OR provider IS NULL`, so older rows and imports keep working. A row with another provider is never matched by a Google id |
| Email owned by another provider's row | Refused with a `GoogleAuthenticationException` (logged per F9), no crash on the unique email index |
| Migration, missing users table (F5) | Fails loudly with a clear message. Supersedes the v6-04 no-op |
| Race retry (F7) | The retry lookup is a locking read, because a plain SELECT reuses the old snapshot under MySQL REPEATABLE READ |
| Ruling: duplicates block the unique index | No console warning. The migration is hook-protected, and the upgrade guide covers the cleanup |
| Info: `LOWER(col) = ?` | Cannot use the email index. Fine for normal user tables |
| Route throttle | Added (user request), default 10/min per IP, `GOOGLE_AUTH_THROTTLE` |
