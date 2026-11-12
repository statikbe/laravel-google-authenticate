# Upgrade Guide

Using Laravel Boost? After `composer update`, run `php artisan boost:update` and ask your agent to use the `google-authenticate-upgrade` skill.

---
## How to upgrade from v5.x to v6.0.0

Using Boost? The `google-authenticate-upgrade` skill does most of this for you.

6.0 removes the code a project had to write itself. After the upgrade, a project has no Google-auth code left, except an optional custom listener. The route names `google.auth.login` and `google.auth.callback` are unchanged, so your login buttons keep working.

Update `composer.json` to `"statikbe/laravel-google-authenticate": "^6.0"`, then go through the list.

### Breaking changes

1. **`HasGoogleAuth` trait removed.** Delete `use HasGoogleAuth` (and its import) from your `User` model. Nothing replaces it: the service sets `provider` and `provider_id` with `forceFill`. Also remove `provider` and `provider_id` from `$fillable` if you added them.
2. **`spatie/laravel-permission` is optional.** The package only suggests it (`^6.0|^7.0`). Without it, login works and no role is granted. Install it yourself when you want the super admin role.
3. **The listener `GrantSuperAdminRole` is on by default.** It grants the role `super_admin` (`GOOGLE_AUTH_SUPER_ADMIN_ROLE`) to new users whose email domain and `hd` claim are both in `domains.allowed`. Use another role name through the env var. To turn the listener off, publish the config and set the listener to `null`:
   ``` shell
   php artisan vendor:publish --provider="Statikbe\\GoogleAuthenticate\\GoogleAuthenticateServiceProvider" --tag="google-config"
   ```
   ``` php
   'super_admin_listener' => null,
   ```
   There is no env var for the listener. If you have a project listener that grants the same role (for example `GrantSuperAdminToOrganisation`), delete it, or set the listener to `null` and keep yours. Its inline domain check can become `$event->isFromAllowedDomain()`. Never run two listeners that grant different roles.
4. **An empty `domains.allowed` refuses new users.** Set `GOOGLE_AUTH_ALLOWED_DOMAINS` (comma separated). For a public app that accepts every Google account, set `GOOGLE_AUTH_ALLOW_ANY_DOMAIN=true`. Existing users still log in. Remove the 5.0 guard from `AppServiceProvider::boot()`: the package now checks this at login, so it no longer breaks artisan commands.
5. **Missing `HasRoles`, or spatie teams on.** When spatie is installed and the `User` model lacks `Spatie\Permission\Traits\HasRoles`, a new Google login fails with a `LogicException` and the new row is rolled back. Add the trait. When `permission.teams` is on, the default listener grants nothing and the login goes on. The package does not support teams: write a custom listener (see the readme).
6. **Laravel 10 is dropped.** The package needs Laravel `^11|^12|^13` and PHP `^8.2`. Laravel 10 projects stay on 5.x.
7. **Classes moved.** Update imports and routes:
   - `Statikbe\GoogleAuthenticate\Services\GoogleAuthService` is now `Statikbe\GoogleAuthenticate\GoogleAuthService`.
   - `Statikbe\GoogleAuthenticate\GoogleAuthenticateController` is now `Statikbe\GoogleAuthenticate\Http\GoogleAuthController`.
8. **`findOrCreate` and `createUser` are merged.** Subclasses that override them break. Move that logic into a listener on `GoogleUserRegistered`. Column mapping belongs in `user_columns`. Delete subclasses of the service or controller, and their container bindings.
9. **The package registers a `Gate::before`.** A user with the super admin role passes every ability, also policies that deny. The role is checked under the user model's own guard, so a role with the same name under another guard does not count. This is the case even when Filament Shield uses `intercept_gate => 'after'`. A project `Gate::before` that only checks this role can go. Set `GOOGLE_AUTH_SUPER_ADMIN_GATE=false` to keep the role limited to its assigned permissions.
10. **Views and the `google-views` publish tag are removed.** The views were never rendered. Delete `resources/views/vendor/google-authenticate`. Publishing `google-lang` and `google-config` still works.
11. **Routes get the `guest` middleware.** A logged-in user who opens `google.auth.login` is redirected instead of sent to Google.
12. **A successful login uses `redirect()->intended(redirect_url)`.** A user sent to login from a protected page lands back there. Otherwise the `redirect_url` config applies. A controller subclass that only did this can be deleted.
13. **Config moved from `src/config` to `config/`.** The keys are unchanged, so a published `config/google-authenticate.php` keeps working. New keys: `domains.allow_any`, `super_admin_role`, `super_admin_listener`, `super_admin_gate`.

