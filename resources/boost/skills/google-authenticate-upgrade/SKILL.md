---
name: google-authenticate-upgrade
description: "Upgrades a Laravel app from statikbe/laravel-google-authenticate 3.x/4.x/5.x to 6.0 by removing project Google-login code the package now ships, and keeps super admin limited to people of the organisation who log in through Google. Activate when the user mentions upgrading, migrating or bumping laravel-google-authenticate, Google login hardening, super admin via Google, the GoogleUserRegistered event, or when composer.json requires statikbe/laravel-google-authenticate below ^6.0."
license: MIT
metadata:
  author: Statik
---

# Upgrade laravel-google-authenticate to 6.0

Boost only offers this skill for packages the app requires directly in `composer.json`. It shows up after `composer update` + `php artisan boost:update`; if it is missing, check the package is a direct requirement.

Goal (owner requirement): only people of the organisation (email domain AND Google `hd` both in `domains.allowed`) who log in through Google can become super admin. Users created any other way (panel/Nova "create user", seeders, factories, a mass-assigned `provider`, linking an existing row) never get it.

The big change from 5.x: the package now ships the controller, routes, domain guard, default super admin listener and `Gate::before`. Your job is to **remove** project Google-auth code and move its settings to config/env. End state: no Google-auth code in the project, except one optional custom listener for needs the default cannot cover.

## Rules for you, the agent

- Work on a branch. Never connect to remote servers or run anything against production: give the user the command and wait for the output.
- Never add `declare(strict_types=1);`.
- Do not assume any file, class or constant named below exists. Adapt to the project's own role system.
- **Delete, never add.** You never write a boot guard, a `Gate::before`, or a subclass of the package controller or service into the project. The only code you may write is a custom `GoogleUserRegistered` listener (step 4) or a listener subclass that overrides `assignRole()`.
- Show the user what you found before you delete anything. Where a step says STOP AND ASK, do not continue until the user answered.
- Never print secret values (Google client secrets, tokens, passwords) in your output, commits or summaries. Say that a secret exists and where.

## Checklist

- [ ] 1 Detect: version, Laravel 10 stop, own controller, own listener
- [ ] 2 Bump to ^6.0, config to env, `services.google`
- [ ] 3 Cleanup inventory: remove old project code, check migrations
- [ ] 4 Roles: spatie, `HasRoles`, teams, role name, default or custom listener
- [ ] 5 `HasPanelShield` auto-role
- [ ] 6 Self-service account deletion off
- [ ] 7 Domain guard: remove boot guard, decide `allow_any`
- [ ] 8 Advisory: password / email change by Google users (report, do not change)
- [ ] 9 Seeders and factories safe outside local/testing
- [ ] 10 Overrides: list behaviours, then delete
- [ ] 11 Remember tokens cleared (user-run)
- [ ] 12 Tests
- [ ] 13 Verify, security findings

## 1. Detect

- `composer show statikbe/laravel-google-authenticate` and read the constraint in `composer.json`. Already 6.x: only verify steps 4, 12, 13.
- `composer show laravel/framework`. **Laravel 10 or lower: STOP.** 6.0 needs Laravel 11+ and PHP 8.2+. The app stays on 5.x; report this and end.
- Coming from 3.x or 4.x: a legacy `config/google-auth.php` (keys like `auth_role_domain`) means the 3.x to 4 steps in the package `upgrade-guide.md` apply first (read it from `vendor/statikbe/laravel-google-authenticate/upgrade-guide.md` after the bump). Do not repeat that guide here; this skill covers what to remove afterwards.
- Which login code runs? `grep -rnE "Socialite::driver|GoogleAuthenticateController|GoogleAuthService|google\.auth|login/google|['\"]GoogleAuthenticate['\"]|GOOGLE_VALUES|handleProviderCallback|getUserModel|HasGoogleAuth" app routes bootstrap config resources`.
- Project super admin listener: `grep -rnE "GoogleUserRegistered|GrantSuperAdminToOrganisation|assignRole|syncRoles" app`. Note the role name it grants and every other thing it does.
- Role grants elsewhere: `grep -rnE "(User|static)::(created|creating|saved)\(|function booted|ObservedBy|Observer|->provider\b|'provider'|SUPER_ADMIN|super.?admin|str_ends_with|endsWith|strpos|explode\('@'" app`.
- Read `config/google-authenticate.php` (if published): note `domains.allowed`, `domains.disabled`, `register_enabled`, `user_columns`, `middleware`, `redirect_url`.
- Report the findings to the user as a table (pattern, file:line, planned action) before step 2.

