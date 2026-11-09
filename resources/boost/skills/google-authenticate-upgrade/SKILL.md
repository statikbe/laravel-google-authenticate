---
name: google-authenticate-upgrade
description: "Upgrades a Laravel app from statikbe/laravel-google-authenticate 3.x/4.x to 5.0 and hardens it so only people of the organisation can become super admin through Google login. Activate when the user mentions upgrading, migrating or bumping laravel-google-authenticate, Google login hardening, super admin via Google, the GoogleUserRegistered event, or when composer.json requires statikbe/laravel-google-authenticate below ^5.0."
license: MIT
metadata:
  author: Statik
---

# Upgrade and harden laravel-google-authenticate (5.0)

Boost only offers this skill for packages the app requires directly in `composer.json`. It shows up after `composer update` + `php artisan boost:update`; if it is missing, check the package is a direct requirement.

Rules for you, the agent: work on a branch; never connect to remote servers or run anything against production (give the user the command and wait for the output); never add `declare(strict_types=1)`; do not assume any file, constant or class named in the snippets exists, adapt them to the project's own role system (spatie/permission, Shield, Nova gate, custom column, ...).

Goal (owner requirement): only people of the organisation (email domain AND Google `hd` both in `domains.allowed`) who log in through Google can become super admin. Users created any other way (panel/Nova "create user", seeders, factories, a mass-assigned `provider`, linking an existing row) must never get it.

## Checklist

- [ ] 1 Detect: version, own Google controller or package controller, config
- [ ] 2 Bump to ^5.0, config (`remember`, non-empty `allowed`)
- [ ] 3 Super admin / role grant only via `GoogleUserRegistered`
- [ ] 4 `HasPanelShield` auto-role neutralised
- [ ] 5 Self-service account deletion off
- [ ] 6 `domains.allowed` boot guard
- [ ] 7 Advisory: password / email change by Google users (report, do not change)
- [ ] 8 Seeders and factories safe outside local/testing
- [ ] 9 Overrides, linking, nullable `password`
- [ ] 10 Remember tokens cleared (user-run)
- [ ] 11 Tests, including once with `APP_RUNNING_IN_CONSOLE=false`
- [ ] 12 Final verification

## 1. Detect

- `composer show statikbe/laravel-google-authenticate`; read the constraint in `composer.json`. Already 5.x: only verify steps 3-8.
- Read `config/google-authenticate.php`. A legacy `config/google-auth.php` (3.x, keys like `auth_role_domain`) means the 3.x to 4 steps in the package `upgrade-guide.md` come first. Note `domains.allowed`, `domains.disabled`, `register_enabled`, `user_columns`, `middleware`. A published copy may lack `remember`/`register_enabled`; package defaults are `false` / `true`.
- Which login code runs? `grep -rnE "Socialite::driver|GoogleAuthenticateController|google\.auth|login/google" app routes bootstrap config`. If the app has its own Google controller (own callback/`findUser`), the 5.0 checks do NOT protect it. Either point the routes at the package controller (preferred), or re-implement in the app controller: refuse unless `email_verified` is true; email domain in `allowed` and not in `disabled`; with `allowed` set the lowercase `hd` claim must be in it; link an existing row only when `hd` equals the email domain or the email is gmail.com/googlemail.com, then set `password` to null and rotate `remember_token`; `Auth::login($user, false)`; grant roles only for a new row after those checks.
- Search `app database routes bootstrap config tests` for: `HasGoogleAuth`, `findOrCreate`, `createUser`, `provider`, `provider_id`, `hd`, `assignRole`, `syncRoles`, `super.?admin`, `HasPanelShield`, `shouldShowDeleteAccountForm`, `Registered::class`.

## 2. Bump and config

