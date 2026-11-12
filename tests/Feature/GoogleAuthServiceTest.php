<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Laravel\Socialite\Two\User as SocialiteUser;
use Statikbe\GoogleAuthenticate\Events\GoogleAccountLinked;
use Statikbe\GoogleAuthenticate\Events\GoogleUserRegistered;
use Statikbe\GoogleAuthenticate\Exceptions\GoogleAuthenticationException;
use Statikbe\GoogleAuthenticate\GoogleAuthService;
use Statikbe\GoogleAuthenticate\Tests\Support\MappedUser;
use Statikbe\GoogleAuthenticate\Tests\Support\Member;
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

it('works with a configured user model that is not App\Models\User', function () {
    Schema::create('members', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->timestamp('email_verified_at')->nullable();
        $table->string('provider')->nullable();
        $table->string('provider_id')->nullable();
        $table->rememberToken();
        $table->timestamps();
    });
    config(['auth.providers.users.model' => Member::class]);

    $member = app(GoogleAuthService::class)->authenticate(socialiteUser());

    expect($member)->toBeInstanceOf(Member::class)->provider_id->toBe('google-123');
    expect(Member::count())->toBe(1);
});

it('glues non-claim words in user_columns literally', function () {
    config(['google-authenticate.user_columns' => [
        'name' => ['name', ' ', 'admin'],
        'email' => ['email'],
        'email_verified_at' => ['email_verified'],
    ]]);

    $user = app(GoogleAuthService::class)->authenticate(socialiteUser());

    expect($user->name)->toBe('Jan admin');
});

it('refuses an email linked to another google id when registration is disabled', function () {
    config(['google-authenticate.register_enabled' => false]);
    User::forceCreate(['name' => 'Jan', 'email' => 'jan@statik.be', 'provider' => 'google', 'provider_id' => 'google-original']);

    app(GoogleAuthService::class)->authenticate(socialiteUser());
})->throws(GoogleAuthenticationException::class, 'E-mail linked to another Google account');

it('dispatches the registered event only when a row is created', function () {
    Event::fake([GoogleUserRegistered::class]);
    $service = app(GoogleAuthService::class);

    $service->authenticate(socialiteUser());
    Event::assertDispatchedTimes(GoogleUserRegistered::class, 1);

    // provider_id sync of the existing row
    $service->authenticate(socialiteUser());
    Event::assertDispatchedTimes(GoogleUserRegistered::class, 1);

    // linking a row that matched by email only
    User::query()->update(['provider_id' => null, 'provider' => null]);
    $service->authenticate(socialiteUser());

    expect(User::count())->toBe(1);
    Event::assertDispatchedTimes(GoogleUserRegistered::class, 1);
});

it('refuses to create a user when allowed is empty and allow_any is off, and reports it', function () {
    Exceptions::fake();
    config(['google-authenticate.domains' => ['allowed' => [], 'disabled' => [], 'allow_any' => false]]);

    try {
        app(GoogleAuthService::class)->authenticate(socialiteUser());
        $this->fail('expected a refusal');
    } catch (GoogleAuthenticationException $e) {
        expect($e->getCode())->toBe(403);
    }

    expect(User::count())->toBe(0);
    Exceptions::assertReported(fn (LogicException $e) => str_contains($e->getMessage(), 'ALLOW_ANY'));
});

it('still logs in existing users when allowed is empty and allow_any is off', function () {
    config(['google-authenticate.domains' => ['allowed' => [], 'disabled' => [], 'allow_any' => false]]);
    User::forceCreate(['name' => 'Jan', 'email' => 'jan@statik.be', 'provider' => 'google', 'provider_id' => 'google-123']);
    User::forceCreate(['name' => 'Piet', 'email' => 'piet@gmail.com']);
    $piet = (new SocialiteUser)
        ->setRaw(['name' => 'Piet', 'email' => 'piet@gmail.com', 'email_verified' => true])
        ->map(['id' => 'google-456', 'name' => 'Piet', 'email' => 'piet@gmail.com']);

    $service = app(GoogleAuthService::class);

    expect($service->authenticate(socialiteUser())->email)->toBe('jan@statik.be');
    expect($service->authenticate($piet)->provider_id)->toBe('google-456');
    expect(User::count())->toBe(2);
});

