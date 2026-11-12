<?php

namespace Statikbe\GoogleAuthenticate\Tests\Support;

use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $table = 'users';

    protected $fillable = ['name', 'first_name', 'email', 'email_verified_at', 'password', 'provider', 'provider_id'];
}
