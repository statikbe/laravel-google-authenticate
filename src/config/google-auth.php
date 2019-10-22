<?php

return  [

   /*
   |--------------------------------------------------------------------------
   | Login domains and their Roles
   |--------------------------------------------------------------------------
   |
   | Here you can provide the domains your users email should contain to
   | receive certain roles.
   | If there is no need to check the domain you can leave the array empty.
   |
   */

    'roles' => [
        'no_role' => [
            //'domain.be',
        ],
        'admin' => [
            'statik.be',
        ],
        //'RoleName' => [
        //    'domain.com',
        //    'other.org'
        //],
    ],

    /*
    |--------------------------------------------------------------------------
    | Redirect url
    |--------------------------------------------------------------------------
    |
    | Sets the redirect url after login
    |
    |
    */

    'redirect_url' => '/',

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
    |    'google_key' => 'column_name',
    | ]
    |
    | The values provided in comment are also returned by google
    | but are not used in a regular Laravel User model.
    |
    */

    'user_columns' => [
        'name' => 'name',
        'email_verified' => 'email_verified_at', //will be changed from boolean to current date
        'email' => 'email',
        //'given_name' => 'first_name',
        //'family_name' => 'last_name'
        //'picture' => 'picture',    // picture url
        //'nickname' => 'nickname',
        //'locale' => 'nickname',   // 'en' for example
    ]

];
