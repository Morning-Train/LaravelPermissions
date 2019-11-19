<?php

namespace MorningTrain\Laravel\Permissions\Database\Seeds;

use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use MorningTrain\Laravel\Permissions\Permissions;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    public function run()
    {
        $permissions = array_unique(array_merge(
            array_keys(config('permissions.custom_permission_roles', [])),
            Permissions::getRestrictedOperationIdentifiers()
        ));

        $permissionRoles = array_merge_recursive(
            config('permissions.permission_roles', []),
            config('permissions.custom_permission_roles', [])
        );

        foreach ($permissions as $name) {
            $permission = Permission::create(['name' => $name]);
            $roles      = Arr::get($permissionRoles, $permission->name, []);

            if (!empty($roles)) {
                $permission->syncRoles($roles);
            }
        }
    }
}
