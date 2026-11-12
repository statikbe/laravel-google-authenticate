<?php

namespace Statikbe\GoogleAuthenticate;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Spatie\Permission\Guard;
use Statikbe\GoogleAuthenticate\Events\GoogleUserRegistered;

class GoogleAuthenticateServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     */
    public function boot(): void
    {
        // loaders
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'google-authenticate');

        $this->registerSuperAdminGate();

        $this->registerSuperAdminListener();

        // Publishing is only necessary when using the CLI:
        if ($this->app->runningInConsole()) {
            $this->bootForConsole();
        }
    }

    /**
     * Register any package services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/google-authenticate.php', 'google-authenticate'
        );

        // mergeConfigFrom is shallow, so overlay key by key: the app's services.google wins. Merged at
        // register time from a config file so the env() reads survive config:cache.
        $defaults = require __DIR__.'/../config/google-services.php';
        $this->app['config']->set('services.google', array_merge($defaults, $this->app['config']->get('services.google') ?? []));

        $this->app->singleton(GoogleAuthService::class);
    }

    /**
     * Give the configured role every ability. Returns null otherwise, so normal checks still apply.
     */
    protected function registerSuperAdminGate(): void
    {
        $role = config('google-authenticate.super_admin_role');

        if (! config('google-authenticate.super_admin_gate') || ! $role) {
            return;
        }

        Gate::before(fn ($user, $ability) => method_exists($user, 'hasRole') && $this->hasRoleOnOwnGuard($user, $role) ? true : null);
    }

    /**
     * Scoped to the user model's own guard: a same-named role under another guard (say 'api') must not grant everything.
     * Without spatie the model's own hasRole() is all there is, and Guard does not exist.
     */
    protected function hasRoleOnOwnGuard(object $user, string $role): bool
    {
        return $this->permissionsAvailable()
            ? $user->hasRole($role, Guard::getDefaultName($user))
            : $user->hasRole($role);
    }

    protected function permissionsAvailable(): bool
    {
        return class_exists(Guard::class);
    }

    /**
     * Fails loudly on a missing class, so a typo in the config does not silently grant nothing.
     */
    protected function registerSuperAdminListener(): void
    {
        $listener = config('google-authenticate.super_admin_listener');

        if (! $listener || ! config('google-authenticate.super_admin_role')) {
            return;
        }

        if (! class_exists($listener)) {
            throw new InvalidArgumentException("google-authenticate.super_admin_listener: class [{$listener}] does not exist. Set a listener class, or null for none.");
        }

        Event::listen(GoogleUserRegistered::class, $listener);
    }

    /**
     * Console-specific booting.
     */
    protected function bootForConsole(): void
    {
        // publish the translations
        $langPath = 'vendor/google-authenticate';
        $langPath = (function_exists('lang_path'))
            ? lang_path($langPath)
            : resource_path('lang/'.$langPath);
        $this->publishes([
            __DIR__.'/../resources/lang' => $langPath,
        ], 'google-lang');

        $this->publishes([
            __DIR__.'/../database/migrations/2024_01_01_000000_add_google_provider_to_users_table.php' => database_path('migrations/'.date('Y_m_d_His').'_add_google_provider_to_users_table.php'),
        ], 'google-migrations');

        // publishes config file
        $this->publishes([
            __DIR__.'/../config/google-authenticate.php' => config_path('google-authenticate.php'),
        ], 'google-config');
    }
}
