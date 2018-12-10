# GoogleAuthenticator


This module gives you the option to log in with Google that is coupled to a specific domain.
It will give that user the Admin Role in return.

## Installation

Via Composer

``` bash
$ composer require statikbe/laravel-google-authenticate
```

## Usage

The package will automatically register itself.

You can publish the migration with:
```shell
php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="migrations"

```

To add the needed columns in your database run:
 ``` shell
php artisan migrate
``` 

Add the ``` use HasGoogleAuth``` trait in your ```User.php``` class.
This will provide the necessary fillable options to your User.

in your .env file you should include the following keys:
``` php
AUTH_ROLE_ADMIN="Admin" //This can be any role you like
AUTH_ROLE_DOMAIN="domain.com"
GOOGLE_CLIENT_ID="YOUR_GOOGLE_CLIENT_ID"
GOOGLE_CLIENT_SECRET="YOUR_GOOGLE_CLIENT_SECRET"
CALLBACK_URL_GOOGLE="YOUR_GOOGLE_CALLBACK_URL"
```
Info on how to create a Google Auth Client id and secret can be
found [on their documentation page](https://developers.google.com/identity/protocols/OAuth2).

## Security

If you discover any security related issues, please email [info@statik.be](mailto:info@statik.be) instead of using the issue tracker.

## License

The MIT License (MIT). Please see [License file](license.md) for more information.

