<?php

namespace Statikbe\GoogleAuthenticate\Events;

use Illuminate\Database\Eloquent\Model;
use Laravel\Socialite\AbstractUser;
use Statikbe\GoogleAuthenticate\GoogleAuthService;

/**
 * Dispatched once, right after a brand-new user row was created by a Google login that
 * passed every check (verified email, domain allow/disable, hosted domain) and right
 * before the user is logged in. A listener that throws aborts the login.
 *
 * $hostedDomain is the Google `hd` claim; an empty string when Google sent none, which is
 * only possible when no `domains.allowed` list is configured, which new users only get with `domains.allow_any`.
 */
readonly class GoogleUserRegistered
{
    public function __construct(
        public Model $user,
        public AbstractUser $googleUser,
        public string $hostedDomain,
    ) {}

    /**
     * Reads the email from the column `user_columns` maps the Google email to, so a renamed column still works.
     */
    public function isFromAllowedDomain(): bool
    {
        $column = array_search(['email'], config('google-authenticate.user_columns', []), true);

        return app(GoogleAuthService::class)->isAllowedDomain(
            (string) $this->user->getAttribute(is_string($column) ? $column : 'email'),
            $this->hostedDomain,
        );
    }
}