## 2. Bump and config

- `composer require statikbe/laravel-google-authenticate:^6.0` (or set `^6.0` in `composer.json` and `composer update statikbe/laravel-google-authenticate`), then `php artisan boost:update`.
- Compare the published `config/google-authenticate.php` with `vendor/statikbe/laravel-google-authenticate/config/google-authenticate.php`. Env vars now cover the common keys:

  | Config | Env var |
  |---|---|
  | `redirect_url` | `GOOGLE_AUTH_REDIRECT_URL` |
  | `register_enabled` | `GOOGLE_AUTH_REGISTER` |
  | `domains.allowed` | `GOOGLE_AUTH_ALLOWED_DOMAINS` (comma separated) |
  | `domains.allow_any` | `GOOGLE_AUTH_ALLOW_ANY_DOMAIN` |
  | `super_admin_role` | `GOOGLE_AUTH_SUPER_ADMIN_ROLE` |
  | `super_admin_gate` | `GOOGLE_AUTH_SUPER_ADMIN_GATE` |
  | `throttle` | `GOOGLE_AUTH_THROTTLE` (default `10,1`, empty disables) |

  There is **no** env var for `super_admin_listener`: the listener class is code and lives only in the published config.
- **STOP AND ASK** before moving a hardcoded `domains.allowed` to `.env` files (the user must set it per environment, and `.env` is not in git).
- If the published config only holds defaults or values now covered by env, propose deleting it. Keep it only for values env cannot hold: `user_columns`, custom `middleware`, or `super_admin_listener`.
- `services.google` in `config/services.php`: the package fills it from `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` with redirect `/login/google/callback` when the app has not set it. Delete the block when it only does that; keep it when redirect or keys differ.

## 3. Cleanup inventory

Walk this table top to bottom. For each row: run the detection, show the hits, then remove the code or move its value to config. Nothing is deleted without showing it first.

| Old project code | Detect | Action in 6.0 |
|---|---|---|
| `use HasGoogleAuth` on User | `grep -rn HasGoogleAuth app` | Remove the import and the `use`. The trait is gone; leaving it makes the app fail. |
| `provider` / `provider_id` in `$fillable` | `grep -rnE "'provider(_id)?'" app/Models` | Remove: the package uses `forceFill`, and a fillable `provider` lets request data forge it. |
| Own Google controller (`Socialite::driver('google')` in `app`) | step 1 grep | Delete it. Point the login button at `route('google.auth.login')`. A custom redirect becomes `GOOGLE_AUTH_REDIRECT_URL`; the intended URL is handled by the package. |
| Own routes to `login/google`, or a subclassed controller | `grep -rnE "login/google|GoogleAuthenticateController|google\.auth" routes app` | Delete. Package routes keep the names `google.auth.login` and `google.auth.callback`, with `guest` middleware, and a successful login uses `redirect()->intended()`. |
| Subclass of the package controller or service, plus its binding | `grep -rnE "extends GoogleAuth|bind\(GoogleAuthService|'GoogleAuthenticate'" app` | Delete both. Column mapping goes to `user_columns`. Extra work for new users goes to a custom listener (step 10). |
| Super admin listener copied from the 5.0 skill or readme | `grep -rln GoogleUserRegistered app` | Handled in step 4. |
| Role grants on model events, `provider` or an email domain | step 1 grep | Delete. Move the intent to a `GoogleUserRegistered` listener (step 4, pattern c). |
| Own `Gate::before` for the super admin role | `grep -rn "Gate::before" app` | Delete when it only checks that role: the package gate gives the role every ability. Keep it when it does more, and tell the user to set `GOOGLE_AUTH_SUPER_ADMIN_GATE=false`. |
| `domains.allowed` boot guard | `grep -rn "domains.allowed" app` | Delete (step 7). |
| Published `config/google-authenticate.php` | file exists | Step 2. |
| `services.google` block | `grep -n "'google'" config/services.php` | Step 2. |
| Published views | `resources/views/vendor/google-authenticate` exists | Delete: never rendered. |
| Published lang | `lang/vendor/google-authenticate` exists | Delete when identical to the package files. Keep real translations. |
| Published 5.x migration | file in `database/migrations` | Keep: it already ran, and the package migration skips its columns. |
| Old 3.x `config/google-auth.php` | file exists | Delete after moving `auth_role_domain` to `GOOGLE_AUTH_ALLOWED_DOMAINS`. |
| Tests of removed project code | `grep -rln "GoogleUserRegistered\|login/google" tests` | Delete tests of deleted code. Keep or adapt the tests of step 12. |

