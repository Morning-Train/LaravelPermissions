<?php

namespace MorningTrain\Laravel\Resources\Database\Seeds;

use Illuminate\Database\Seeder;
use MorningTrain\Laravel\Resources\ResourceRepository;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    public function run()
    {
        $this->seedPermissions(ResourceRepository::getAllPermissions());
    }

    protected function seedPermissions(array $permissions)
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }
    }
}
