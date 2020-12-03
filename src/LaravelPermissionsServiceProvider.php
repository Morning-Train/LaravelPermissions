<?php

namespace MorningTrain\Laravel\Permissions;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use MorningTrain\Laravel\Dev\Commands\System\Events\SystemBuilding;
use MorningTrain\Laravel\Dev\Commands\System\Events\SystemRefreshing;
use MorningTrain\Laravel\Dev\Commands\System\Events\SystemStartsBuilding;
use MorningTrain\Laravel\Permissions\Console\RefreshPermissions;
use MorningTrain\Laravel\Permissions\Console\RefreshRoles;
use MorningTrain\Laravel\Resources\Support\Contracts\Operation;

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

        Operation::macro('isRestricted', function($identifier){
            return Permissions::isRestricted($identifier);
        });

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

            $this->publishes([
                __DIR__ . '/../database/migrations/2020_12_03_000001_create_permissions_group_table.php' => database_path('migrations/2020_12_03_000001_create_permissions_group_table.php'),
            ], 'permissions-group');

            $this->commands([
                RefreshRoles::class,
                RefreshPermissions::class,
            ]);

            if(class_exists(SystemStartsBuilding::class)) {
                Event::listen(SystemStartsBuilding::class, function (SystemStartsBuilding $event) {
                    if(isset($event->command)) {
                        $event->command->call('permission:cache-reset');
                    }
                });
            }

            if(class_exists(SystemBuilding::class)) {
                Event::listen([SystemBuilding::class], function(SystemBuilding $event) {
                    if(isset($event->command)) {
                        $event->command->call(RefreshPermissions::class);
                    }
                });
            }

            if(class_exists(SystemRefreshing::class)) {
                Event::listen([SystemRefreshing::class], function(SystemRefreshing $event) {
                    if(isset($event->command)) {
                        $event->command->call('permission:cache-reset');
                        $event->command->call(RefreshPermissions::class);
                    }
                });
            }

        }

        // Implicitly grant "admin" role all permissions
        // This works in the app by using gate-related functions like auth()->user->can() and @can()
        Gate::before(function ($user, $ability) {
            return Permissions::isSuperAdmin($user) ? true : null;
        });

    }

}
