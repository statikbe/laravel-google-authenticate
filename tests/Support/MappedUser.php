<?php

namespace Statikbe\GoogleAuthenticate\Tests\Support;

use Illuminate\Foundation\Auth\User as Authenticatable;

class MappedUser extends Authenticatable
{
    protected $table = 'mapped_users';

    protected $guarded = [];
}
