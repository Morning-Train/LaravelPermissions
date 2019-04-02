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
    }

    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
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
                return $user->hasRole('admin') ? true : null;
            }
        });
    }

}