- Set `^5.0` in `composer.json`, run `composer update statikbe/laravel-google-authenticate`, then `php artisan boost:update`.
- In `config/google-authenticate.php` add `'remember' => false,` (explicit; `true` only if the app wants the old behaviour) and `'register_enabled' => true,` when missing. Keep `domains.disabled`.
- `domains.allowed` must be non-empty and lowercase wherever roles are granted: empty lets every Google account on earth register. With it set, the Google `hd` must also be in the list, so personal and non-Workspace accounts are refused. Ask the user which listed domains are Workspace primary domains; for alias/secondary domains the primary `hd` domain must be in `allowed` too, or those users are locked out.
- With `register_enabled` false the package never creates rows, so `GoogleUserRegistered` never fires: roles are assigned by an admin.

## 3. Role / super admin grant

Detect (grep `app`, including `Providers`, `Observers`, traits):

`grep -rnE "(User|static)::(created|creating|saved)\(|function booted|ObservedBy|Observer|->provider\b|'provider'|assignRole|syncRoles|SUPER_ADMIN|super.?admin|str_ends_with|endsWith|strpos|explode\('@'" app`

Problems: a role granted from a model event (also a closure in a service provider or an Observer), from the `provider` column (stays mass-assignable, so it can be forged), or from an email domain alone (a user created in the panel, a seeder or a factory with that domain gets it; case differences and non-Workspace accounts slip through). Also email-domain-only helpers that gate privileges (`isStatik()`-style `strpos($email, '@x.be')`, which is also buggy: position 0 is falsy).

Fix: delete that code and add a listener. `GoogleUserRegistered` (`$user`, `$googleUser`, `$hostedDomain`, namespace `Statikbe\GoogleAuthenticate\Events`) fires once, only for a brand-new row, after every check passed, before the login, inside the same database transaction. A throwing listener aborts the login and rolls the row back (only DB work rolls back: no mail/HTTP in it). `$hostedDomain` is lowercase, `''` when Google sent none.

```php
namespace App\Listeners;

use Statikbe\GoogleAuthenticate\Events\GoogleUserRegistered;

class GrantSuperAdminToOrganisation
{
    public function handle(GoogleUserRegistered $event): void
    {
        $allowed = array_values(array_filter(
            array_map(fn ($domain) => strtolower((string) $domain), (array) config('google-authenticate.domains.allowed', [])),
        ));
        $emailDomain = strtolower((string) substr((string) strrchr((string) $event->user->email, '@'), 1));

        // Fail closed: no allow-list, or either domain outside it, grants nothing.
        if ($allowed === []
            || ! in_array($event->hostedDomain, $allowed, true)
            || ! in_array($emailDomain, $allowed, true)) {
            return;
        }

        $event->user->assignRole(SUPER_ADMIN_ROLE); // the app's own role constant/name; spatie/permission shown
    }
}
```

Laravel auto-discovers `app/Listeners` by the typed `handle` parameter; otherwise `Event::listen(GoogleUserRegistered::class, ...)`. Adapt the last line (spatie `assignRole`, Nova gate permission, a column set with `forceFill`). If the app has several roles per domain, keep the same two-domain check. Existing rows linked to Google keep their roles and get no grant. If the old code also granted a default non-privileged role at creation, keep that in the same listener or leave it where it is, but never based on `provider`.

## 4. Filament Shield `HasPanelShield`

Detect: `grep -rn "HasPanelShield" app` and `panel_user` in `config/filament-shield.php`. When `panel_user.enabled` is true, the trait registers (only when not running in console) a `created` hook that gives the `panel_user` role to every new user, Google-created ones included, so any Google account that passes the checks (all of them when `allowed` is empty) gets panel access. Decide with the user: remove the trait (implement `canAccessPanel` yourself), set `panel_user.enabled` false, or keep it only when every `allowed`-domain Google user may enter the panel. Tests run in console and do not exercise this hook: verify with `APP_RUNNING_IN_CONSOLE=false` (step 11). Check `Registered` event listeners that assign a default role too.

## 5. Self-service account deletion

Detect: `grep -rnE "shouldShowDeleteAccountForm|DeleteAccount|deleteAccount" app config` (Filament edit-profile plugin, Breezy, Jetstream, custom "delete my account"). A user who deletes their row and signs in with Google again is re-created and re-granted the creation-time role (privilege reset or escalation). Disable it (`->shouldShowDeleteAccountForm(false)` / remove the route). If it must stay, make sure the grant cannot recur beyond step 3's org check. With `SoftDeletes` the package does not see trashed rows, so the re-login hits the unique email index and fails; restore or force-delete deliberately.

