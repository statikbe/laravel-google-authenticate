<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Laravel\Socialite\AbstractUser;
use Statikbe\GoogleAuthenticate\Events\GoogleUserRegistered;
use Statikbe\GoogleAuthenticate\GoogleAuthenticateController;
use Statikbe\GoogleAuthenticate\Tests\Support\User;

function googleReturns(string $email, bool|string $verified = true, string $id = 'google-123', ?string $hd = 'statik.be'): void
{
    $googleUser = (new SocialiteUser)
        ->setRaw(array_filter(['name' => 'Jan', 'email' => $email, 'email_verified' => $verified, 'hd' => $hd], fn ($v) => $v !== null))
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

function rejected(Illuminate\Testing\TestResponse $response): void
{
    $response->assertRedirect('/')->assertSessionHas('danger', __('google-authenticate::messages.unauthenticated'));
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

    rejected(callback());

    $this->assertGuest();
});

it('rejects an unverified google email when registration is disabled', function () {
    config(['google-authenticate.register_enabled' => false]);
    existingUser();
    googleReturns('jan@statik.be', verified: false);

    rejected(callback());

    $this->assertGuest();
});

it('rejects an unknown email when registration is disabled', function () {
    config(['google-authenticate.register_enabled' => false]);
    googleReturns('nobody@statik.be');

    rejected(callback());

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

    rejected(callback());

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

    rejected(callback());

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

    rejected(callback());

    $this->assertGuest();
    expect($user->fresh()->provider_id)->toBeNull();
});

it('rejects a different google id for a linked email when registration is enabled', function () {
    existingUser(providerId: 'google-original');
    googleReturns('jan@statik.be', id: 'google-attacker');

    rejected(callback());

    $this->assertGuest();
    expect(User::count())->toBe(1);
});

it('handles a google payload without given_name and family_name', function () {
    config([
        'google-authenticate.user_columns' => [
            'name' => ['name'],
            'first_name' => ['given_name'],
            'email' => ['email'],
            'email_verified_at' => ['email_verified'],
        ],
    ]);
    googleReturns('jan@statik.be');

    callback();

    $this->assertAuthenticated();
    expect(User::where('email', 'jan@statik.be')->first())->first_name->toBe('');
});

it('refuses a payload without email_verified', function () {
    $googleUser = (new SocialiteUser)
        ->setRaw(['name' => 'Jan', 'email' => 'jan@statik.be'])
        ->map(['id' => 'google-123', 'name' => 'Jan', 'email' => 'jan@statik.be']);
    Socialite::shouldReceive('driver->user')->andReturn($googleUser);

    rejected(callback());

    $this->assertGuest();
    expect(User::count())->toBe(0);
});

it('refuses an unverified email without creating a user', function () {
    googleReturns('jan@statik.be', verified: false);

    rejected(callback());

    $this->assertGuest();
    expect(User::count())->toBe(0);
});

it('refuses a missing hd when allowed domains are set', function () {
    config(['google-authenticate.domains' => ['allowed' => ['statik.be']]]);
    googleReturns('jan@statik.be', hd: null);

    rejected(callback());

    $this->assertGuest();
    expect(User::count())->toBe(0);
});

it('refuses a non-matching hd when allowed domains are set', function () {
    config(['google-authenticate.domains' => ['allowed' => ['statik.be']]]);
    googleReturns('jan@statik.be', hd: 'evil.com');

    rejected(callback());

    $this->assertGuest();
});

it('accepts a matching hd case-insensitively when allowed domains are set', function () {
    config(['google-authenticate.domains' => ['allowed' => ['statik.be']]]);
    googleReturns('jan@statik.be', hd: 'STATIK.be');

    callback();

    $this->assertAuthenticated();
});

it('ignores hd when allowed domains are empty', function () {
    googleReturns('jan@gmail.com', hd: null);

    callback();

    $this->assertAuthenticated();
});

it('does not set a remember cookie by default', function () {
    googleReturns('jan@statik.be');

    $response = callback();

    $response->assertCookieMissing(Auth::guard()->getRecallerName());
});

it('sets a remember cookie when configured', function () {
    config(['google-authenticate.remember' => true]);
    googleReturns('jan@statik.be');

    $response = callback();

    $response->assertCookie(Auth::guard()->getRecallerName());
});

it('dispatches the registered event once, only for a newly created user', function () {
    Event::fake([GoogleUserRegistered::class]);
    googleReturns('jan@statik.be');

    callback();

    Event::assertDispatchedTimes(GoogleUserRegistered::class, 1);
    Event::assertDispatched(GoogleUserRegistered::class, fn (GoogleUserRegistered $e) => $e->user->is(User::first())
        && $e->googleUser->email === 'jan@statik.be'
        && $e->hostedDomain === 'statik.be');
});

it('does not dispatch the registered event on a later login', function () {
    Event::fake([GoogleUserRegistered::class]);
    existingUser(providerId: 'google-123');
    googleReturns('jan@statik.be');

    callback();

    $this->assertAuthenticated();
    Event::assertNotDispatched(GoogleUserRegistered::class);
});

