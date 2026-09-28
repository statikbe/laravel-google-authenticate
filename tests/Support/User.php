<?php

namespace Statikbe\GoogleAuthenticate\Tests\Support;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Statikbe\GoogleAuthenticate\Traits\HasGoogleAuth;

class User extends Authenticatable
{
    use HasGoogleAuth;

    protected $table = 'users';

    protected $fillable = ['name', 'email', 'email_verified_at', 'password'];
}
