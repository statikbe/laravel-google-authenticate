<?php

namespace Statikbe\GoogleAuthenticate\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Socialite\SocialiteServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Statikbe\GoogleAuthenticate\GoogleAuthenticateServiceProvider;
use Statikbe\GoogleAuthenticate\Tests\Support\User;

// The controller's return type is the host app's App\Models\User, which doesn't exist in a package.
if (! class_exists('App\Models\User')) {
    class_alias(User::class, 'App\Models\User');
}

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            SocialiteServiceProvider::class,
            GoogleAuthenticateServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('auth.providers.users.model', User::class);
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->string('provider')->nullable();
            $table->string('provider_id')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }
}
