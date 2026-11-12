<?php

namespace Statikbe\GoogleAuthenticate\Events;

use Illuminate\Database\Eloquent\Model;
use Laravel\Socialite\AbstractUser;

/**
 * Dispatched inside the login transaction whenever an existing row, matched by email only, is linked
 * to a Google account. Its old sessions and API tokens were created before the Google identity was
 * proven, so a listener is the place to revoke them.
 *
 * $googleUser carries the OAuth access and refresh token: never queue a listener that receives this
 * event as-is, the token would end up in the queue payload.
 */
readonly class GoogleAccountLinked
{
    public function __construct(
        public Model $user,
        public AbstractUser $googleUser,
    ) {}
}
