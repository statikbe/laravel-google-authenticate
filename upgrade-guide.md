# Upgrade Guide

Using Laravel Boost? After `composer update`, run `php artisan boost:update` and ask your agent to use the `google-authenticate-upgrade` skill.

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
5. **Overrides.** If you override `createUser`, the signature is now `createUser(array $userData, AbstractUser $googleUser): User`. The security checks no longer live in `findOrCreate`; they run before it and cannot be overridden.
6. **New event.** Listen to `Statikbe\GoogleAuthenticate\Events\GoogleUserRegistered` (`$user`, `$googleUser`, `$hostedDomain`) to act on freshly registered users, e.g. to assign a role. It only fires after every check passed, only for newly created rows, and before the login. Throw to abort the login.

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
