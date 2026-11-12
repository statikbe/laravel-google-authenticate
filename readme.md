<p align="center"><img src="assets/card.png" alt="Laravel Google Authenticate"></p>

# Google Authenticator
[![Latest Version on Packagist](https://img.shields.io/packagist/v/statikbe/laravel-google-authenticate.svg?style=flat-square)](https://packagist.org/packages/statikbe/laravel-google-authenticate)
[![Total Downloads](https://img.shields.io/packagist/dt/statikbe/laravel-google-authenticate.svg?style=flat-square)](https://packagist.org/packages/statikbe/laravel-google-authenticate)


This module gives you the option to let you (and your users) log in with their Google account on your Laravel application.

This is initially used to let only people log in from certain workspaces. But can be enabled to let everyone log in. 


[Changelog](changelog.md) - [Upgrade guides](upgrade-guide.md)

---

## Installation

The package needs PHP `^8.2` and Laravel `^11`, `^12` or `^13`. Installing takes three steps.

**1. Require the package.** It registers itself.

``` bash
composer require statikbe/laravel-google-authenticate
```

**2. Set the env vars.**

``` bash
GOOGLE_CLIENT_ID="YOUR_GOOGLE_CLIENT_ID"
GOOGLE_CLIENT_SECRET="YOUR_GOOGLE_CLIENT_SECRET"
GOOGLE_AUTH_ALLOWED_DOMAINS="statik.be,example.com"
```

`GOOGLE_AUTH_ALLOWED_DOMAINS` is a comma separated list of the Workspace domains that may register. Without it, new users are refused (see [Email domains](#email-domains)).

Info on how to create a Google Auth client id and secret can be
found [on their documentation page](https://developers.google.com/identity/protocols/OAuth2). Add `https://your-app.test/login/google/callback` as an authorised redirect URI.

You do not need to edit `config/services.php`. The package fills `services.google` from these env vars, with `/login/google/callback` as redirect. When your app sets `services.google` itself, your values win key by key. `CALLBACK_URL_GOOGLE` is no longer required: keep it if your `config/services.php` already uses it.

**3. Run the migration.** It adds `provider` and `provider_id` to your `users` table, and skips columns that already exist. It also makes `users.password` nullable when it is NOT NULL, because Google-only users have no password. Its `down()` does nothing: a rollback never drops `provider` or `provider_id`, since many apps created those columns themselves. It also adds a unique index on `provider_id` (no double rows from a double callback). When your table already has one, or holds duplicate `provider_id` values, the index is skipped without an error: remove the duplicate rows, then add `$table->unique('provider_id')` in a migration of your own. It uses the table of your configured user model and fails with a clear message when that table does not exist yet.

``` shell
php artisan migrate
```

Then link to the login route in your login and register views: `{{ route('google.auth.login') }}`.

You do not need to add a trait to your `User` model. Roles are optional, see [Super admin role](#super-admin-role).

### Config
Everything below works with env vars. Publish the config file only when you need a value env cannot hold (`user_columns`, `middleware`, `super_admin_listener`):

```bash
php artisan vendor:publish --provider="Statikbe\\GoogleAuthenticate\\GoogleAuthenticateServiceProvider" --tag="google-config"
```

| Key | Env var | Default | What it does |
|---|---|---|---|
| `redirect_url` | `GOOGLE_AUTH_REDIRECT_URL` | `/` | Where the user lands after login, when no intended URL exists |
| `register_enabled` | `GOOGLE_AUTH_REGISTER` | `true` | `false`: only existing users can log in |
| `remember` | - | `false` | `true` issues a remember-me cookie |
| `throttle` | `GOOGLE_AUTH_THROTTLE` | `10,1` | Laravel throttle `maxAttempts,decayMinutes` per IP on both Google routes. Set `GOOGLE_AUTH_THROTTLE=` (empty) to turn it off |
| `domains.allowed` | `GOOGLE_AUTH_ALLOWED_DOMAINS` | `[]` | Comma separated in env |
| `domains.disabled` | - | `[]` | Domains that can never log in |
| `domains.allow_any` | `GOOGLE_AUTH_ALLOW_ANY_DOMAIN` | `false` | `true` lets new users register when `allowed` is empty |
| `super_admin_role` | `GOOGLE_AUTH_SUPER_ADMIN_ROLE` | `super_admin` | Role used by the default listener and the gate |
| `super_admin_listener` | - | `GrantSuperAdminRole::class` | Listener class for `GoogleUserRegistered`. `null` or `''` means no package listener. No env var |
| `super_admin_gate` | `GOOGLE_AUTH_SUPER_ADMIN_GATE` | `true` | `false` turns off the `Gate::before` |
| `user_columns` | - | name, email, email_verified_at | Maps Google data to user columns |
| `middleware` | - | `['web']` | Middleware of the login routes |

#### Email domains
You can change the email domains that can login using Google:
- `allowed`-array: only the domains in this array can login using Google
- `disabled`-array: domains in this array can not login using Google
- Empty `allowed`: existing users can still log in, but **new users are refused**. The visitor sees the generic login error and the misconfiguration is reported. Set `GOOGLE_AUTH_ALLOW_ANY_DOMAIN=true` for a public app that accepts every Google account.

When `allowed` is set, the Google account must also carry a matching hosted domain (`hd` claim). Only Google Workspace accounts have one, so personal Gmail accounts are refused even with an allowed email domain. Google accounts with an unverified email are always refused.

The domain rules apply to existing users too, not only to new registrations. When `register_enabled` is `false`, only existing users can log in, and only with a verified Google email.

``` php
    'domains' => [
        //'allowed' => ['statik.be'],
        //'disabled' => ['google.com'],
        //'allow_any' => true, // public app: new users may register while 'allowed' is empty
    ],
```

#### Linking existing users
A user row that already exists with the same email, but without a Google account, is linked on first Google login only when the Google `hd` claim equals the email domain, or the email is on `gmail.com` / `googlemail.com`. Otherwise the login is refused. A user whose Google email changes to an email that another row already has is refused (error code 403, the visitor sees the generic login error) and nothing is overwritten. When linked, the row's `password` is set to `null` (if the column exists) and its `remember_token` is rotated, so credentials set before the link stop working.

#### Redirect after login
After a successful login the user goes to the intended URL (the protected page they tried to open), or else to `redirect_url`. The login routes use the `guest` middleware: a logged-in user who opens them is redirected. A refused login redirects back with a generic error. Unexpected exceptions are reported with `report()`. An invalid OAuth state (an expired or tampered login) is not reported.

#### Remember me
Logins are not remembered by default. Set `'remember' => true` in the config to issue a remember-me cookie.

#### Custom middlewares
``` php
    'middleware' => [
        'web',
        CustomMiddleware::class
    ], 
```

#### User table
You can customize how a user is saved. The config array `user_columns` will create the fillable data for your user. 
The array keys are your user column names, the array values are what should be stored. (Make sure the value is an array).
You can add multiple values per key, these will be glued together.
The following values would be filled by google's returned data, before being glued: `name`, `email_verified`, `email`, `given_name`, `family_name`, `picture`, `nickname`, `locale`.

For example in your config:
``` php
'user_columns' => [
        'name' => ['name', ' (', 'locale', ')']     // John Doe (en)
        'email_verified_at' => ['email_verified'],  // 2019-10-23 14:31:50
        'email' => ['email'],                       // john@doe.com
        'other data' => ['blablabla'],              // blablabla
]
```

## Super admin role

Out of the box, a new Google user from an allowed Workspace domain gets the super admin role. The package ships a listener for this, `Statikbe\GoogleAuthenticate\Listeners\GrantSuperAdminRole`, and a gate that lets the role do everything.

**This needs [spatie/laravel-permission](https://github.com/spatie/laravel-permission), which is optional.** Install it (`^6.0` or `^7.0`), migrate its tables and add `Spatie\Permission\Traits\HasRoles` to your `User` model. Filament Shield projects already have this. Without spatie, login works and no role is granted. There is no error, so install spatie if you expect the role.

The listener follows these rules:
- It runs only for brand-new users created by a Google login. A row that is linked to Google later, or a user created in a panel, seeder or factory, never gets the role.
- It grants nothing unless `domains.allowed` is not empty and both the email domain and the `hd` claim are in it.
- The role is `super_admin`. Use `GOOGLE_AUTH_SUPER_ADMIN_ROLE` for another name. The role is created when missing, with the guard spatie picks for your user model.
- With spatie installed and no `HasRoles` on the `User` model, the new login fails with a `LogicException` and the new row is rolled back. Add the trait.
- **Spatie teams (`permission.teams`) are not supported.** With teams on, the listener grants nothing and the login goes on. Write a [custom listener](#custom-listener) instead.

**The gate sees everything.** The package registers a `Gate::before` for the role. A super admin passes every ability, also policies that deny them, and also in Filament Shield projects with `intercept_gate => 'after'`. The role is checked under the user model's own guard: a role with the same name under another guard does not count. You do not need a gate of your own. Set `GOOGLE_AUTH_SUPER_ADMIN_GATE=false` when the role must stay limited to its assigned permissions.

**Turn the default listener off.** Publish the config and set `'super_admin_listener' => null`. The gate stays on for users that have the role. There is no env var for the listener: it is a class, so it is code and not per-environment config.

## Custom listener

Write your own listener when the default does not fit. Typical cases: a role system other than spatie, different roles per domain, spatie teams, or extra setup for new users (default team, welcome mail).

`Statikbe\GoogleAuthenticate\Events\GoogleUserRegistered` is dispatched when a brand-new user row was created by a Google login that passed every check (verified email, domain rules, `hd`). It fires before the login, never when linking an existing row or on later logins.

| Property | Type | Content |
|---|---|---|
| `$event->user` | `Model` | The new row, saved but not yet logged in |
| `$event->googleUser` | `Laravel\Socialite\AbstractUser` | Raw Google profile (`getRaw()` holds `hd`, `picture`, `locale`, ...) |
| `$event->hostedDomain` | `string` | Lowercased `hd` claim, `''` for personal accounts |

`$event->isFromAllowedDomain(): bool` holds the check of the default listener: `domains.allowed` is not empty, and both the email domain and `hd` are in it. Call it instead of copying that check.

**Use another role library.** Extend the default listener and override only `assignRole()`. The domain check and the role name from config stay in the package:

``` php
namespace App\Auth;

use Illuminate\Database\Eloquent\Model;
use Statikbe\GoogleAuthenticate\Listeners\GrantSuperAdminRole;

class GrantSuperAdminWithBouncer extends GrantSuperAdminRole
{
    protected function assignRole(Model $user, string $roleName): void
    {
        Bouncer::assign($roleName)->to($user);
    }
}
```

Put the subclass in `app/Auth/`, not `app/Listeners`. Laravel event discovery registers every class in `app/Listeners` that has a typed `handle` method, so a subclass there would run twice (discovered, and registered from the config), and `null` would not turn it off.

Register it in the published config, not with `Event::listen`:

``` php
'super_admin_listener' => \App\Auth\GrantSuperAdminWithBouncer::class,
```

The package checks at boot that the class exists. The spatie checks (`HasRoles`, teams) live in the default `assignRole()`, so your subclass does not inherit them and does not need spatie. The gate calls spatie's `hasRole` (under the user model's guard), so it skips users without `HasRoles`. Set `GOOGLE_AUTH_SUPER_ADMIN_GATE=false` and use the other library's own super admin check.

**Check the domain elsewhere.** The `GoogleAuthenticate` facade gives policies, seeders or Filament's `canAccessPanel()` the same rule the package uses:

``` php
use Statikbe\GoogleAuthenticate\Facades\GoogleAuthenticate;

GoogleAuthenticate::isAllowedDomain($user->email);            // email domain only
GoogleAuthenticate::isAllowedDomain($email, $hostedDomain);   // email domain and hd
```

It returns `true` only when `allowed` is not empty, the email domain is not in `disabled`, and the email domain and `hd` (when given) are in `allowed`. The comparison ignores case.

**Replace the default.** Publish the config, set `'super_admin_listener' => null`, and add your own listener:

``` php
namespace App\Listeners;

use Statikbe\GoogleAuthenticate\Events\GoogleUserRegistered;

class GrantAdminToOrganisation
{
    public function handle(GoogleUserRegistered $event): void
    {
        if (! $event->isFromAllowedDomain()) {
            return;
        }

        // Any role system works here; this one uses a column.
        $event->user->forceFill(['is_admin' => true])->save();
    }
}
```

The gate keeps working for the role in `GOOGLE_AUTH_SUPER_ADMIN_ROLE`, so a custom listener that grants that role needs no `Gate::before` of its own.

**Add to the default.** Keep the default role and add a listener for the extra work. Both run in the same transaction:

``` php
public function handle(GoogleUserRegistered $event): void
{
    $role = match ($event->hostedDomain) {
        'statik.be' => 'developer',
        'client.be' => 'editor',
        default => null,
    };

    if ($role && $event->isFromAllowedDomain()) {
        $event->user->assignRole($role);
    }
}
```

**Revoke old sessions and tokens on a link.** `Statikbe\GoogleAuthenticate\Events\GoogleAccountLinked` is dispatched, inside the login transaction, every time an existing row is linked to a Google account (matched by email, no Google id yet). That row may have sessions or API tokens from before the Google identity was proven. Revoke them in a listener:

| Property | Type | Content |
|---|---|---|
| `$event->user` | `Model` | The linked row, already saved |
| `$event->googleUser` | `Laravel\Socialite\AbstractUser` | Raw Google profile |

``` php
namespace App\Listeners;

use Illuminate\Support\Facades\DB;
use Statikbe\GoogleAuthenticate\Events\GoogleAccountLinked;

class RevokeAccessOnLink
{
    public function handle(GoogleAccountLinked $event): void
    {
        DB::table('sessions')->where('user_id', $event->user->getKey())->delete(); // database session driver
        $event->user->tokens()->delete();                                          // Sanctum, when used
    }
}
```

The package already rotates the `remember_token` and clears the `password`. To end sessions of other devices on every request instead, add Laravel's `Illuminate\Session\Middleware\AuthenticateSession` to your `web` group: it logs out sessions whose stored password hash no longer matches, and the link nulls the password. A throwing listener rolls the link back and aborts the login.

**The event carries the OAuth access and refresh token** (`$event->googleUser->token`, `->refreshToken`). Never queue a listener that receives `GoogleAccountLinked` or `GoogleUserRegistered` as-is: the tokens would end up in the queue payload. Dispatch a job with the values you need instead.

**Registration.** Laravel finds listeners in `app/Listeners` through the typed `handle` parameter. Outside that folder, or with discovery off, register it in `AppServiceProvider::boot()`:

``` php
Event::listen(GoogleUserRegistered::class, GrantAdminToOrganisation::class);
```

### Rules for every listener
- Grant privileged roles only from this event. Never grant them from a model event (`User::created`), the `provider` column or an email domain alone. Users created in a panel, seeder or factory would qualify too.
- Always check `isFromAllowedDomain()` (or the `hd` claim yourself) before a privileged grant. An email domain alone does not prove Workspace membership.
- An exception aborts the login and rolls back the new row, so the next login retries cleanly. Throw on purpose to block a login. Never swallow errors: a half-run listener does not run again on the next login.
- Do not queue the listener (`ShouldQueue`). It would run after the commit, outside the rollback, and the user would log in before the role exists.
- Tests: fire the event with a factory user and a `Laravel\Socialite\Two\User` that has `hd` set via `setRaw`, or run the full callback the way `tests/Feature/GoogleCallbackTest.php` does.

## Laravel Boost skill
The package ships a [Laravel Boost](https://laravel.com/docs/boost) skill, `google-authenticate-upgrade`, that walks an agent through upgrading a 3.x, 4.x or 5.x project to 6.0 and removes the Google-auth code a project no longer needs. Boost discovers it from `resources/boost/skills` of your installed packages: run `php artisan boost:install` (first time) or `php artisan boost:update` (after updating this package), select the skill, then ask your agent to upgrade Google Authenticate. Boost only offers skills of direct composer requirements. Boost is not a dependency of this package.

## Publishing
You can publish the config, the migration and the translations:
``` shell
php artisan vendor:publish --provider="Statikbe\\GoogleAuthenticate\\GoogleAuthenticateServiceProvider" --tag="google-config"
php artisan vendor:publish --provider="Statikbe\\GoogleAuthenticate\\GoogleAuthenticateServiceProvider" --tag="google-migrations"
php artisan vendor:publish --provider="Statikbe\\GoogleAuthenticate\\GoogleAuthenticateServiceProvider" --tag="google-lang"
```

## Security

If you discover any security related issues, please email [info@statik.be](mailto:info@statik.be) instead of using the issue tracker.

## License

The MIT License (MIT). Please see [License file](license.md) for more information.
