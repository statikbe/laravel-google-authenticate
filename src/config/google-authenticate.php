<?php

return  [

    /*
    |--------------------------------------------------------------------------
    | Redirect url
    |--------------------------------------------------------------------------
    |
    | Sets the redirect url after login
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
        'email_verified_at' => ['email_verified'], //will be changed from boolean to current date
        'email' => ['email'],
        //'first_name' => ['given_name'],
        //'last_name' => ['family_name'],
        //'picture' => ['picture'],    // picture url
        //'locale' => ['locale'],   // 'en' for example
        //'link' => ['link'],

    ],

   /*
   |--------------------------------------------------------------------------
   | Configure what domains can log in using google
   |--------------------------------------------------------------------------
   |
   | If any domain can log in using google you can leave this array empty.
   | If only certain domains can log in using google
   |   you can add them to the 'allowed' array.
   | If some domains are not allowed to log in using google
   |   you can add them to the 'disabled' array.
   |
   */

    'domains' => [
        //'allowed' => ['statik.be'],
        //'disabled' => ['google.com'],
    ],
];