### Also good to know
- **Zero config.** `services.google` now has defaults from `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET`, with the redirect `/login/google/callback`. Keep your own `services.google` block when its redirect or keys differ: your values win. The `CALLBACK_URL_GOOGLE` env var is no longer required: keep it if your `config/services.php` already uses it.
- **Migration.** It loads automatically and skips columns that already exist. It also makes `users.password` nullable when it is NOT NULL (Google-only users have no password). Its `down()` does nothing, so a rollback never drops `provider` or `provider_id`. Keep your published 5.x migration: it already ran. Then publish the 6.0 migration with `php artisan vendor:publish --tag=google-migrations` (skip this when `database/migrations/*_add_google_provider_to_users_table.php` already exists). The copy gets today's date, and `php artisan migrate` runs it. The package also loads the same migration itself; both runs skip what already exists. It also adds a unique index on `provider_id`, so a double callback cannot create two rows for one Google account. The index is skipped (the migration does not fail) when your table already has a unique index on `provider_id` or holds duplicate values. To get the index after cleaning up, find the duplicates with `select provider_id, count(*) from users where provider_id is not null group by provider_id having count(*) > 1`, delete or null the extra rows and add the index yourself with `$table->unique('provider_id')` in a migration of your own. The migration also fails with a clear message when the users table of `auth.providers.users.model` does not exist yet: run it after the migration that creates that table.
- **Email change.** A linked user whose Google email changes to an email another row already has is refused (error code 403, generic login error) instead of overwriting.
- **Facade.** `GoogleAuthenticate::isAllowedDomain($email, ?$hd)` gives policies and `canAccessPanel()` the same domain rule the package uses at login. See the readme.
- **Other role libraries.** Extend `Statikbe\GoogleAuthenticate\Listeners\GrantSuperAdminRole`, override `assignRole()` and set your class as `super_admin_listener` in the published config.
- **Rate limit.** Both Google routes now carry `throttle:10,1` (10 requests per minute per IP). Set `GOOGLE_AUTH_THROTTLE=` (empty) to disable it, or a value like `20,1` to change it.
- **New event method.** `GoogleUserRegistered::isFromAllowedDomain()` replaces the domain check in custom listeners.
- **Account link event.** `GoogleAccountLinked` fires inside the login transaction when an existing row is linked to Google. Listen to it to revoke sessions and API tokens issued before the link, and add `Illuminate\Session\Middleware\AuthenticateSession` to the `web` group to end other devices' sessions (see the readme). Never queue a listener of this event or of `GoogleUserRegistered`: the event holds the OAuth tokens.
- **Stricter lookups.** `provider_id` is matched only on rows with `provider = google` or a NULL `provider` (older installs, imports). A row with another provider (for example `github`) is never matched by a Google id, and a Google login whose email belongs to such a row is refused. Emails are matched case-insensitively.
- **Logging.** Refused logins write a `Log::warning` with the Google `sub` and the reason (never the email).

---
## How to upgrade from v4.x to v6.0.0

Do the 4.x to 5.0 steps below first (remember tokens, linking rules, `GoogleUserRegistered`), then the 5.x to 6.0 list above.

A 4.x project often grants roles in a model observer or in `User::created` (see the v3 to v4 notes below). That is not safe: users created in a panel, a seeder or a factory would get the role too. Replace it with a listener on `GoogleUserRegistered`:

``` php
namespace App\Listeners;

use Statikbe\GoogleAuthenticate\Events\GoogleUserRegistered;

class GrantAdminToOrganisation
{
    public function handle(GoogleUserRegistered $event): void
    {
        if ($event->isFromAllowedDomain()) {
            $event->user->assignRole('admin');
        }
    }
}
```

Delete the observer. If the role is `super_admin`, you can skip the listener: the default one grants it. Otherwise set `'super_admin_listener' => null` in the published config and keep your own listener.

---
## How to upgrade from v4.x to v5.0.0

1. **Clear existing remember tokens.** Logins are no longer remembered by default, but tokens issued earlier keep working until they are cleared:
   ``` php
   User::query()->update(['remember_token' => null]);
   ```
   Set `'remember' => true` in `config/google-authenticate.php` if you want the old behaviour.
