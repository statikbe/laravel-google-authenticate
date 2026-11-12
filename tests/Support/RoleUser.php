<?php

namespace Statikbe\GoogleAuthenticate\Tests\Support;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;

class RoleUser extends Authenticatable
{
    use HasRoles;

    protected $table = 'users';

    protected $guarded = [];

    // Spatie resolves guards from the auth provider model, which is Support\User here, not this class.
    protected string $guard_name = 'web';
}
