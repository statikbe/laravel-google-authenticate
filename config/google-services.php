<?php

// Defaults for config('services.google'); merged by the service provider so the app's own values win.
return [
    'client_id' => env('GOOGLE_CLIENT_ID'),
    'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    'redirect' => '/login/google/callback',
];