2. **Never grant roles or privileges from the `provider` column.** `provider` / `provider_id` stay mass-assignable (`HasGoogleAuth` is unchanged), so a user-controlled payload can set them. Use the `GoogleUserRegistered` event instead (see 6).
3. **Linking rules.** An existing user matched by email is only linked when the Google `hd` claim equals the email domain, or the email is on `gmail.com` / `googlemail.com`. Linking also sets `password` to `null` and rotates `remember_token`. Users with a custom domain on a non-Workspace Google account can no longer take over an existing row.
4. **Unverified emails** are refused everywhere, and with `domains.allowed` set the `hd` claim must match too.
5. **Overrides.** The domain logic moved from `GoogleAuthenticateController` to `Statikbe\GoogleAuthenticate\Services\GoogleAuthService`. If you extended the controller to override `findOrCreate` / `createUser`, extend `GoogleAuthService` instead and rebind it in a service provider: `$this->app->bind(GoogleAuthService::class, App\Services\MyGoogleAuthService::class);`. The `createUser` signature is now `createUser(array $userData, AbstractUser $googleUser): User`. The security checks no longer live in `findOrCreate`; `authenticate()` runs them first and is `final`, so they cannot be overridden. `GOOGLE_VALUES` and `getUserModel()` moved to the service too. The `'GoogleAuthenticate'` container binding is removed: resolve `GoogleAuthService::class` instead. Overrides of `handleProviderCallback()` must now match `handleProviderCallback(GoogleAuthService $service): RedirectResponse`. An app with its own Google controller should call `$service->authenticate($socialiteUser)` instead of copying checks, then `Auth::login()` and `session()->regenerate()`.
6. **New event.** Listen to `Statikbe\GoogleAuthenticate\Events\GoogleUserRegistered` (`$user`, `$googleUser`, `$hostedDomain`) to act on freshly registered users, e.g. to assign a role. It only fires after every check passed, only for newly created rows, and before the login. Throw to abort the login.
7. **Harden super admin grants.** Grant privileged roles only in a `GoogleUserRegistered` listener that requires both the email domain and `$event->hostedDomain` to be in a non-empty `domains.allowed`. Never from a model event, the `provider` column or an email domain alone (users created in a panel, seeders or factories would qualify). Also review `HasPanelShield` (auto-assigns `panel_user` to every new user), self-service account deletion (a deleted user is re-created and re-granted on the next Google login) and seeders with default passwords. The Boost skill checks all of this.

---
## How to upgrade from v4.0.0 to v4.1.0

### Step 1: Publishing
#### Config

#### Views
``` shell
php artisan vendor:publish --provider="Statikbe\\GoogleAuthenticate\\GoogleAuthenticateServiceProvider" --tag="views"
``` 

### Step 2: Copy new files
- Templates
  - Remove the home.blade.php file
  - Copy the dashboard.blade.php, login.blade.php & register.blade.php files to the views/auth folder.
---
## How to upgrade from v3.3.1 to v4

Update your ```composer.json``` file to require ```"statikbe/laravel-google-authenticate": "^4.0"```.

### Step 1: Publishing
If you published any of the v3 files you can republish them to get the renamed versions.
This can be done with the following commands:
#### Config
```shell
php artisan vendor:publish --provider="Statikbe\\GoogleAuthenticate\\GoogleAuthenticateServiceProvider" --tag="config"
```

#### Views
``` shell
php artisan vendor:publish --provider="Statikbe\\GoogleAuthenticate\\GoogleAuthenticateServiceProvider" --tag="views"
``` 

#### Translations
``` shell
php artisan vendor:publish --provider="Statikbe\\GoogleAuthenticate\\GoogleAuthenticateServiceProvider" --tag="lang"
```

### Step 2: Remove obsolete files
- Config parameters
  - Copy the relevant content (changes you might have made) of `google-auth.php` to `google-authenticate.php`
  - Remove `google-auth.php`
- Templates
  - Copy the `home.blade.php`, `login.blade.php` & `register.blade.php` files from the `resources/views/vendor/statikbe` folder to the newly created
    folder `resources/view/vendor/google-authenticate` folder
  - Remove the view files situated in `resources/views/vendor/statikbe`
- Translations
  - Copy your translations from the files in the  `lang/vendor/statikbe` folder and paste them in the newly created files made in the `lang/vendor/google-authenticate` folder
  - Remove the `lang/vendor/statikbe` folder

### Step 3: Roles (optional)
We are not using the roles in version 4 anymore. This is to keep the package less opinionated. 
If you were using the roles setting in version 3 you might want to move that logic to the `created` event of your user. 

For example: 

``` php 
    //Add this in your Auth model
    protected static function boot()
    {
        parent::boot();

        self::created(function(User $model){
            //get user's mail domain
            $emailArray = explode('@', $model->email);
            $emailDomain = $emailArray[1];

            switch ($emailDomain){
                case 'statik.be':
                    $model->assignRole(\Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin']));
                //Add any other domain or role to the switch
            }
        });
    }
```
**WARNING** This code is not secure if you are not validating emails. If this is the case you should first check if the registration came from a social login before assigning a role.

---


## How to upgrade from version 2 to version 3. 

<a name="requirements"></a>
### Requirements

- Laravel 5.8 installed
- Statikbe\laravel-google-authenticate 2.x installed

<a name="upgrade-steps"></a>
### Upgrade Steps

1. Update your ```composer.json``` file to require ```"statikbe/laravel-google-authenticate": "^3.1.0"```. Then run ```composer update```.

2. In your ```config/google-auth.php``` update to the correct version; [here you can find the 3.1.0 version](https://github.com/statikbe/laravel-google-authenticate/blob/3.1.0/src/config/google-auth.php). You will have to change how you want your roles to be granted using the `roles` array. This will include adding domains to the array or leaving the array empty if anyone can receive the role.

3. Remove `AUTH_ROLE_ADMIN`, `AUTH_ROLE_DOMAIN` and `AUTH_REDIRECT_URL` from you `.env` file. (You can add these values to the config file you just updated.)

4. the vendor views map has been changed from `Statikbe` to `statikbe`. (This is only relevant if you published the package views before.) 

5. Done!
