<?php

namespace Statikbe\GoogleAuthenticate\Facades;

use Illuminate\Support\Facades\Facade;
use Statikbe\GoogleAuthenticate\GoogleAuthService;

/**
 * @method static bool isAllowedDomain(string $email, ?string $hostedDomain = null)
 *
 * @see GoogleAuthService
 */
class GoogleAuthenticate extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return GoogleAuthService::class;
    }
}
