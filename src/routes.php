<?php

Route::group(['middleware' => ['web']], function () {
    Route::get('login/google', 'Statikbe\GoogleAuthenticate\GoogleAuthenticateController@redirectToProvider')->name('login.auth.google');
    Route::get('login/google/callback', 'Statikbe\GoogleAuthenticate\GoogleAuthenticateController@handleProviderCallback');
    Route::get('logout', 'Statikbe\GoogleAuthenticate\GoogleAuthenticateController@logout');
});