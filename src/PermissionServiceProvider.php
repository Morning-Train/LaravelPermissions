<?php

namespace MorningTrain\Laravel\Permissions;


use Illuminate\Filesystem\Filesystem;
use MorningTrain\Laravel\Permissions\Services\PermissionRegistrar;
use Spatie\Permission\PermissionRegistrar as SpatiePermissionRegistrar;
use Spatie\Permission\PermissionServiceProvider as SpatiePermissionProvider;

class PermissionServiceProvider extends SpatiePermissionProvider
{
    public function boot(SpatiePermissionRegistrar $permissionLoader, Filesystem $filesystem)
    {
        // Here we "hack" our way into overriding the Spatie PermissionRegistrar
        parent::boot(app()->make(PermissionRegistrar::class), $filesystem);
    }

}
