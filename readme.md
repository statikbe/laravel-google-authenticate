# GoogleAuthenticator


This module gives you the option to let you (and your users) log in with Google on your Laravel application. 
You can specify Roles that your user will receive upon login. These can be protected using the domain of the users email. 


## Installation

Via Composer

``` bash
$ composer require statikbe/laravel-google-authenticate
```

## Usage

The package will automatically register itself.

You can publish the migration and config with following command:
``` shell
php artisan vendor:publish --provider="Statikbe\GoogleAuthenticate\GoogleAuthenticateServiceProvider"
```

To add the needed columns in your database run:
 ``` shell
php artisan migrate
``` 

Add the ``` use HasGoogleAuth``` trait in your ```User.php``` class.
This will provide the necessary fillable options to your User.

In your .env file you should include the following keys:
``` php
GOOGLE_CLIENT_ID="YOUR_GOOGLE_CLIENT_ID"
GOOGLE_CLIENT_SECRET="YOUR_GOOGLE_CLIENT_SECRET"
CALLBACK_URL_GOOGLE="https://www.domain.com/login/google/callback"
```

The next step is to add the following lines in your ```services.php```
``` php
'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('CALLBACK_URL_GOOGLE'),
    ],
```

Info on how to create a Google Auth Client id and secret can be
found [on their documentation page](https://developers.google.com/identity/protocols/OAuth2).

Finally you can add google login route to your views: `{{ route('google.auth.login') }}`.


### Config
You can config the package in  ```config/google-auth.php```.

#### Roles
You can customize who gets what role. Or even what email domains are allowed to login. For example: 
The following config would result in this situation: 
You can only receive the `admin` role if your email is: `*@statik.be`. 
(If your email is something else you will still be logged-in without a role).
you can also exclude domains from receiving a role. To do this use `!` at the start of your domain.

``` php 
'roles' => [
        'no_role' => [
            //              //everyone can login
        ],
        'admin' => [
            'statik.be',    //only statik.be would receive the admin role
        ],
        'regular_user' => [
            '!statik.be',   //everyone except statik.be would receive this role
        ],
]
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
php artisan vendor:publish --tag=laravel-google-authenticate.views
```
and 
``` shell
php artisan vendor:publish --tag=laravel-google-authenticate.translations
```

## Security

If you discover any security related issues, please email [info@statik.be](mailto:info@statik.be) instead of using the issue tracker.

## License

The MIT License (MIT). Please see [License file](license.md) for more information.