Then run `php artisan migrate --pretend` and show the output. Only missing columns may appear. Anything else: STOP AND ASK.

## 4. Roles

Prerequisites (report each result):

- `spatie/laravel-permission` is optional in 6.0. Without it the default listener grants nothing and the login goes on. If the app expects a super admin role, spatie must be installed and its tables migrated (`php artisan migrate:status`).
- Spatie installed: the User model must use `HasRoles`, or the default listener throws (login fails, error is reported).
- `grep -n "teams" config/permission.php`. **Teams on: the default listener grants nothing** (the package does not support teams: with no team id spatie writes a null team key). The project needs its own team-aware listener: pattern (a).
- Role name: read it from the old listener, from Shield's `super_admin.name` in `config/filament-shield.php`, or from the `roles` table. If it is not `super_admin`, set `GOOGLE_AUTH_SUPER_ADMIN_ROLE` (listener and gate both use it). Check the role's `guard_name` matches the User model's guard: the listener creates the role under that guard and the gate only counts a role under it.

The package gate: `Gate::before` from the package gives the super admin role every ability, before policies. A super admin passes policies that deny them today. The role is checked under the User model's own guard: a role with the same name under another guard does not count. `GOOGLE_AUTH_SUPER_ADMIN_GATE=false` turns it off. **STOP AND ASK** when the project has policies that deny super admins on purpose.

Facade and event helpers for custom code: `GoogleAuthenticate::isAllowedDomain($email, ?$hostedDomain)` (class `Statikbe\GoogleAuthenticate\Facades\GoogleAuthenticate`) and `$event->isFromAllowedDomain()` on `Statikbe\GoogleAuthenticate\Events\GoogleUserRegistered`. Both hold the fail-closed rule: `domains.allowed` non-empty, email domain not in `disabled`, and email domain and `hd` both in `allowed`. Use them instead of copying the check.

**STOP AND ASK** before granting roles differently than today: any path below that changes which role, which users or which domains get a grant compared with the old code. Show old behaviour next to new behaviour and wait.

Pick a pattern per finding:

### Pattern (d): project listener grants super admin plus extra work

Keep the default listener (it grants the role). Trim the project listener to the extra work only (sync to another system, default team, welcome mail, other roles). Remove its super admin grant and its inline domain check. Set `GOOGLE_AUTH_SUPER_ADMIN_ROLE` to the role the old listener granted. Never keep both granting different roles: the user would get two privileged roles.

**STOP AND ASK** before deleting a project listener that has extra logic. List each extra behaviour and where it lives afterwards. Only delete the listener when it did nothing but grant the role with a domain check; then the default replaces it.

```php
namespace App\Listeners;

use Statikbe\GoogleAuthenticate\Events\GoogleUserRegistered;

class SyncNewGoogleUser
{
    public function handle(GoogleUserRegistered $event): void
    {
        if (! $event->isFromAllowedDomain()) {
            return;
        }

        // only the extra work; the default listener grants the super admin role
    }
}
```

### Pattern (c): observer or model event grants roles on `User::created`

Delete the observer or `booted` hook (and its `ObservedBy` / registration). Move each grant to a `GoogleUserRegistered` listener guarded by `$event->isFromAllowedDomain()`. A privileged grant that went to every new user (panel-created included) now goes only to Google users of the organisation. Keep non-super-admin extras (for example a `panel_admin` role) in that listener; the default listener covers only the super admin role. **STOP AND ASK** when the old hook also granted roles to users who are not from the organisation: that behaviour will stop.

```php
namespace App\Listeners;

use Statikbe\GoogleAuthenticate\Events\GoogleUserRegistered;

class GrantPanelRoles
{
    public function handle(GoogleUserRegistered $event): void
    {
        if (! $event->isFromAllowedDomain()) {
            return;
        }

        $event->user->assignRole('panel_admin');
    }
}
```

Laravel auto-discovers `app/Listeners` by the typed `handle` parameter. Outside that folder, or with discovery off, register it with `Event::listen(GoogleUserRegistered::class, GrantPanelRoles::class)` in `AppServiceProvider::boot()`.

### Pattern (a): teams enabled

Disable the default listener: publish the config (`php artisan vendor:publish --tag=google-config`) and set `'super_admin_listener' => null`. Write a team-aware custom listener (below). The gate stays on for the role name in `GOOGLE_AUTH_SUPER_ADMIN_ROLE`, so no `Gate::before` is needed. **STOP AND ASK** for the team that new users belong to and which role in that team (the old code or the user knows).

