# Changelog

All notable changes to `GoogleAuthenticator` will be documented in this file.

## Version 6.0.0

### Added
- Zero-config install: `services.google` defaults from `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET` (redirect `/login/google/callback`), merged by the package and safe with `config:cache`
- The migration loads automatically, skips columns that already exist, makes `users.password` nullable when needed, and its `down()` does nothing
- The migration uses the table of the configured user model, fails with a clear message when that table is missing, keeps a custom `password` column length, and adds a unique index on `provider_id` unless one exists or duplicates are present (then it skips)
- Default listener `Statikbe\GoogleAuthenticate\Listeners\GrantSuperAdminRole`: grants the `super_admin` role to new users whose email domain and `hd` are both in `domains.allowed`. The role-library code sits in `protected assignRole()`, so a subclass can swap spatie for another library. Set the class in `super_admin_listener`, or `null` to turn it off
- `Gate::before` for the super admin role, checked under the user model's own guard, with `GOOGLE_AUTH_SUPER_ADMIN_GATE` to turn it off
- `GoogleAuthenticate` facade with `isAllowedDomain(string $email, ?string $hostedDomain = null)`
- `GoogleUserRegistered::isFromAllowedDomain()`
- Rate limit on both Google routes: config `throttle` / env `GOOGLE_AUTH_THROTTLE`, default `10,1` (10 requests per minute per IP), empty disables it
- `Statikbe\GoogleAuthenticate\Events\GoogleAccountLinked` (`$user`, `$googleUser`), dispatched inside the login transaction whenever an existing row is linked to a Google account. Revoke old sessions and API tokens there
- Refused logins are logged with `Log::warning` (Google `sub` and reason, never the email). The skipped super admin grant under spatie teams is logged too
- Config keys `domains.allow_any`, `super_admin_role`, `super_admin_listener`, `super_admin_gate` and env vars `GOOGLE_AUTH_ALLOWED_DOMAINS`, `GOOGLE_AUTH_ALLOW_ANY_DOMAIN`, `GOOGLE_AUTH_SUPER_ADMIN_ROLE`, `GOOGLE_AUTH_SUPER_ADMIN_GATE`

### Changed
- Unexpected exceptions in the callback are reported with `report()`, except an invalid OAuth state (`InvalidStateException`)
- A successful login redirects with `redirect()->intended(redirect_url)`
- The service works with the configured user model, not `App\Models\User`
- Config moved to `config/`, routes to `routes/web.php`. Config keys are unchanged
- `spatie/laravel-permission` (`^6.0|^7.0`) is a suggested package, not required
- Readme and upgrade guide rewritten for the 3-step install
- A linked user whose Google email changes to an email another row already has is refused (error code 403, generic login error) instead of overwriting
- `provider_id` lookups match rows with `provider = google` or a NULL `provider`, never another provider; an email owned by another provider's row is refused, and email lookups use the mapped email column, case-insensitively
- `register_enabled` is read at call time, not once per container
- A parallel double callback no longer fails: the service reuses the row the other request created when a unique index on `provider_id` rejects the insert
- The empty `domains.allowed` misconfiguration is reported at most once per hour
- `GOOGLE_AUTH_ALLOWED_DOMAINS` entries are trimmed
- Return URLs with backslashes, control characters or user info are refused

### Removed
- The `HasGoogleAuth` trait
- The views and the `google-views` publish tag
- Laravel 10 support (needs Laravel `^11|^12|^13`, PHP `^8.2`)
- `findOrCreate`, `createUser` and the `GOOGLE_VALUES` constant: one lookup flow replaces them

