<?php

use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Statikbe\GoogleAuthenticate\Tests\Support\User;

function googleReturns(string $email, bool $verified = true, string $id = 'google-123'): void
{
    $googleUser = (new SocialiteUser)
        ->setRaw(['name' => 'Jan', 'email' => $email, 'email_verified' => $verified])
        ->map(['id' => $id, 'name' => 'Jan', 'email' => $email]);

    Socialite::shouldReceive('driver->user')->andReturn($googleUser);
}

function existingUser(string $email = 'jan@statik.be', ?string $providerId = null): User
{
    return User::forceCreate(['name' => 'Jan', 'email' => $email, 'provider_id' => $providerId]);
}

function callback(): Illuminate\Testing\TestResponse
{
    return test()->get(route('google.auth.callback'));
}

it('registers and logs in a new user on an allowed domain', function () {
    config(['google-authenticate.domains' => ['allowed' => ['statik.be']]]);
    googleReturns('jan@statik.be');

    callback();

    $this->assertAuthenticated();
    expect(User::where('email', 'jan@statik.be')->value('provider_id'))->toBe('google-123');
});

it('logs in an existing verified user when registration is disabled and links the provider id', function () {
    config(['google-authenticate.register_enabled' => false]);
    $user = existingUser();
    googleReturns('jan@statik.be');

    callback();

    $this->assertAuthenticatedAs($user);
    expect($user->fresh())->provider_id->toBe('google-123')->provider->toBe('google');
});

it('rejects a different google id for an email already linked to another provider id', function () {
    config(['google-authenticate.register_enabled' => false]);
    existingUser(providerId: 'google-original');
    googleReturns('jan@statik.be', id: 'google-attacker');

    callback();

    $this->assertGuest();
});

it('rejects an unverified google email when registration is disabled', function () {
    config(['google-authenticate.register_enabled' => false]);
    existingUser();
    googleReturns('jan@statik.be', verified: false);

    callback();

    $this->assertGuest();
});

it('rejects an unknown email when registration is disabled', function () {
    config(['google-authenticate.register_enabled' => false]);
    googleReturns('nobody@statik.be');

    callback();

    $this->assertGuest();
    expect(User::count())->toBe(0);
});

it('rejects an existing user outside the allowed domains', function () {
    config([
        'google-authenticate.register_enabled' => false,
        'google-authenticate.domains' => ['allowed' => ['statik.be']],
    ]);
    existingUser('jan@gmail.com');
    googleReturns('jan@gmail.com');

    callback();

    $this->assertGuest();
});

it('matches allowed domains case-insensitively', function () {
    config(['google-authenticate.domains' => ['allowed' => ['statik.be']]]);
    googleReturns('JAN@STATIK.BE');

    callback();

    $this->assertAuthenticated();
});

it('rejects a disabled domain', function () {
    config(['google-authenticate.domains' => ['disabled' => ['gmail.com']]]);
    googleReturns('jan@gmail.com');

    callback();

    $this->assertGuest();
    expect(User::count())->toBe(0);
});

it('logs in an existing verified user when registration is disabled and no domains are configured', function () {
    config([
        'google-authenticate.register_enabled' => false,
        'google-authenticate.domains' => [],
    ]);
    $user = existingUser();
    googleReturns('jan@statik.be');

    callback();

    $this->assertAuthenticatedAs($user);
});

it('logs in an already linked user again when registration is disabled', function () {
    config(['google-authenticate.register_enabled' => false]);
    $user = existingUser(providerId: 'google-123');
    googleReturns('jan@statik.be');

    callback();

    $this->assertAuthenticatedAs($user);
    expect(User::count())->toBe(1);
});

it('rejects an unverified google email for an existing unlinked user when registration is enabled', function () {
    $user = existingUser();
    googleReturns('jan@statik.be', verified: false);

    callback();

    $this->assertGuest();
    expect($user->fresh()->provider_id)->toBeNull();
});

it('rejects a different google id for a linked email when registration is enabled', function () {
    existingUser(providerId: 'google-original');
    googleReturns('jan@statik.be', id: 'google-attacker');

    callback()->assertRedirect();

    $this->assertGuest();
    expect(User::count())->toBe(1);
});
