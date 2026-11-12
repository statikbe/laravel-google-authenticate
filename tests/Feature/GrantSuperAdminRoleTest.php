<?php

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Statikbe\GoogleAuthenticate\Events\GoogleUserRegistered;
use Statikbe\GoogleAuthenticate\GoogleAuthenticateServiceProvider;
use Statikbe\GoogleAuthenticate\Listeners\GrantSuperAdminRole;
use Statikbe\GoogleAuthenticate\Tests\Support\CustomRole;
use Statikbe\GoogleAuthenticate\Tests\Support\ListenerWithoutSpatie;
use Statikbe\GoogleAuthenticate\Tests\Support\RenamedListener;
use Statikbe\GoogleAuthenticate\Tests\Support\RoleUser;
use Statikbe\GoogleAuthenticate\Tests\Support\User;

function listenerAllowed(array $allowed = ['statik.be']): void
{
    config(['google-authenticate.domains' => ['allowed' => $allowed, 'disabled' => [], 'allow_any' => false]]);
}

// Registers the listener again after a test changed the config.
function registerListener(): void
{
    Event::forget(GoogleUserRegistered::class);

    (new class(app()) extends GoogleAuthenticateServiceProvider
    {
        public function listen(): void
        {
            $this->registerSuperAdminListener();
        }
    })->listen();
}

function registerNewUser(string $email = 'jan@statik.be', string $hostedDomain = 'statik.be', string $model = RoleUser::class): Model
{
    $user = $model::forceCreate(['name' => 'Jan', 'email' => $email]);
    $google = (new SocialiteUser)->setRaw([])->map(['id' => '1', 'email' => $email]);

    event(new GoogleUserRegistered($user, $google, $hostedDomain));

    return $user;
}

function googleLogin(string $email, ?string $hd = 'statik.be'): void
{
    $googleUser = (new SocialiteUser)
        ->setRaw(array_filter(['name' => 'Jan', 'email' => $email, 'email_verified' => true, 'hd' => $hd]))
        ->map(['id' => 'g-1', 'name' => 'Jan', 'email' => $email]);

    Socialite::shouldReceive('driver->user')->andReturn($googleUser);
}

beforeEach(function () {
    config([
        'auth.providers.users.model' => RoleUser::class,
        'google-authenticate.super_admin_listener' => GrantSuperAdminRole::class,
    ]);
    registerListener();
    RenamedListener::$assigned = [];
});

it('grants the default role to a new user from an allowed domain', function () {
    listenerAllowed();

    expect(registerNewUser()->hasRole('super_admin'))->toBeTrue();
});

it('grants a custom role name from config', function () {
    listenerAllowed();
    config(['google-authenticate.super_admin_role' => 'owner']);

    $user = registerNewUser();

    expect($user->hasRole('owner'))->toBeTrue()->and($user->hasRole('super_admin'))->toBeFalse();
});

it('creates the role when missing and reuses an existing one', function () {
    listenerAllowed();

    registerNewUser('a@statik.be');
    registerNewUser('b@statik.be');

    expect(Role::where('name', 'super_admin')->count())->toBe(1);
});

it('respects a custom Role model', function () {
    listenerAllowed();
    app(PermissionRegistrar::class)->setRoleClass(CustomRole::class);

    try {
        $user = registerNewUser();

        expect($user->roles->first())->toBeInstanceOf(CustomRole::class);
    } finally {
        app(PermissionRegistrar::class)->setRoleClass(Role::class);
    }
});

it('grants nothing when domains.allowed is empty', function () {
    listenerAllowed([]);

    expect(registerNewUser()->roles)->toBeEmpty();
});

it('grants nothing when the hosted domain is not allowed', function () {
    listenerAllowed();

    expect(registerNewUser(hostedDomain: 'other.be')->roles)->toBeEmpty();
    expect(registerNewUser('c@statik.be', hostedDomain: '')->roles)->toBeEmpty();
});

