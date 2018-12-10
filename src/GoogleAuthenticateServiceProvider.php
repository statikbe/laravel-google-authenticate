<?php

namespace Statikbe\GoogleAuthenticate;

use Illuminate\Support\ServiceProvider;

class GoogleAuthenticateServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        // $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'statikbe');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'Statikbe');
        $this->publishes([
            __DIR__.'/../database/migrations/add_google_provider_to_user_table.php.stub' => database_path('migrations/'.date('Y_m_d_His', time()).'_add_google_provider_to_user_table.php'),
        ], 'migrations');

        $this->loadRoutesFrom(__DIR__.'/routes.php');

        // Publishing is only necessary when using the CLI.
        if ($this->app->runningInConsole()) {
            $this->bootForConsole();
        }
    }

    /**
     * Register any package services.
     *
     * @return void
     */
    public function register()
    {

        // Register the service the package provides.
        $this->app->singleton('GoogleAuthenticate', function ($app) {
            return new GoogleAuthenticateController();
        });
    
    }
    

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['GoogleAuthenticate'];
    }
    
    /**
     * Console-specific booting.
     *
     * @return void
     */
    protected function bootForConsole()
    {

        // Publishing the views.
        $this->publishes([
            __DIR__.'/../resources/views' => base_path('resources/views/vendor/Statikbe'),
        ], 'GoogleAuthenticate.views');
        
    }
}
