<?php

namespace MorningTrain\Laravel\Permissions\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Traits\HasPermissions;
use Spatie\Permission\Traits\HasRoles;

class PermissionGroup extends Model
{

    use HasPermissions;
    use HasRoles;

    public static function syncRolePermissions()
    {

        $groups = static::query()
            ->with('roles')
            ->with('permissions')
            ->get();

        $role_permissions_map = [];
        $roles_map = [];

        if($groups->isNotEmpty()) {
            foreach($groups as $group) {

                $group_roles = $group->roles;

                $simplified_group_permissions = $group->permissions->pluck('name')->all();

                if($group_roles->isNotEmpty()) {
                    foreach ($group_roles as $group_role) {

                        $role_id = $group_role->id;
                        data_set($roles_map, $role_id, $group_role);

                        $existing_role_permissions = data_get($role_permissions_map, $role_id, []);

                        $merged_role_permissions = array_unique(array_merge($existing_role_permissions, $simplified_group_permissions));

                        data_set($role_permissions_map, $role_id, $merged_role_permissions);

                    }
                }

            }
        }

        if(!empty($roles_map)) {
            foreach($roles_map as $role) {
                $role_permissions = data_get($role_permissions_map, $role->id);
                $role->syncPermissions($role_permissions);
            }
        }

    }

    public static function syncGroups($group_identifiers)
    {

        $existing = static::query()->get()->keyBy('slug');

        while(!empty($group_identifiers)) {
            $group_identifier = array_pop($group_identifiers);

            if($existing->has($group_identifier)) {
                $existing->forget($group_identifier);
            } else {
                $group = new PermissionGroup();
                $group->slug = $group_identifier;
                $group->save();
            }

        }

        $existing->delete();
    }


}