it('grants nothing when the email domain is not allowed', function () {
    listenerAllowed();

    expect(registerNewUser('jan@other.be')->roles)->toBeEmpty();
});

it('grants nothing when super_admin_role is empty', function () {
    listenerAllowed();
    config(['google-authenticate.super_admin_role' => '']);

    expect(registerNewUser()->roles)->toBeEmpty();
});

it('grants nothing to a row that was only linked to Google, not created', function () {
    listenerAllowed();
    $user = RoleUser::forceCreate(['name' => 'Jan', 'email' => 'jan@statik.be']);
    googleLogin('jan@statik.be');

    $this->get(route('google.auth.callback'));

    $this->assertAuthenticated();
    expect($user->fresh()->provider_id)->toBe('g-1')->and($user->fresh()->roles)->toBeEmpty();
});

it('grants through the whole login for a new user', function () {
    listenerAllowed();
    googleLogin('jan@statik.be');

    $this->get(route('google.auth.callback'));

    $this->assertAuthenticated();
    expect(RoleUser::firstWhere('email', 'jan@statik.be')->hasRole('super_admin'))->toBeTrue();
});

it('registers no listener when super_admin_listener is null', function () {
    listenerAllowed();
    config(['google-authenticate.super_admin_listener' => null]);
    registerListener();

    expect(registerNewUser()->roles)->toBeEmpty();
});

it('registers no listener when super_admin_role is empty', function () {
    config(['google-authenticate.super_admin_role' => '']);
    registerListener();

    expect(Event::hasListeners(GoogleUserRegistered::class))->toBeFalse();
});

it('fails loudly at boot when the configured listener class does not exist', function () {
    config(['google-authenticate.super_admin_listener' => 'App\Listeners\Typo']);

    expect(fn () => registerListener())->toThrow(InvalidArgumentException::class, 'App\Listeners\Typo');
});

it('runs a subclass that overrides only assignRole, and still skips users outside allowed', function () {
    listenerAllowed();
    config(['google-authenticate.super_admin_listener' => RenamedListener::class]);
    registerListener();

    $user = registerNewUser();
    registerNewUser('x@other.be');

    expect(RenamedListener::$assigned)->toBe(['super_admin:jan@statik.be'])->and($user->roles)->toBeEmpty();
});

it('grants nothing and lets the login go on when spatie teams are on', function () {
    listenerAllowed();
    config(['permission.teams' => true]);
    googleLogin('jan@statik.be');

    $this->get(route('google.auth.callback'));

    $this->assertAuthenticated();
    expect(Role::count())->toBe(0);
});

it('logs a warning when the grant is skipped because spatie teams are on', function () {
    Log::spy();
    listenerAllowed();
    config(['permission.teams' => true]);
    googleLogin('jan@statik.be');

    $this->get(route('google.auth.callback'));

    Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message) => str_contains($message, 'teams'));
});

it('grants nothing when spatie is not installed', function () {
    listenerAllowed();
    config(['google-authenticate.super_admin_listener' => ListenerWithoutSpatie::class]);
    registerListener();

    expect(registerNewUser()->roles)->toBeEmpty()->and(Role::count())->toBe(0);
    // The spatie check comes first, so even a model without HasRoles does not throw.
    expect(fn () => registerNewUser('b@statik.be', model: User::class))->not->toThrow(LogicException::class);
});

it('rolls the new user back and fails the login when the user model lacks HasRoles', function () {
    listenerAllowed();
    config(['auth.providers.users.model' => User::class]);
    googleLogin('jan@statik.be');

    $this->get(route('google.auth.callback'));

    $this->assertGuest();
    expect(User::count())->toBe(0);
});

it('throws a LogicException for a model without assignRole', function () {
    listenerAllowed();

    expect(fn () => registerNewUser(model: User::class))->toThrow(LogicException::class, 'HasRoles');
});

it('is not queued', function () {
    expect(new GrantSuperAdminRole)->not->toBeInstanceOf(ShouldQueue::class);
});
