<?php

namespace Statikbe\GoogleAuthenticate\Events;

use Illuminate\Database\Eloquent\Model;
use Laravel\Socialite\AbstractUser;

/**
 * Dispatched once, right after a brand-new user row was created by a Google login that
 * passed every check (verified email, domain allow/disable, hosted domain) and right
 * before the user is logged in. A listener that throws aborts the login.
 *
 * $hostedDomain is the Google `hd` claim; an empty string when Google sent none, which is
 * only possible when no `domains.allowed` list is configured.
 */
readonly class GoogleUserRegistered
{
    public function __construct(
        public Model        $user,
        public AbstractUser $googleUser,
        public string       $hostedDomain,
    ) {}
}
