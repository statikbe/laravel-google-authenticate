<?php

use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use Statikbe\GoogleAuthenticate\Listeners\GrantSuperAdminRole;

it('no longer ships the HasGoogleAuth trait or the views', function () {
    expect(class_exists('Statikbe\GoogleAuthenticate\Traits\HasGoogleAuth'))->toBeFalse()
        ->and(is_dir(__DIR__.'/../../resources/views'))->toBeFalse();
});

it('keeps the route names and uris', function () {
    expect(Route::getRoutes()->getByName('google.auth.login')->uri())->toBe('login/google')
        ->and(Route::getRoutes()->getByName('google.auth.callback')->uri())->toBe('login/google/callback');
});

it('ships exactly the 6.0 config keys', function () {
    $config = require __DIR__.'/../../config/google-authenticate.php';

    expect(array_keys($config))->toEqualCanonicalizing([
        'redirect_url', 'register_enabled', 'remember', 'domains', 'super_admin_role',
        'super_admin_listener', 'super_admin_gate', 'user_columns', 'middleware', 'throttle',
    ])
        ->and(array_keys($config['domains']))->toEqualCanonicalizing(['allowed', 'disabled', 'allow_any'])
        ->and($config['super_admin_listener'])->toBe(GrantSuperAdminRole::class)
        ->and($config['user_columns'])->toEqual([
            'name' => ['name'],
            'email' => ['email'],
            'email_verified_at' => ['email_verified'],
        ]);
});

it('trims and drops blanks in the comma separated allowed domains env', function () {
    putenv('GOOGLE_AUTH_ALLOWED_DOMAINS=a.com, b.com ,,c.com');

    try {
        $config = require __DIR__.'/../../config/google-authenticate.php';
    } finally {
        putenv('GOOGLE_AUTH_ALLOWED_DOMAINS');
    }

    expect(array_values($config['domains']['allowed']))->toBe(['a.com', 'b.com', 'c.com']);
});

function reloadRoutes(?string $throttle): void
{
    config([
        'google-authenticate.throttle' => $throttle,
        'services.google' => ['client_id' => 'id', 'client_secret' => 'secret', 'redirect' => 'https://app.test/login/google/callback'],
    ]);
    Route::setRoutes(new RouteCollection);
    require __DIR__.'/../../routes/web.php';
    Route::getRoutes()->refreshNameLookups();
}

it('throttles the google routes at 10 per minute by default', function () {
    config(['services.google' => ['client_id' => 'id', 'client_secret' => 'secret', 'redirect' => 'https://app.test/login/google/callback']]);

    foreach (range(1, 10) as $_) {
        $this->get(route('google.auth.login'))->assertRedirect();
    }

    $this->get(route('google.auth.login'))->assertStatus(429);
});

it('puts no throttle on the routes when the value is empty', function (?string $value) {
    reloadRoutes($value);

    foreach (['google.auth.login', 'google.auth.callback'] as $name) {
        expect(collect(Route::getRoutes()->getByName($name)->gatherMiddleware())->filter(fn ($m) => str_starts_with($m, 'throttle')))->toBeEmpty();
    }
})->with([null, '']);

it('applies a custom throttle value', function () {
    reloadRoutes('3,1');

    expect(Route::getRoutes()->getByName('google.auth.callback')->gatherMiddleware())->toContain('throttle:3,1');
    foreach (range(1, 3) as $_) {
        $this->get(route('google.auth.login'))->assertRedirect();
    }
    $this->get(route('google.auth.login'))->assertStatus(429);
});
