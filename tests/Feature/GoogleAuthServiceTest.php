<?php

use Illuminate\Support\Facades\Event;
use Laravel\Socialite\Two\User as SocialiteUser;
use Statikbe\GoogleAuthenticate\Events\GoogleUserRegistered;
use Statikbe\GoogleAuthenticate\Exceptions\GoogleAuthenticationException;
use Statikbe\GoogleAuthenticate\Services\GoogleAuthService;
use Statikbe\GoogleAuthenticate\Tests\Support\User;

function socialiteUser(bool $verified = true): SocialiteUser
{
    return (new SocialiteUser)
        ->setRaw(['name' => 'Jan', 'email' => 'jan@statik.be', 'email_verified' => $verified, 'hd' => 'statik.be'])
        ->map(['id' => 'google-123', 'name' => 'Jan', 'email' => 'jan@statik.be']);
}

it('creates a user for a valid payload and dispatches the registered event once', function () {
    Event::fake([GoogleUserRegistered::class]);

    $user = app(GoogleAuthService::class)->authenticate(socialiteUser());

    expect($user)->email->toBe('jan@statik.be')->provider_id->toBe('google-123');
    expect(User::count())->toBe(1);
    Event::assertDispatchedTimes(GoogleUserRegistered::class, 1);
});

it('throws for an unverified email and creates nothing', function () {
    app(GoogleAuthService::class)->authenticate(socialiteUser(verified: false));
})->throws(GoogleAuthenticationException::class);

it('does not persist a user for an unverified email', function () {
    try {
        app(GoogleAuthService::class)->authenticate(socialiteUser(verified: false));
    } catch (GoogleAuthenticationException) {
    }

    expect(User::count())->toBe(0);
});
