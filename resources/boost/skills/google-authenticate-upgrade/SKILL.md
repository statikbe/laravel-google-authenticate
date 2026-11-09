---
name: google-authenticate-upgrade
description: "Upgrades a Laravel app from statikbe/laravel-google-authenticate 3.x/4.x to 5.0. Activate when the user mentions upgrading, migrating or bumping laravel-google-authenticate, Google login hardening, the GoogleUserRegistered event, or when composer.json requires statikbe/laravel-google-authenticate below ^5.0."
license: MIT
metadata:
  author: Statik
---

# Upgrade laravel-google-authenticate to 5.0

Work on a branch. Never run anything against production or remote servers: give the user commands for those and wait for the output.

## 1. Detect

- Run `composer show statikbe/laravel-google-authenticate` and read the constraint in `composer.json`. Stop when already on 5.x.
- Read `config/google-authenticate.php` (published copy). Note `domains.allowed`, `domains.disabled`, `register_enabled`, `user_columns`, `middleware`.
- Search the app for: `HasGoogleAuth`, `GoogleAuthenticateController`, `findOrCreate`, `createUser`, `provider`, `provider_id`, `hd`, `super-admin`, `assignRole`, `HasPanelShield`.

## 2. Bump

- Set the constraint to `^5.0` in `composer.json`, then run `composer update statikbe/laravel-google-authenticate`.
- Run `php artisan boost:update` to receive the latest version of this skill.

## 3. Config

Edit `config/google-authenticate.php`:

- Add `'remember' => false,`. Login no longer sets a remember-me cookie. Use `true` only when the app explicitly wants the old behaviour.
- `domains.allowed` must be non-empty in production. Empty allows every Google account.
- When `allowed` is set, the Google `hd` (Workspace hosted domain) claim must also be in the list, so personal and non-Workspace accounts are refused. Every allowed domain must be a Google Workspace primary domain or a domain whose `hd` is listed. For secondary or alias domains add the primary `hd` domain to `allowed` too, and ask the user which domains are real Workspace domains.
- Keep `domains.disabled` as it is.

## 4. Role assignment

`provider` / `provider_id` stay mass-assignable (`HasGoogleAuth` unchanged), so a role decision based on them can be forged. Find every place that grants roles or privileges based on:

- a `User::created` / `creating` / `saved` model event, `booted()` closure or Observer;
- the `provider` column or `provider_id`;
- a `HasPanelShield`-style hook, or an email domain check next to a login.

Replace each with a listener on `Statikbe\GoogleAuthenticate\Events\GoogleUserRegistered`. The event fires only for a brand-new row, only after every check (verified email, allow/disable lists, `hd`) passed, and before the login. A throwing listener aborts the login and rolls the new user back.

```php
namespace App\Listeners;

use Statikbe\GoogleAuthenticate\Events\GoogleUserRegistered;

class GrantSuperAdminToCompanyDomain
{
    public function handle(GoogleUserRegistered $event): void
    {
        $allowed = array_map('strtolower', config('google-authenticate.domains.allowed', []));
        $emailDomain = strtolower(substr(strrchr($event->user->email, '@'), 1));

        // Both the verified hosted domain and the email domain must be allow-listed.
        if ($allowed !== []
            && in_array($event->hostedDomain, $allowed, true)
            && in_array($emailDomain, $allowed, true)) {
            $event->user->assignRole('super-admin');
        }
    }
}
```

Laravel auto-discovers listeners in `app/Listeners` with a typed `handle` event parameter. Otherwise register it with `Event::listen(...)`. Remove the old model-event code. Never grant a role from the `provider` column.

## 5. Overrides

- Search for classes extending `GoogleAuthenticateController`.
- `createUser` is now `protected function createUser(array $userData, AbstractUser $googleUser): User` (was `createUser(array $userData, bool $emailVerified)`). `findOrCreate` is now `protected` and can be overridden.
- Security checks now run before `findOrCreate` in a private method. Remove copies of those checks, and do not try to bypass them.
- Set `provider` / `provider_id` with `forceFill` in an override, never via mass assignment of request data.

## 6. Remember tokens

Tokens issued before the upgrade keep working until cleared. Do NOT run this yourself on production. Give the user this command for each environment they choose:

```
php artisan tinker --execute="App\Models\User::query()->update(['remember_token' => null]);"
```

## 7. Linking behaviour

Tell the user: an existing row matched by email is linked to Google only when the `hd` claim equals the email domain, or the email is on `gmail.com` / `googlemail.com`. Otherwise the login is refused. Linking sets `password` to `null` (when the column exists) and rotates `remember_token`. Users with a custom domain on a non-Workspace Google account can no longer take over an existing row.

## 8. Tests

- Run the project's test suite (use the project's runner, e.g. `ddev exec ./vendor/bin/pest`). Fix failures caused by the changes above.
- Add tests that go through the real callback route with a faked Socialite user (`Socialite::shouldReceive('driver->user')`) carrying `email`, `email_verified`, `hd`. See `vendor/statikbe/laravel-google-authenticate/tests/Feature/GoogleCallbackTest.php` for the pattern. Cover: unverified email refused, wrong `hd` refused, role granted only via the listener, `provider` column alone grants nothing.

## 9. Breaking changes checklist

- [ ] Unverified Google emails refused everywhere.
- [ ] With `domains.allowed` set, `hd` must match; personal accounts refused.
- [ ] No remember-me cookie by default (`remember` option).
- [ ] Linking requires `hd` == email domain or gmail; clears `password`, rotates `remember_token`.
- [ ] `createUser(array $userData, AbstractUser $googleUser)` signature; `findOrCreate` / `createUser` protected.
- [ ] `GoogleUserRegistered` event added; role grants moved to it.
- [ ] Existing remember tokens cleared (user-run).

## 10. Verify

- `composer show statikbe/laravel-google-authenticate` reports 5.x.
- `config/google-authenticate.php` has `remember` and a non-empty `domains.allowed`.
- Search finds no role assignment tied to model events or the `provider` column.
- Test suite passes.
- Ask the user to log in manually with an allowed Workspace account and a non-allowed account (must be refused) in a non-production environment.
