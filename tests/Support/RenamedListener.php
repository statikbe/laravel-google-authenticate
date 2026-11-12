<?php

namespace Statikbe\GoogleAuthenticate\Tests\Support;

use Illuminate\Database\Eloquent\Model;
use Statikbe\GoogleAuthenticate\Listeners\GrantSuperAdminRole;

// Stands in for a project swapping spatie for another role library: only assignRole() is overridden.
class RenamedListener extends GrantSuperAdminRole
{
    /** @var list<string> */
    public static array $assigned = [];

    protected function assignRole(Model $user, string $roleName): void
    {
        self::$assigned[] = $roleName.':'.$user->getAttribute('email');
    }
}
