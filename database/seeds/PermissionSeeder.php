<?php

namespace MorningTrain\Laravel\Resources\Database\Seeds;

use Illuminate\Database\Seeder;
use MorningTrain\Laravel\Resources\ResourceRepository;

class PermissionSeeder extends Seeder
{
    public function run()
    {
        $this->seedPermissions(ResourceRepository::getAllPermissions());
    }

    protected function seedPermissions(array $permissions)
    {
        // We assume this means the package is installed
        if (class_exists('\Spatie\Permission\PermissionRegistrar')) {

            // Reset cached roles and permissions
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

            foreach ($permissions as $permission) {
                \Spatie\Permission\Models\Permission::create(['name' => $permission]);
            }
        }
    }
}
