<?php

use Statikbe\GoogleAuthenticate\GoogleAuthenticateController;

Route::middleware(config('google-authenticate.middleware'))->group(function() {
    Route::get('login/google', [GoogleAuthenticateController::class, 'redirectToProvider'])->name('google.auth.login');
    Route::get('login/google/callback', [GoogleAuthenticateController::class, 'handleProviderCallback']);
});