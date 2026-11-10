<?php

use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Statikbe\GoogleAuthenticate\Tests\Support\User;

beforeEach(function () {
    config([
        'app.url' => 'https://app.test',
        'services.google' => ['client_id' => 'id', 'client_secret' => 'secret', 'redirect' => 'https://app.test/login/google/callback'],
    ]);
});

// Seeds the session directly: url()->previous() already rewrites a hostile Referer into an on-site URL,
// so only this path exercises the guard against the raw value.
function loginUrlAfterError(string $flashed): string
{
    return test()->withSession(['googleLoginUrl' => $flashed])
        ->get(route('google.auth.callback', ['error' => 'x']))
        ->headers->get('Location');
}

it('only redirects back to safe login urls', function (string $referer, string $expected) {
    // Redirect::to() makes relative targets absolute, so compare against the same normalisation.
    expect(loginUrlAfterError($referer))->toBe(url($expected));
})->with([
    'protocol-relative' => ['//evil.com', '/'],
    'slash backslash' => ['/\evil.com', '/'],
    'double backslash' => ['\\\\evil.com', '/'],
    'javascript scheme' => ['javascript:alert(1)', '/'],
    'data scheme' => ['data:text/html,x', '/'],
    'foreign host' => ['https://evil.com/login', '/'],
    'ftp scheme on app host' => ['ftp://app.test/x', '/'],
    'empty' => ['', '/'],
    'relative path' => ['/login', '/login'],
    'same host' => ['https://app.test/login', 'https://app.test/login'],
    'same host other case' => ['https://APP.test/login', 'https://APP.test/login'],
    'same host other port' => ['https://app.test:8443/login', 'https://app.test:8443/login'],
]);

it('never leaves the app host for a hostile referer', function (string $referer) {
    $this->withHeader('Referer', $referer)->get(route('google.auth.login'));

    expect($this->get(route('google.auth.callback', ['error' => 'x']))->headers->get('Location'))
        ->toStartWith('http://localhost');
})->with(['//evil.com', '/\\evil.com', 'javascript:alert(1)', 'https://evil.com/login']);

it('redirects to google and flashes the sanitised login url', function () {
    $response = $this->withHeader('Referer', '//evil.com')->get(route('google.auth.login'));

    $response->assertRedirectContains('accounts.google.com')->assertSessionHas('googleLoginUrl', '/');
});

it('redirects to the login url with an error when google reports one', function () {
    $this->withHeader('Referer', '/login')->get(route('google.auth.login'));

    $this->get(route('google.auth.callback', ['error' => 'access_denied']))
        ->assertRedirect('/login')
        ->assertSessionHas('danger', __('google-authenticate::messages.error'));

    $this->assertGuest();
    expect(User::count())->toBe(0);
});

it('redirects with an error when the oauth state is invalid', function () {
    Socialite::shouldReceive('driver->user')->andThrow(new InvalidStateException);

    $this->get(route('google.auth.callback'))
        ->assertRedirect('/')
        ->assertSessionHas('danger', __('google-authenticate::messages.error'));

    expect(Auth::check())->toBeFalse();
});
