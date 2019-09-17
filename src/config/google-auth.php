<?php

return  [
    //Sets the Role you want to give to your authenticated user
    'auth_role_admin' => env('AUTH_ROLE_ADMIN'),
    //Sets the domain to check when you login your user
    'auth_role_domain' => env('AUTH_ROLE_DOMAIN'),
    //Sets the redirect url afterwards
    'auth_redirect_url' => env('AUTH_REDIRECT_URL'),
];
