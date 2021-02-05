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
