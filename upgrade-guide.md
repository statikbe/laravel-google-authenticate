# Upgrade Guide

---

##This will guide you through upgrading `GoogleAuthenticator` from version 2 to version 3. 

<a name="requirements"></a>
### Requirements

- Laravel 5.8 installed
- Statikbe\laravel-google-authenticate 2.x installed

<a name="upgrade-steps"></a>
### Upgrade Steps

1. Update your ```composer.json``` file to require ```"statikbe/laravel-google-authenticate": "^3.1.0"```. Then run ```composer update```.

2. In your ```config/google-auth.php``` update to the correct version; [here's you can find the 3.1.0 version](https://github.com/statikbe/laravel-google-authenticate/blob/3.1.0/src/config/google-auth.php). You will have to change how you want your roles to be granted using the `roles` array. This will include adding domains to the array or leaving the array empty if anyone can receive the role.

3. Remove `AUTH_ROLE_ADMIN`, `AUTH_ROLE_DOMAIN` and `AUTH_REDIRECT_URL` from you `.env` file. (You can add these values to the config file you just updated.)

4. the vendor views mpa has been changed from `Statikbe` to `statikbe`. (This is only relevant if you published the package views before.) 

5. Done!

# How to upgrade from v3.3.1 to v4

## Step 1
first of all you need to republish some files. This can be done with the following files
#### Config

```shell
php artisan vendor:publish --provider="Statikbe\\GoogleAuthenticate\\GoogleAuthenticateServiceProvider" --tag="config"
```

### Views & Translations
You can publish the views and translations files using:
``` shell
php artisan vendor:publish --provider="Statikbe\\GoogleAuthenticate\\GoogleAuthenticateServiceProvider" --tag="views"
```
and
``` shell
php artisan vendor:publish --provider="Statikbe\\GoogleAuthenticate\\GoogleAuthenticateServiceProvider" --tag="lang"
```

## Remove obsolete files
- Config parameters
    - First of all you can copy the content of your `google-auth.php` file to the `google-authenticate.php` file
    - Then you can remove the `google-auth.php` file
- Templates
    - Copy the `home.blade.php`, `login.blade.php` & `register.blade.php` files from the `resources/views/vendor/statikbe`folder to the newly created
      folder `resources/view/vendor/google-authenticate` folder
    - Remove the view files situated in ```resources/vie ws/vendor/statikbe```
- Translations
    - Copy your translations from the files in the  `lang/vendor/statikbe` folder and paste them in the newly created files made in the `lang/vendor/google-authenticate` folder
    - Remove the `lang/vendor/statikbe` folder 