```php
namespace App\Listeners;

use Statikbe\GoogleAuthenticate\Events\GoogleUserRegistered;

class GrantSuperAdminInTeam
{
    public function handle(GoogleUserRegistered $event): void
    {
        if (! $event->isFromAllowedDomain()) {
            return;
        }

        // set the team context the way the project already does, then grant:
        setPermissionsTeamId($teamId);
        $event->user->assignRole(config('google-authenticate.super_admin_role'));
    }
}
```

Alternative when only the role call differs: keep the default listener's domain check and swap just the grant by setting a subclass class in the published config. Put the subclass in `app/Auth/`, not `app/Listeners`: Laravel event discovery registers every class in `app/Listeners` with a typed `handle` method, so a subclass there would run twice (once discovered, once from the config), and `null` would not turn it off:

```php
// config/google-authenticate.php
'super_admin_listener' => \App\Auth\GrantSuperAdminInTeam::class,
```

```php
namespace App\Auth;

use Illuminate\Database\Eloquent\Model;
use Statikbe\GoogleAuthenticate\Listeners\GrantSuperAdminRole;

class GrantSuperAdminInTeam extends GrantSuperAdminRole
{
    protected function assignRole(Model $user, string $roleName): void
    {
        // team-aware or other-library grant here
    }
}
```

The same swap works for another role library (Bouncer, a column): override only `assignRole()`; the domain check and role name stay in the package. No env var exists for the listener. Turn the package gate off with `GOOGLE_AUTH_SUPER_ADMIN_GATE=false` when the other library has its own super admin check.

Rules for every listener: grant privileged roles only from this event; always check `isFromAllowedDomain()`; do not queue it (`ShouldQueue`), because it must run inside the login transaction; an exception aborts the login and rolls the row back, so never swallow errors.

## 5. Filament Shield `HasPanelShield`

Detect: `grep -rn "HasPanelShield" app` and `panel_user` in `config/filament-shield.php`. When `panel_user.enabled` is true, the trait gives the `panel_user` role to every new user (only when not running in console), Google-created ones included. Report it. **STOP AND ASK**: remove the trait (the project implements `canAccessPanel`, which can call `GoogleAuthenticate::isAllowedDomain($user->email)`), set `panel_user.enabled` false, or keep it. Tests run in console and do not exercise this hook: verify with `APP_RUNNING_IN_CONSOLE=false` (step 12). Check `Registered` event listeners that assign a default role too.

## 6. Self-service account deletion

Detect: `grep -rnE "shouldShowDeleteAccountForm|DeleteAccount|deleteAccount" app config` (Filament edit-profile plugin, Breezy, Jetstream, custom). A user who deletes their row and signs in with Google again is re-created and gets the creation-time grant again. Report it and propose disabling it (`->shouldShowDeleteAccountForm(false)` or removing the route). **STOP AND ASK** before removing a feature the user may want. With `SoftDeletes` the package does not see trashed rows, so the re-login hits the unique email index; restore or force-delete deliberately.

## 7. Domain guard

- Delete the 5.0 boot guard in `AppServiceProvider::boot()` (pattern e): `grep -rnE "domains.allowed|LogicException" app/Providers`. The package guards this at login now: with `register_enabled` on and `allowed` empty it refuses to create new users and reports the misconfiguration. Existing users still log in. Do not write a replacement.
- If `domains.allowed` is empty in the effective config: **STOP AND ASK**. Options: set `GOOGLE_AUTH_ALLOWED_DOMAINS` (comma separated), or `GOOGLE_AUTH_ALLOW_ANY_DOMAIN=true` for a public app. Without either, 6.0 refuses new Google users.
- `register_enabled` false (`GOOGLE_AUTH_REGISTER=false`): the package never creates rows, so `GoogleUserRegistered` never fires and roles are assigned by an admin. Note this for the user; the default listener never runs.
- With `allowed` set, the Google `hd` must also be in the list, so personal and non-Workspace accounts are refused. Ask which listed domains are Workspace primary domains; for alias domains the primary `hd` domain must be in `allowed` too, or those users are locked out.

## 8. Advisory only: password or email set by Google users

Report, do not change code. A Google-provisioned row (`provider_id` set) that can set a password (password reset) or change its email in a profile page keeps access after the Workspace account is suspended. Options: hide the email form on profile pages; block password login and reset for rows with `provider_id`; or accept and document. Linking nulls `password` on rows that existed before.

