<?php

namespace Statikbe\GoogleAuthenticate\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Socialite\SocialiteServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Statikbe\GoogleAuthenticate\GoogleAuthenticateServiceProvider;
use Statikbe\GoogleAuthenticate\Tests\Support\User;

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

        // Suite default keeps 5.x semantics (empty allow-list creates users); the D9 tests set it to false.
        $app['config']->set('google-authenticate.domains.allow_any', true);

        // Support\User has no HasRoles, so the default listener would abort every login with allowed domains. GrantSuperAdminRoleTest turns it on.
        $app['config']->set('google-authenticate.super_admin_listener', null);

        // The permission provider is not registered: only its config and tables are needed for the HasRoles fixtures.
        $app['config']->set('permission', require $this->spatiePath('config/permission.php'));
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('first_name')->nullable();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->string('provider')->nullable();
            $table->string('provider_id')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        // The stub name differs between spatie/laravel-permission versions, so match on the prefix.
        $stubs = glob($this->spatiePath('database/migrations/create_permission_tables.php*'));
        (require $stubs[0])->up();
    }

    private function spatiePath(string $path): string
    {
        return dirname(__DIR__).'/vendor/spatie/laravel-permission/'.$path;
    }
}
