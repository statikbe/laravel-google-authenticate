<?php

namespace Statikbe\GoogleAuthenticate\Tests\Support;

use Illuminate\Foundation\Auth\User as Authenticatable;

// Deliberately unrelated to Support\User, the configured auth user model in TestCase.
class Member extends Authenticatable
{
    protected $table = 'members';

    protected $fillable = ['name', 'email', 'email_verified_at'];
}
