<?php

namespace MorningTrain\Laravel\Permissions;


use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use MorningTrain\Laravel\Permissions\Console\RefreshPermissions;
use MorningTrain\Laravel\Permissions\Console\RefreshRoles;

class LaravelPermissionsServiceProvider extends ServiceProvider
{
    /**
     * Register bindings in the container.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/permissions.php',
            'permissions'
        );
    }

    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/permissions.php' => config_path('permissions.php'),
            ], 'mt-config');

            $this->commands([
                RefreshRoles::class,
                RefreshPermissions::class,
            ]);
        }

        // Implicitly grant "admin" role all permissions
        // This works in the app by using gate-related functions like auth()->user->can() and @can()
        Gate::before(function ($user, $ability) {
            return Permissions::isSuperAdmin($user) ? true : null;
        });
    }

}