## 6. Boot guard for `domains.allowed`

When roles are granted through Google, add in `AppServiceProvider::boot()` (or at least a test asserting the same):

```php
if (! app()->environment(['local', 'testing'])
    && config('google-authenticate.register_enabled', true)
    && array_filter((array) config('google-authenticate.domains.allowed', [])) === []) {
    throw new \LogicException('google-authenticate.domains.allowed must not be empty.');
}
```

## 7. Advisory only: password or email set by Google users

Report to the user, do not change code. A Google-provisioned row (`provider_id` set) that can set a password (password reset) or change its email in a profile page keeps access after the Workspace account is suspended. Options: hide the email form on profile pages; block password login and password reset for rows with `provider_id`; or accept and document. Linking already nulls `password` on rows that existed before.

## 8. Seeders and factories

`grep -rnE "Hash::make|bcrypt|'password'|@[a-z0-9.-]+\.[a-z]+'|firstOrCreate|syncRoles|assignRole" database app/Console`. Flag seeders with a default or hardcoded password, or a hardcoded super-admin email: they must be guarded (`app()->environment(['local', 'testing'])`, or password from env/prompt) and not be reachable from `DatabaseSeeder` in production. Do not rewrite silently: list them and propose the guard.

## 9. Overrides and linking

- `grep -rn "extends GoogleAuthenticateController" app`. `createUser` is now `protected function createUser(array $userData, AbstractUser $googleUser): User` (was `createUser(array $userData, bool $emailVerified)`); `findOrCreate` is protected and overridable. Security checks run before `findOrCreate` in a private method: delete copies, never bypass. Set `provider`/`provider_id` with `forceFill`, never from request data.
- `HasGoogleAuth` is unchanged: `provider`/`provider_id` stay fillable. Never grant anything from them.
- Linking: an existing row matched by email is linked only when `hd` equals the email domain or the email is gmail.com/googlemail.com; otherwise refused. Linking sets `password` to `null` and rotates `remember_token`. Verify `users.password` is nullable (the package migration stub makes it so); if NOT NULL, linking fails with a database error. Tell the user about these rules.

## 10. Remember tokens (user-run)

Tokens issued before the upgrade keep working. Never run this yourself against production; give the user this command per environment:

```
php artisan tinker --execute="DB::table((new App\Models\User)->getTable())->update(['remember_token' => null]);"
```

## 11. Tests

Use the real callback route with a mocked Socialite user: see `vendor/statikbe/laravel-google-authenticate/tests/Feature/GoogleCallbackTest.php` (`Socialite::shouldReceive('driver->user')` returning a Socialite user with `setRaw(['email', 'email_verified', 'hd'])` and `map(['id', 'email'])`). With `allowed` set, assert:

- missing or wrong `hd`: no user row, no login;
- allowed email domain + matching `hd`: user created and gets the role via the listener;
- a user created via the panel/Nova create action, a factory or a seeder never has the super-admin role;
- a row with `provider => 'google'` mass-assigned gets no role;
- unverified email refused; `allowed` empty grants no role.

Run the project suite with its own runner (e.g. `ddev exec ./vendor/bin/pest`). Run the grant tests once more with `APP_RUNNING_IN_CONSOLE=false` to cover web-runtime-only hooks (step 4).

## 12. Verify

- `composer show statikbe/laravel-google-authenticate` reports 5.x.
- Config has `remember` and a non-empty lowercase `domains.allowed`; step 6 guard present.
- Step 3 grep finds no role grant on model events, on `provider`, or on an email domain alone; the only super-admin grant is the `GoogleUserRegistered` listener.
- `HasPanelShield` (4), self-delete (5), seeders (8) fixed or reported.
- Tests pass, normal run and with `APP_RUNNING_IN_CONSOLE=false`.
- Tell the user: step 7 advisories, the step 10 command, and ask for a manual login in a non-production environment with an allowed Workspace account and a non-allowed one (must be refused).
