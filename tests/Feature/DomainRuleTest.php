<?php

use Laravel\Socialite\Two\User as SocialiteUser;
use Statikbe\GoogleAuthenticate\Events\GoogleUserRegistered;
use Statikbe\GoogleAuthenticate\Facades\GoogleAuthenticate;
use Statikbe\GoogleAuthenticate\GoogleAuthService;
use Statikbe\GoogleAuthenticate\Tests\Support\Member;

function ruleDomains(array $allowed = ['statik.be'], array $disabled = []): void
{
    config(['google-authenticate.domains' => ['allowed' => $allowed, 'disabled' => $disabled, 'allow_any' => false]]);
}

function googleWithEmail(string $email): SocialiteUser
{
    return (new SocialiteUser)->setRaw([])->map(['id' => '1', 'email' => $email]);
}

it('is false for everything when allowed is empty', function () {
    ruleDomains(allowed: []);

    expect(app(GoogleAuthService::class)->isAllowedDomain('jan@statik.be', 'statik.be'))->toBeFalse();
    expect(app(GoogleAuthService::class)->isAllowedDomain('jan@statik.be'))->toBeFalse();
});

it('is false for a disabled domain even when it is also allowed', function () {
    ruleDomains(allowed: ['statik.be'], disabled: ['statik.be']);

    expect(app(GoogleAuthService::class)->isAllowedDomain('jan@statik.be', 'statik.be'))->toBeFalse();
});

it('is false when the email domain is not allowed', function () {
    ruleDomains();

    expect(app(GoogleAuthService::class)->isAllowedDomain('jan@other.be', 'statik.be'))->toBeFalse();
});

it('is false when the hosted domain is not allowed', function () {
    ruleDomains();

    expect(app(GoogleAuthService::class)->isAllowedDomain('jan@statik.be', 'other.be'))->toBeFalse();
});

it('checks the email domain only when the hosted domain is null', function () {
    ruleDomains();

    expect(app(GoogleAuthService::class)->isAllowedDomain('jan@statik.be'))->toBeTrue();
    expect(app(GoogleAuthService::class)->isAllowedDomain('jan@other.be'))->toBeFalse();
});

it('treats an empty hosted domain as a personal account', function () {
    ruleDomains();

    expect(app(GoogleAuthService::class)->isAllowedDomain('jan@statik.be', ''))->toBeFalse();
});

it('compares case-insensitively', function () {
    ruleDomains(allowed: ['Statik.BE'], disabled: ['EVIL.be']);

    expect(app(GoogleAuthService::class)->isAllowedDomain('Jan@STATIK.be', 'STATIK.BE'))->toBeTrue();
    expect(app(GoogleAuthService::class)->isAllowedDomain('Jan@Evil.BE'))->toBeFalse();
});

it('is false for a malformed email', function () {
    ruleDomains();

    expect(app(GoogleAuthService::class)->isAllowedDomain('not-an-email'))->toBeFalse();
    expect(app(GoogleAuthService::class)->isAllowedDomain('a@b@statik.be'))->toBeFalse();
});

it('is reachable through the facade', function () {
    ruleDomains();

    expect(GoogleAuthenticate::isAllowedDomain('jan@statik.be', 'statik.be'))->toBeTrue();
    expect(GoogleAuthenticate::isAllowedDomain('jan@other.be', 'statik.be'))->toBeFalse();
});

it('registers the facade alias in composer.json', function () {
    $composer = json_decode(file_get_contents(__DIR__.'/../../composer.json'), true);

    expect($composer['extra']['laravel']['aliases'])->toBe(['GoogleAuthenticate' => GoogleAuthenticate::class]);
});

it('exposes the rule on the registered event', function () {
    ruleDomains();
    $user = new Member(['email' => 'jan@statik.be']);
    $google = googleWithEmail('jan@statik.be');

    expect((new GoogleUserRegistered($user, $google, 'statik.be'))->isFromAllowedDomain())->toBeTrue();
    expect((new GoogleUserRegistered($user, $google, ''))->isFromAllowedDomain())->toBeFalse();
    expect((new GoogleUserRegistered($user, $google, 'other.be'))->isFromAllowedDomain())->toBeFalse();
});

it('reads the email from the mapped email column on the event', function () {
    ruleDomains();
    config(['google-authenticate.user_columns' => ['mail' => ['email'], 'name' => ['name']]]);
    $user = new Member;
    $user->setAttribute('mail', 'jan@statik.be');

    expect((new GoogleUserRegistered($user, googleWithEmail('other@evil.be'), 'statik.be'))->isFromAllowedDomain())->toBeTrue();
});

it('keeps assertLoginAllowed independent of the privilege rule and the facade free of role helpers', function () {
    $service = file_get_contents(__DIR__.'/../../src/GoogleAuthService.php');
    preg_match('/private function assertLoginAllowed.*?\n    }\n/s', $service, $match);

    expect($match[0])->not->toContain('isAllowedDomain');
    expect(file_get_contents(__DIR__.'/../../src/Facades/GoogleAuthenticate.php'))->not->toMatch('/assign|role/i');
});
