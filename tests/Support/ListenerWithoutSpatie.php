<?php

namespace Statikbe\GoogleAuthenticate\Tests\Support;

use Statikbe\GoogleAuthenticate\Listeners\GrantSuperAdminRole;

// spatie is a require-dev here, so the "not installed" branch is reached through the overridable check.
class ListenerWithoutSpatie extends GrantSuperAdminRole
{
    protected function permissionsAvailable(): bool
    {
        return false;
    }
}