it('does not dispatch the registered event when linking an existing user', function (bool $registerEnabled) {
    Event::fake([GoogleUserRegistered::class]);
    config(['google-authenticate.register_enabled' => $registerEnabled]);
    existingUser();
    googleReturns('jan@statik.be');

    callback();

    $this->assertAuthenticated();
    Event::assertNotDispatched(GoogleUserRegistered::class);
})->with([true, false]);

it('does not dispatch the registered event when a check fails', function (array $config, bool|string $verified, ?string $hd) {
    Event::fake([GoogleUserRegistered::class]);
    config($config);
    googleReturns('jan@statik.be', verified: $verified, hd: $hd);

    rejected(callback());

    $this->assertGuest();
    Event::assertNotDispatched(GoogleUserRegistered::class);
})->with([
    'unverified' => [[], false, 'statik.be'],
    'disabled domain' => [['google-authenticate.domains' => ['disabled' => ['statik.be']]], true, 'statik.be'],
    'domain not allowed' => [['google-authenticate.domains' => ['allowed' => ['other.be']]], true, 'other.be'],
    'hd missing' => [['google-authenticate.domains' => ['allowed' => ['statik.be']]], true, null],
    'hd mismatch' => [['google-authenticate.domains' => ['allowed' => ['statik.be']]], true, 'evil.com'],
]);

it('does not log in when a registered listener throws', function () {
    Event::listen(GoogleUserRegistered::class, fn () => throw new RuntimeException('boom'));
    googleReturns('jan@statik.be');

    $this->withoutExceptionHandling();
    expect(fn () => callback())->toThrow(RuntimeException::class, 'boom');

    $this->assertGuest();
    expect(User::count())->toBe(0);
});

it('registers again after a listener failed the first attempt', function () {
    $fail = true;
    $calls = 0;
    Event::listen(GoogleUserRegistered::class, function () use (&$fail, &$calls) {
        $calls++;
        if ($fail) {
            throw new RuntimeException('boom');
        }
    });
    googleReturns('jan@statik.be');

    $this->withoutExceptionHandling();
    expect(fn () => callback())->toThrow(RuntimeException::class, 'boom');
    $this->assertGuest();

    $fail = false;
    callback();

    $this->assertAuthenticated();
    expect($calls)->toBe(2)->and(User::count())->toBe(1);
});

it('refuses to link a consumer account on a non-gmail domain without hd', function (bool $registerEnabled) {
    config(['google-authenticate.register_enabled' => $registerEnabled]);
    $user = existingUser('jan@company.com');
    googleReturns('jan@company.com', hd: null);

    rejected(callback());

    $this->assertGuest();
    expect($user->fresh()->provider_id)->toBeNull();
})->with([true, false]);

it('refuses to link when hd differs from the email domain', function () {
    existingUser('jan@company.com');
    googleReturns('jan@company.com', hd: 'other.com');

    rejected(callback());

    $this->assertGuest();
});

it('links when hd matches the email domain case-insensitively', function (bool $registerEnabled) {
    config(['google-authenticate.register_enabled' => $registerEnabled]);
    $user = existingUser('jan@company.com');
    googleReturns('jan@company.com', hd: 'COMPANY.com');

    callback();

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->provider_id)->toBe('google-123');
})->with([true, false]);

it('links a gmail.com account without hd', function () {
    $user = existingUser('jan@gmail.com');
    googleReturns('jan@gmail.com', hd: null);

    callback();

    $this->assertAuthenticatedAs($user);
});

it('clears the password and rotates the remember token when linking', function (bool $registerEnabled) {
    config(['google-authenticate.register_enabled' => $registerEnabled]);
    $user = existingUser();
    $user->forceFill(['password' => 'pre-set-hash', 'remember_token' => 'old-token'])->save();
    googleReturns('jan@statik.be');

    callback();

    $fresh = $user->fresh();
    expect($fresh->password)->toBeNull()
        ->and($fresh->remember_token)->not->toBe('old-token')->toHaveLength(60);
})->with([true, false]);

it('does not touch the password of an already linked user', function () {
    $user = existingUser(providerId: 'google-123');
    $user->forceFill(['password' => 'kept', 'remember_token' => 'kept-token'])->save();
    googleReturns('jan@statik.be');

    callback();

    expect($user->fresh())->password->toBe('kept')->remember_token->toBe('kept-token');
});

it('never mass-assigns provider or provider_id', function () {
    expect((new User)->getFillable())->not->toContain('provider')->not->toContain('provider_id');
});

it('accepts the string forms of email_verified', function () {
    googleReturns('jan@statik.be', verified: 'true');
    callback();
    $this->assertAuthenticated();
});

it('refuses the string "false" for email_verified', function () {
    googleReturns('jan@statik.be', verified: 'false');

    rejected(callback());

    $this->assertGuest();
    expect(User::count())->toBe(0);
});

it('cannot bypass the checks by overriding findOrCreate', function () {
    $this->app->instance(GoogleAuthenticateController::class, new class extends GoogleAuthenticateController
    {
        protected function findOrCreate(AbstractUser $googleUser, string $provider): \App\Models\User
        {
            return \App\Models\User::forceCreate(['name' => 'x', 'email' => $googleUser->email]);
        }
    });
    googleReturns('jan@statik.be', verified: false);

    rejected(callback());

    $this->assertGuest();
    expect(User::count())->toBe(0);
});
