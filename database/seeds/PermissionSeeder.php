<?php

namespace MorningTrain\Laravel\Permissions\Database\Seeds;

use Illuminate\Database\Seeder;
use MorningTrain\Laravel\Resources\ResourceRepository;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    public function run()
    {
        foreach (ResourceRepository::getRestrictedOperationIdentifiers() as $name) {
            $permission = Permission::create(['name' => $name]);
            $roles      = config('permissions.permission_roles.'.$name, []) ?? [];

            if (!empty($roles)) {
                $permission->syncRoles($roles);
            }
        }
    }
}
