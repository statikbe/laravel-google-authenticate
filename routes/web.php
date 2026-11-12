<?php

use Statikbe\GoogleAuthenticate\Http\GoogleAuthController;

$throttle = config('google-authenticate.throttle');

Route::middleware([...config('google-authenticate.middleware'), 'guest', ...($throttle ? ["throttle:{$throttle}"] : [])])->group(function () {
    Route::get('login/google', [GoogleAuthController::class, 'redirectToProvider'])->name('google.auth.login');
    Route::get('login/google/callback', [GoogleAuthController::class, 'handleProviderCallback'])->name('google.auth.callback');
});
