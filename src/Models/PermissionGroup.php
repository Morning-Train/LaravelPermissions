<?php

namespace MorningTrain\Laravel\Permissions\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Permission;
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

    public function users()
    {
        return $this->belongsToMany(config('permission.model.user', 'App\Models\User'));
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

    public static function syncUserPermissions()
    {

        $groups = static::query()
            ->with('users')
            ->with('permissions')
            ->get();

        $user_permissions_map = [];
        $users_map = [];

        if($groups->isNotEmpty()) {
            foreach($groups as $group) {

                $group_users = $group->users;

                $simplified_group_permissions = $group->permissions->pluck('name')->all();

                if($group_users->isNotEmpty()) {
                    foreach ($group_users as $group_user) {

                        $user_id = $group_user->id;
                        data_set($users_map, $user_id, $group_user);

                        $existing_user_permissions = data_get($user_permissions_map, $user_id, []);

                        $merged_user_permissions = array_unique(array_merge(
                            [$group->slug],
                            $existing_user_permissions,
                            $simplified_group_permissions
                        ));

                        data_set($user_permissions_map, $user_id, $merged_user_permissions);

                    }
                }

            }
        }

        if(!empty($users_map)) {
            foreach($users_map as $user) {
                $user_permissions = data_get($user_permissions_map, $user->id);
                $user->syncPermissions($user_permissions);
            }
        }

        $user_class = config('permission.model.user', 'App\Models\User');
        $other_users = call_user_func($user_class . '::query')->whereNotIn('id', array_keys($user_permissions_map))->get();

        if($other_users->isNotEmpty()) {
            foreach($other_users as $other_user) {
                $other_user->syncPermissions([]);
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

    public function getListOfRolesAttribute()
    {
        return $this->roles->pluck('name');
    }

    public function getListOfUsersAttribute()
    {
        return $this->users->pluck('id');
    }

    public static function migrate($slug, $permissions, $role_names)
    {


        $identifier_fragments = explode('.', $slug);
        $group_category = array_shift($identifier_fragments);

        $group = PermissionGroup::whereSlug($slug)->first();
        if ($group === null) {
            $group = new PermissionGroup();
            $group->slug = $slug;
        }

        $group->category = $group_category;
        $group->save();

        $new_permissions = array_unique(
            array_merge(
                [$group->slug],
                $permissions
            )
        );

        foreach ($new_permissions as $permission_slug) {
            Permission::findOrCreate($permission_slug);
        }

        $group->syncPermissions($permissions);

        $roles = Role::query()->whereIn('name', $role_names)->get();

        if($roles->isNotEmpty()) {
            foreach($roles as $role) {

                $group->syncRoles($roles);

                $merged_role_permissions = array_unique(
                    array_merge(
                        $new_permissions,
                        $role->permissions()->pluck('name')->toArray(),
                    )
                );

                $role->syncPermissions($merged_role_permissions);
            }
        }

    }

    public static function migrateRemove($slug, $permissions, $role_names)
    {
        $group = PermissionGroup::whereSlug($slug)->first();

        $permissions_to_remove = collect($permissions);

        $group->revokePermissionTo($permissions_to_remove);

        $roles = \App\Models\Role::query()->whereIn('name', $role_names)->get();

        $roles->each(
            function ($role) use ($permissions_to_remove, $slug) {
                $allPermissions = $role->permissions()->pluck('name');

                $permissions_to_keep = $allPermissions
                    ->reject(
                        function ($permissionName) use ($permissions_to_remove, $role, $slug) {
                            $shouldBeRemoved = $permissions_to_remove->contains($permissionName);

                            if (!$shouldBeRemoved) {
                                return false;
                            }

                            $permission = Permission::findByName($permissionName);

                            $roleHasGroupWithPermission = $role->permissionGroups()
                                ->where('slug', '!=', $slug)
                                ->whereHas(
                                    'permissions',
                                    function ($q) use ($permission) {
                                        $q->where(
                                            config('permission.table_names.permissions') . '.id',
                                            $permission->getKey()
                                        );
                                    }
                                )
                                ->exists();

                            return !$roleHasGroupWithPermission;
                        }
                    )
                    ->toArray();

                if (count($permissions_to_keep) === $allPermissions->count()) {
                    return;
                }

                $role->syncPermissions($permissions_to_keep);
            }
        );
    }
}
