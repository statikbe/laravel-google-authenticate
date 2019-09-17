<?php

Route::group(['middleware' => ['web']], function () {
    Route::get('login/google', 'Statikbe\GoogleAuthenticate\GoogleAuthenticateController@redirectToProvider')->name('google.auth.login');
    Route::get('login/google/callback', 'Statikbe\GoogleAuthenticate\GoogleAuthenticateController@handleProviderCallback');
    Route::get('logout', 'Statikbe\GoogleAuthenticate\GoogleAuthenticateController@logout')->name('google.auth.logout');
});