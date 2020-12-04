<?php

namespace MorningTrain\Laravel\Permissions\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasPermissions;
use Spatie\Permission\Traits\HasRoles;

class PermissionGroup extends Model
{

    use HasPermissions;
    use HasRoles;

    protected $fillable = ['guard_name'];

    public function __construct(array $attributes = [])
    {
        $attributes['guard_name'] = $attributes['guard_name'] ?? config('auth.defaults.guard');

        parent::__construct($attributes);
    }

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

                        $merged_role_permissions = array_unique(array_merge(
                            [$group->slug],
                            $existing_role_permissions,
                            $simplified_group_permissions
                        ));

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

        $other_roles = Role::query()->whereNotIn('id', array_keys($role_permissions_map))->get();

        if($other_roles->isNotEmpty()) {
            foreach($other_roles as $other_role) {
                $other_role->syncPermissions([]);
            }
        }
    }

    public static function syncGroups($group_identifiers)
    {

        $existing = static::query()->get()->keyBy('slug');

        $sort_index = 0;

        $group_identifiers_collection = collect($group_identifiers);

        $group_identifiers_collection = $group_identifiers_collection->groupBy(function($group_identifier) {
            $identifier_fragments = explode('.', $group_identifier);
            return array_shift($identifier_fragments);
        });

        foreach($group_identifiers_collection->toArray() as $group_category => $group_identifiers) {
            while(!empty($group_identifiers)) {
                $group_identifier = array_shift($group_identifiers);

                if($existing->has($group_identifier)) {
                    $group = $existing->get($group_identifier);
                    $existing->forget($group_identifier);
                } else {
                    $group = new PermissionGroup();
                    $group->slug = $group_identifier;
                }

                $group->sort_index =  $sort_index;
                $group->category = $group_category;
                $group->save();

                $sort_index++;
            }
        }

        if($existing->isNotEmpty()) {
            PermissionGroup::query()->whereIn('slug', $existing->keys())->delete();
        }

    }


}