it('creates users with an empty allowed list when allow_any is true', function () {
    config(['google-authenticate.domains' => ['allowed' => [], 'disabled' => [], 'allow_any' => true]]);

    expect(app(GoogleAuthService::class)->authenticate(socialiteUser())->provider_id)->toBe('google-123');
});

it('creates users when allowed is set, regardless of allow_any', function () {
    config(['google-authenticate.domains' => ['allowed' => ['statik.be'], 'disabled' => [], 'allow_any' => false]]);

    expect(app(GoogleAuthService::class)->authenticate(socialiteUser())->provider_id)->toBe('google-123');
});

it('refuses to move a linked user onto the email of another row and saves nothing', function () {
    config(['google-authenticate.domains' => ['allowed' => ['statik.be'], 'disabled' => [], 'allow_any' => false]]);
    $linked = User::forceCreate(['name' => 'Jan', 'email' => 'old@statik.be', 'provider' => 'google', 'provider_id' => 'google-123']);
    $other = User::forceCreate(['name' => 'Boss', 'email' => 'jan@statik.be', 'provider' => 'google', 'provider_id' => 'google-999']);

    try {
        app(GoogleAuthService::class)->authenticate(socialiteUser());
        $this->fail('expected a refusal');
    } catch (GoogleAuthenticationException $e) {
        expect($e->getCode())->toBe(403)->and($e->getMessage())->toBe('E-mail linked to another account');
    }

    expect($linked->fresh()->email)->toBe('old@statik.be');
    expect($other->fresh())->email->toBe('jan@statik.be')->name->toBe('Boss');
});

it('still syncs a changed email onto a linked user when no other row has it', function () {
    config(['google-authenticate.domains' => ['allowed' => ['statik.be'], 'disabled' => [], 'allow_any' => false]]);
    User::forceCreate(['name' => 'Jan', 'email' => 'old@statik.be', 'provider' => 'google', 'provider_id' => 'google-123']);

    expect(app(GoogleAuthService::class)->authenticate(socialiteUser())->email)->toBe('jan@statik.be');
});

it('only matches provider_id lookups of the google provider', function () {
    User::forceCreate(['name' => 'Other', 'email' => 'other@statik.be', 'provider' => 'github', 'provider_id' => 'google-123']);

    $user = app(GoogleAuthService::class)->authenticate(socialiteUser());

    expect($user->email)->toBe('jan@statik.be')->and(User::count())->toBe(2);
});

it('still finds a row with a NULL provider by its google id', function () {
    $existing = User::forceCreate(['name' => 'Jan', 'email' => 'jan@statik.be', 'provider' => null, 'provider_id' => 'google-123']);

    expect(app(GoogleAuthService::class)->authenticate(socialiteUser())->is($existing))->toBeTrue()
        ->and(User::count())->toBe(1);
});

it('refuses an email owned by a row of another provider', function () {
    User::forceCreate(['name' => 'Jan', 'email' => 'jan@statik.be', 'provider' => 'github', 'provider_id' => 'gh-1']);
    Log::spy();

    try {
        app(GoogleAuthService::class)->authenticate(socialiteUser());
        $this->fail('expected a refusal');
    } catch (GoogleAuthenticationException $e) {
        expect($e->getMessage())->toBe('E-mail linked to another account');
    }

    expect(User::count())->toBe(1);
    Log::shouldHaveReceived('warning')->once();
});

it('matches an existing row by email case-insensitively', function () {
    $existing = User::forceCreate(['name' => 'Jan', 'email' => 'Jan@Statik.be']);

    $user = app(GoogleAuthService::class)->authenticate(socialiteUser());

    expect($user->is($existing))->toBeTrue()->and(User::count())->toBe(1);
});

it('refuses a different google id on a differently-cased email', function () {
    User::forceCreate(['name' => 'Jan', 'email' => 'JAN@statik.be', 'provider' => 'google', 'provider_id' => 'google-999']);

    app(GoogleAuthService::class)->authenticate(socialiteUser());
})->throws(GoogleAuthenticationException::class, 'E-mail linked to another Google account');

