<?php

use Statikbe\GoogleAuthenticate\Listeners\GrantSuperAdminRole;

return [

    /*
    |--------------------------------------------------------------------------
    | Redirect url
    |--------------------------------------------------------------------------
    |
    | Sets the redirect url after login
    |
    */

    'redirect_url' => env('GOOGLE_AUTH_REDIRECT_URL', '/'),

    /*
    |--------------------------------------------------------------------------
    | register enabled
    |--------------------------------------------------------------------------
    |
    | Set's a flag to only let manually create users login with their Google Account. Non existing users are sent back with the generic login error
    |
    */
    'register_enabled' => env('GOOGLE_AUTH_REGISTER', true),

    /*
    |--------------------------------------------------------------------------
    | Remember me
    |--------------------------------------------------------------------------
    |
    | Set to true to issue a long-lived remember-me cookie after a Google login
    |
    */
    'remember' => false,

    /*
    |--------------------------------------------------------------------------
    | Column Names
    |--------------------------------------------------------------------------
    |
    | This array will be used to fill a user with the data returned by Google.
    | In case any of your column names differ from the regular Laravel User
    | model you can edit them here.
    |
    | 'user_columns' => [
    |    'column_name' => ['google_key' , 'google_key'],
    | ]
    |
    | The values provided in comment are also returned by google
    | but are not used in a regular Laravel User model.
    | You can also provide multiple values for one column. These will be
    | glued together, you can add your own values as value inside the array. ['name',' (','locale', ')']
    |
    */

    'user_columns' => [
        'name' => ['name'],
        'email_verified_at' => ['email_verified'], // will be changed from boolean to current date
        'email' => ['email'],
        // 'first_name' => ['given_name'],
        // 'last_name' => ['family_name'],
        // 'picture' => ['picture'],    // picture url
        // 'locale' => ['locale'],   // 'en' for example
        // 'link' => ['link'],

    ],

    /*
   |--------------------------------------------------------------------------
   | Configure what domains can log in using google
   |--------------------------------------------------------------------------
   |
   | 'allowed' is a comma separated list in GOOGLE_AUTH_ALLOWED_DOMAINS.
   | When 'allowed' is set, the Google account must also belong to one of those
   |   Workspace domains (the 'hd' claim), so personal accounts are refused.
   | If some domains are not allowed to log in using google
   |   you can add them to the 'disabled' array.
   | When 'allowed' is empty, new users are refused unless 'allow_any' is true
   |   (public apps that accept every Google account).
   |
   */

    'domains' => [
        'allowed' => array_filter(array_map('trim', explode(',', (string) env('GOOGLE_AUTH_ALLOWED_DOMAINS', '')))),
        'disabled' => [],
        'allow_any' => env('GOOGLE_AUTH_ALLOW_ANY_DOMAIN', false),
    ],

    /*
   |--------------------------------------------------------------------------
   | Super admin
   |--------------------------------------------------------------------------
   |
   | 'super_admin_role' is the spatie role used by the default listener and the gate.
   | 'super_admin_listener' must be GrantSuperAdminRole or a subclass of it (for example
   |   one that overrides assignRole()); boot throws otherwise. Set null or '' for no
   |   package listener, the gate stays on.
   | 'super_admin_gate' lets Gate::before give that role every ability.
   |
   */

    'super_admin_role' => env('GOOGLE_AUTH_SUPER_ADMIN_ROLE', 'super_admin'),
    'super_admin_listener' => GrantSuperAdminRole::class,
    'super_admin_gate' => env('GOOGLE_AUTH_SUPER_ADMIN_GATE', true),

    /*
    |--------------------------------------------------------------------------
    | Throttle
    |--------------------------------------------------------------------------
    |
    | Laravel throttle "maxAttempts,decayMinutes" per IP for both Google routes. Null or empty disables it.
    |
    */
    'throttle' => env('GOOGLE_AUTH_THROTTLE', '10,1'),

    /*
   |--------------------------------------------------------------------------
   | Configure what middleware you want on your route
   |--------------------------------------------------------------------------
   |
   | If you need to add custom middleware on the login/google route
   | you can simply do this by adding them in the array below.
   | Default the web middleware will be loaded, but this can be altered for
   | something like multi tenancy
   | ['web', TennantMiddleware::class]
   */
    'middleware' => ['web'],
];
