<?php

namespace MorningTrain\Laravel\Permissions;


use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use MorningTrain\Laravel\Permissions\Console\RefreshPermissions;

class LaravelPermissionsServiceProvider extends ServiceProvider
{
    /**
     * Register bindings in the container.
     *
     * @return void
     */
    public function register()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/permissions.php' => config_path('permissions.php'),
            ],
                'laravel-permissions-config');
        }
    }

    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/permissions.php',
            'permissions'
        );

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                RefreshPermissions::class,
            ]);
        }

        // Implicitly grant "admin" role all permissions
        // This works in the app by using gate-related functions like auth()->user->can() and @can()
        Gate::before(function ($user, $ability) {
            if (method_exists($user, 'hasRole')) {
                return $user->hasRole(config('permissions.super_admin')) ? true : null;
            }
        });
    }

}
