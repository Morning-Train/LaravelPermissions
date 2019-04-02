<?php

namespace MorningTrain\Laravel\Permissions\Database\Seeds;

use Illuminate\Database\Seeder;
use MorningTrain\Laravel\Resources\ResourceRepository;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    public function run()
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (ResourceRepository::getAllPermissions() as $permission) {
            Permission::create(['name' => $permission]);
        }
    }
}
