# Upgrade Guide

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
  - Remove the view files situated in `resources/vie ws/vendor/statikbe`
- Translations
  - Copy your translations from the files in the  `lang/vendor/statikbe` folder and paste them in the newly created files made in the `lang/vendor/google-authenticate` folder
  - Remove the `lang/vendor/statikbe` folder



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