### Breaking
- Remove `use HasGoogleAuth` from your `User` model
- Classes moved: `Services\GoogleAuthService` is now `GoogleAuthService`, `GoogleAuthenticateController` is now `Http\GoogleAuthController`
- Subclasses that override `findOrCreate` or `createUser` break: use a `GoogleUserRegistered` listener
- The default listener is on by default and grants `super_admin` to new users from allowed domains. Publish the config and set `'super_admin_listener' => null` to turn it off
- An empty `domains.allowed` refuses new users, unless `GOOGLE_AUTH_ALLOW_ANY_DOMAIN=true`. Existing users still log in
- With spatie installed, a `User` model without `HasRoles` makes new Google logins fail. With spatie teams on, the default listener grants nothing: use a custom listener
- The package registers a `Gate::before`: a super admin passes every ability, also policies that deny
- The login routes use the `guest` middleware
- A successful login uses `redirect()->intended()`
- Route names `google.auth.login` and `google.auth.callback` are unchanged

See the [upgrade guide](upgrade-guide.md) for the action per item.

## Version 5.0.0
- **Breaking:** Google accounts with an unverified email are refused for every login and registration (previously only when linking)
- **Breaking:** when `domains.allowed` is set, the Google `hd` (Workspace hosted domain) claim must also match; personal accounts are refused
- **Breaking:** login no longer sets a remember-me cookie by default; use the new `remember` config option to restore it
- **Breaking:** an existing row that matches by email is only linked to a Google account when the `hd` claim equals the email domain, or the email domain is `gmail.com` / `googlemail.com`; otherwise the login is refused
- **Breaking:** linking an existing row sets `password` to `null` (when the column exists) and rotates `remember_token`
- **Breaking:** `createUser(array $userData, bool $emailVerified)` is now `createUser(array $userData, AbstractUser $googleUser)`; the unreachable `$emailVerified` flag is gone
- Added the `Statikbe\GoogleAuthenticate\Events\GoogleUserRegistered` event (`$user`, `$googleUser`, `$hostedDomain`), dispatched after a new user row was created by a fully verified Google login, before the login. Not dispatched when linking or on later logins; a throwing listener aborts the login
- `findOrCreate` and the `GoogleUserRegistered` dispatch now run in one database transaction: a throwing listener rolls back the new user, so the next login registers it again instead of leaving an orphan
- All security checks (verified email, domain allow/disable, `hd`) now run in a private method before `findOrCreate`, so overriding `findOrCreate` / `createUser` cannot skip them
- Added a Laravel Boost skill (`google-authenticate-upgrade`) that guides an agent through the 3.x/4.x to 5.0 upgrade and checks the hardening around super admin grants (listener on `GoogleUserRegistered`, `HasPanelShield`, self-service account deletion, empty `domains.allowed`, seeders, remember tokens)
- `findOrCreate` and `createUser` are now `protected` so they can be overridden
- **Breaking:** the domain logic moved from `GoogleAuthenticateController` to `Statikbe\GoogleAuthenticate\Services\GoogleAuthService` (`GOOGLE_VALUES`, `getUserModel`, `findOrCreate`, `createUser`, ...). Subclasses of the controller that override these must extend `GoogleAuthService` instead and rebind it in a service provider. `authenticate()` is `final`, so the checks cannot be skipped
- **Breaking:** removed the unused `'GoogleAuthenticate'` container binding (it returned a controller instance)
- **Breaking:** `GoogleAuthenticateController::handleProviderCallback()` now takes the service by method injection: `handleProviderCallback(GoogleAuthService $service): RedirectResponse`. Subclasses overriding it must match the signature

## Version 4.6.3
- Fixed existing users being rejected when `allowed` domains are set and registration is disabled
- Register-disabled logins now require a verified Google email and are bound to the stored `provider_id`
- Domain checks are case-insensitive; `allowed` applies to existing users too
- Fixed a 500 error when registration is enabled and a Google account signs in with an email already linked to another Google account; the login is now rejected
- `register_enabled` defaults to `true` when missing from a published config (previously threw a TypeError)
- Added a test suite

## Version 4.6.2
- Added the `register_enabled` config option to disable registering new users via Google
- Login return URL is restricted to same-host URLs (open redirect fix)
- Session is regenerated after login
- Migration stub no longer changes the `email` column; `down()` keeps `password` nullable
- `GoogleAuthenticationException` defaults to a 403 code