## 9. Seeders and factories

`grep -rnE "Hash::make|bcrypt|'password'|@[a-z0-9.-]+\.[a-z]+'|firstOrCreate|syncRoles|assignRole" database app/Console`. Flag seeders with a default or hardcoded password, or a hardcoded super-admin email: they must be guarded (`app()->environment(['local', 'testing'])`, or password from env/prompt) and not reachable from `DatabaseSeeder` in production. List them and propose the guard; **STOP AND ASK** before editing seeders.

## 10. Overrides

Before deleting any subclass, binding, own controller or own listener (step 3 rows), list every behaviour it added (extra columns, notifications, syncs, redirect rules, role grants) and show where each lives in 6.0:

- column mapping: `user_columns` in the published config;
- redirect: `GOOGLE_AUTH_REDIRECT_URL`, plus `redirect()->intended()` built into the package;
- extra work for a new user: a `GoogleUserRegistered` listener (step 4);
- domain rules: `domains.allowed` / `disabled`.

Pattern (b), controller subclass that only adds an intended redirect: delete the subclass and its route override; the package already does that. **STOP AND ASK** when a behaviour has no place in 6.0, or when the override did something else the user relies on. Subclasses of the removed `findOrCreate` / `createUser` cannot be kept: their logic moves into a listener.

Also note: the `'GoogleAuthenticate'` container binding is gone; `GoogleAuthenticateController::GOOGLE_VALUES` and `getUserModel()` are gone. Replace `app('GoogleAuthenticate')` with the `GoogleAuthenticate` facade or remove the code.

## 11. Remember tokens (user-run)

Tokens issued before the upgrade keep working. Never run this yourself against production; give the user this command per environment:

```
php artisan tinker --execute="DB::table((new App\Models\User)->getTable())->update(['remember_token' => null]);"
```

## 12. Tests

Use the real callback route (`google.auth.callback`) with a mocked Socialite user: see `vendor/statikbe/laravel-google-authenticate/tests/Feature/GoogleCallbackTest.php` (`Socialite::shouldReceive('driver->user')` returning a Socialite user with `setRaw(['email', 'email_verified', 'hd'])`). With `allowed` set, assert:

- missing or wrong `hd`: no user row, no login;
- allowed email domain + matching `hd`: user created and gets the role (default listener, or your custom listener);
- a user created via a panel/Nova action, a factory or a seeder never has the super admin role;
- a row with `provider => 'google'` mass-assigned gets no role;
- unverified email refused; `allowed` empty grants no role and, without `allow_any`, creates no user;
- with the gate on, the super admin role passes `Gate::allows('anything')`;
- teams on: the custom listener grants the role inside the team.

Run the project suite with its own runner (for example `ddev exec ./vendor/bin/pest`). Run the grant tests once more with `APP_RUNNING_IN_CONSOLE=false` to cover web-runtime-only hooks (step 5). Delete tests of code you removed.

## 13. Verify and report

- `composer show statikbe/laravel-google-authenticate` reports 6.x.
- Grep `app routes config bootstrap` for `Socialite::driver('google')`, `GoogleAuthService`, `GoogleAuthenticateController`, `HasGoogleAuth`, `domains.allowed`, `Gate::before` and `GoogleUserRegistered`. The only allowed hit is the one custom listener, if the user chose one.
- `php artisan event:list --event=GoogleUserRegistered` shows exactly one listener that grants the super admin role.
- `php artisan route:list --name=google` shows only the package routes (`google.auth.login`, `google.auth.callback`).
- `HasPanelShield` (5), self-delete (6), seeders (9) fixed or reported.
- Tests pass, normal run and with `APP_RUNNING_IN_CONSOLE=false`.

**Security finding (pattern f): committed Google client secrets.** `grep -nE "^GOOGLE_CLIENT_(ID|SECRET)=." .env.example` (also check other committed `.env*` files listed by `git ls-files`). A non-empty `GOOGLE_CLIENT_SECRET` in a committed file is a leaked credential: it stays in git history. Report file and line only, **never print the value**. Recommend: rotate the secret in Google Cloud Console, replace the committed values with empty placeholders, and treat history as compromised. Do not rotate or rewrite history yourself; **STOP AND ASK**.

Tell the user: the step 8 advisories, the step 11 command, any security finding, and ask for a manual login in a non-production environment with an allowed Workspace account and a non-allowed one (must be refused).