it('uses the mapped email column for lookups', function () {
    Schema::create('mapped_users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('mail')->unique();
        $table->timestamp('email_verified_at')->nullable();
        $table->string('provider')->nullable();
        $table->string('provider_id')->nullable();
        $table->rememberToken();
        $table->timestamps();
    });
    config([
        'auth.providers.users.model' => MappedUser::class,
        'google-authenticate.user_columns' => ['name' => ['name'], 'mail' => ['email'], 'email_verified_at' => ['email_verified']],
    ]);
    MappedUser::forceCreate(['name' => 'Jan', 'mail' => 'JAN@statik.be']);

    $user = app(GoogleAuthService::class)->authenticate(socialiteUser());

    expect(MappedUser::count())->toBe(1)->and($user->provider_id)->toBe('google-123');
});

it('reads register_enabled at call time', function () {
    $service = app(GoogleAuthService::class);

    config(['google-authenticate.register_enabled' => false]);

    expect(fn () => $service->authenticate(socialiteUser()))->toThrow(GoogleAuthenticationException::class, 'No user found.');
});

it('dispatches GoogleAccountLinked on a link only', function () {
    Event::fake([GoogleAccountLinked::class]);
    $service = app(GoogleAuthService::class);

    $service->authenticate(socialiteUser());
    $service->authenticate(socialiteUser());
    Event::assertNotDispatched(GoogleAccountLinked::class);

    User::query()->update(['provider_id' => null, 'provider' => null]);
    $service->authenticate(socialiteUser());

    Event::assertDispatchedTimes(GoogleAccountLinked::class, 1);
    Event::assertDispatched(GoogleAccountLinked::class, fn (GoogleAccountLinked $e) => $e->user->email === 'jan@statik.be' && $e->googleUser->id === 'google-123');
});

it('rolls the link back when a GoogleAccountLinked listener throws', function () {
    User::forceCreate(['name' => 'Jan', 'email' => 'jan@statik.be']);
    Event::listen(GoogleAccountLinked::class, fn () => throw new RuntimeException('boom'));

    expect(fn () => app(GoogleAuthService::class)->authenticate(socialiteUser()))->toThrow(RuntimeException::class);
    expect(User::first()->provider_id)->toBeNull();
});

it('logs a refused login with the google sub and the reason, never the email', function () {
    Log::spy();

    try {
        app(GoogleAuthService::class)->authenticate(socialiteUser(verified: false));
    } catch (GoogleAuthenticationException) {
    }

    Log::shouldHaveReceived('warning')->once()->withArgs(function (string $message, array $context): bool {
        return $context === ['sub' => 'google-123', 'reason' => 'E-mail not verified']
            && ! str_contains($message.json_encode($context), 'jan@statik.be');
    });
});

it('reports the open registration misconfiguration at most once per hour', function () {
    Exceptions::fake();
    config(['google-authenticate.domains' => ['allowed' => [], 'disabled' => [], 'allow_any' => false]]);
    $service = app(GoogleAuthService::class);

    foreach ([socialiteUser(), socialiteUser()] as $googleUser) {
        try {
            $service->authenticate($googleUser);
        } catch (GoogleAuthenticationException) {
        }
    }

    Exceptions::assertReportedCount(1);
});

it('reuses the row of a parallel callback after a unique violation on provider_id', function () {
    Schema::table('users', fn (Blueprint $table) => $table->unique('provider_id'));
    // Simulates the other request committing right after our provider_id lookup came back empty.
    $raced = false;
    DB::listen(function ($query) use (&$raced): void {
        if (! $raced && str_contains($query->sql, '"provider_id" = ?')) {
            $raced = true;
            DB::table('users')->insert(['name' => 'Jan', 'email' => 'racer@statik.be', 'provider' => 'google', 'provider_id' => 'google-123']);
        }
    });
    Event::fake([GoogleUserRegistered::class]);

    $user = app(GoogleAuthService::class)->authenticate(socialiteUser());

    expect($user->email)->toBe('racer@statik.be')->and(User::count())->toBe(1);
    Event::assertNotDispatched(GoogleUserRegistered::class);
});