## Version 4.6.1
- Added some small optimisations
## Version 4.6.0
- Added support for Laravel 13
## Version 4.5.2
- Don't update email_verified_at on login
## Version 4.5.1
- Added default allowed domains
## Version 4.5.0
- Added support for Laravel 12
- Removed support for Laravel 9
## Version 4.4.1
- Fixed User model creation in Google authenticateController
## Version 4.4.0
- Added support for custom middleware on routes
## Version 4.3.0
- Added support for Laravel 11
- Updated group names for publishing config/views/etc
## Version 4.2.0
- Added support for Laravel 10
- Added return types
- Removed support for Laravel 8
## Version 4.1.3
- Added banner image in readme
## Version 4.1.2
- Prevent loading the views,should only be used for publishing. This fixes an issue with caching.
## Version 4.1.1
- Fixed lang publishing for laravel 9
## Version 4.1.0
- Updated version illumninate/support to use Laravel 8  or 9
- Updated auth templates with new format from Laravel + google login button
- Removed Laravel support for version 6 and 7
- Removed google/apiclient
## Version 4.0.0
### Updated
- Updated structure to be more consistent with other packages
- Updated upgrade-guide.md file for new version
- Removed `spatie/laravel-permission` implementation and dependency
  - This makes the `GoogleAuthenticator` less opinionated
  - Assigning roles can be done on the "created" method of your user
## Version 3.3.1
### Updated
- Updated version for laravel/socialite & google/apiclient
## Version 3.3.0
### Updated
- Updated version for illumninate/support to work with php 8*
## Version 3.2.1
### Changed
- Removed the `getLogout` action as it was not used
- Removed the `/logout` route as it would interfere with the base laravel route.
## Version 3.2.0
### Updated
- User model will now check which namespace is used for the model and then creates a user from there
## Version 3.1.2
### Updated
- Updated version for illumninate/support to work with php 7*

## Version 3.1.0
### Changed
- Switched the `user_columns` array key and value.
### Added
- Comments in our Controller
- `!` option for domains to make them be ignored: f.e. `!statik.be` will ignore 'statik.be' addresses.
- The use of multiple google values for 1 user table.
- Login with google even if the user account pre-existed (based on email)

## Version 3.0.1
### Changed
- Fixed markdown typos
- Removed statik.be from roles array in config file

## Version 3.0.0
### Changed
- Rewrote config file to revive it's relevance
- Moved following values from .env to config:
```    
    - AUTH_ROLE_ADMIN="admin"
    - AUTH_ROLE_DOMAIN="statik.be"
    - AUTH_REDIRECT_URL = "/admin"
```
- Renamed Statikbe to statikbe (in the service provider)
- Changed publish views tag from GoogleAuthenticate:views to laravel-google-authenticate.views
### Added
- Multiple roles support
- Multiple domains support
- Custom user table columns support

## Version 2.0.3
### Changed
- Fixed bug in authentication check on role

## Version 2.0.2
### Changed
- Added nullable to provider and provider_id columns
- Fixed readme for publishing migration and config

## Version 2.0.1
### Changed
- Updated composer package illuminate/support for Laravel 6

## Version 2.0.0
### Added
- Added the config file
- Auto publish config file
- Added names for routes & use them in overwritten auth views
### Changed
- Updated packages to work with laravel 6
- Cleaned up some code

## Version 1.0.9
### Added
- Added a way to change the redirect url after login

## Version 1.0.8
### Added
- Correct namespace class for Controller extend

## Version 1.0.7
### Added
- Added the values that need to be in the services.php class
- Changed Controller route namespacing

## Version 1.0.6
### Removed
- Removed column from migration, should not have been there in the first place

## Version 1.0.5
### Changed
- Added dependency for doctrine/dbal
- Fixed migration for users

## Version 1.0.4
### Changed
- Changed the name of the migration file
- Typo in readme

## Version 1.0.3
### Changed
- Made some fixes to the readme

## Version 1.0.2
### Removed
- Removed unrelated string

## Version 1.0.1
### Changed
- rework of the migration load in the ServiceProvider

## Version 1.0
### Added
- Everything