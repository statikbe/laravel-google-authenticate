# GoogleAuthenticator

This module gives you the option to let you (and your users) log in with Google on your Laravel application.

[Changelog](changelog.md) - [Upgrade guides](upgrade-guide.md)

---

## Installation

Using Composer

``` bash
$ composer require statikbe/laravel-google-authenticate
```

## Usage

The package will automatically register itself.

You can publish the migration with the following command:
``` shell
php artisan vendor:publish --provider="Statikbe\GoogleAuthenticate\GoogleAuthenticateServiceProvider" --tag="migration"
```

To add the needed columns to your database run:
 ``` shell
php artisan migrate
``` 

Add the ```use HasGoogleAuth``` trait in your ```User.php``` class.
This will provide the necessary fillable options to your User.

In your .env file you should include the following keys:
``` php
GOOGLE_CLIENT_ID="YOUR_GOOGLE_CLIENT_ID"
GOOGLE_CLIENT_SECRET="YOUR_GOOGLE_CLIENT_SECRET"
CALLBACK_URL_GOOGLE="https://www.domain.com/login/google/callback"
```

The next step is to add the following lines in your ```services.php``` config file
``` php
'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('CALLBACK_URL_GOOGLE'),
    ],
```

Info on how to create a Google Auth Client id and secret can be
found [on their documentation page](https://developers.google.com/identity/protocols/OAuth2).

Finally, you can add google login route to your login and register views: `{{ route('google.auth.login') }}`.


### Config
Publish the config file

```bash
php artisan vendor:publish --provider="Statikbe\\GoogleAuthenticate\\GoogleAuthenticateServiceProvider" --tag="config"
```

#### Email domains
You can change the email domains that can login using Google. The three available options are:
- `allowed`-array: only the domains in this array can login using Google
- `disabled`-array: domains in this array can not login using Google
- Empty / null: all domains can use the Google login

``` php
    'domains' => [
        //'allowed' => ['statik.com'],
        //'disabled' => ['google.com'],
    ],
```

#### User table
You can customize how a user is saved. The config array `user_columns` will create the fillable data for your user. 
The array keys are your user column names, the array values are what should be stored. (Make sure the value is an array).
You can add multiple values per key, these will be glued together.
The following values would be filled by google's returned data, before being glued.

``` php
const GOOGLE_VALUES = [
        'name',
        'email_verified',
        'email',
        'given_name',
        'family_name',
        'picture',
        'nickname',
        'locale',
];
```
For example in your config:
``` php
'user_columns' => [
        'name' => ['name', ' (', 'locale', ')']     // John Doe (en)
        'email_verified_at' => ['email_verified'],  // 2019-10-23 14:31:50
        'email' => ['email'],                       // john@doe.com
        'other data' => ['blablabla'],              // blablabla
]
```

## Publishing
You can publish the views and translations files using:
``` shell
php artisan vendor:publish --provider="Statikbe\\GoogleAuthenticate\\GoogleAuthenticateServiceProvider" --tag="views"
```
and 
``` shell
php artisan vendor:publish --provider="Statikbe\\GoogleAuthenticate\\GoogleAuthenticateServiceProvider" --tag="lang"
```

## Security

If you discover any security related issues, please email [info@statik.be](mailto:info@statik.be) instead of using the issue tracker.

## License

The MIT License (MIT). Please see [License file](license.md) for more information.

