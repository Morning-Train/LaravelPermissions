<?php

namespace MorningTrain\Laravel\Permissions\Console;


use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use MorningTrain\Laravel\Permissions\Models\PermissionGroup;
use MorningTrain\Laravel\Permissions\Permissions;
use MorningTrain\Laravel\Resources\ResourceRepository;
use Spatie\Permission\Models\Permission;

class RefreshPermissions extends Command
{
    protected $name        = 'mt:refresh-permissions';
    protected $description = 'Refreshes permissions';
    protected $target      = [];

    public function handle()
    {
        $this->call('mt:refresh-roles');

        $this->info('Refreshing application permissions.');

        // All permissions which need to be created
        $this->target = array_unique(array_merge(
            array_keys(config('permissions.custom_permission_roles', [])),
            Permissions::getRestrictedOperationIdentifiers(),
            array_keys(config('permissions.groups', [])),
            Arr::flatten(config('permissions.groups', [])),
        ));

        $this->deleteDeprecated();

        $this->refreshPermissions();
        $this->syncRoles();
        $this->syncGroups();

        $this->info('Done refreshing permissions.');
    }

    protected function deleteDeprecated()
    {
        // Delete all deprecated
        $deleted = Permission::whereNotIn('name', $this->target)->delete();
        $this->info("Deleted $deleted deprecated " . Str::plural('permission', $deleted));
    }

    protected function refreshPermissions()
    {
        // Create all new
        $existing = Permission::query()->get()->pluck('name')->all();
        $new      = array_diff($this->target, $existing);
        $count    = count($new);

        if ($count > 0) {
            $this->info("Creating " . $count . " " . Str::plural('permission', $count));
            $bar = $this->output->createProgressBar($count);

            foreach ($new as $permission) {
                Permission::create(['name' => $permission]);
                $bar->advance();
            }

            $bar->finish();
            $this->info('');
        }
    }

    protected function syncRoles()
    {
        $this->info('Syncing permission roles.');

        $permissions = array_merge_recursive(
            config('permissions.permission_roles', []),
            config('permissions.custom_permission_roles', [])
        );

        Permission::query()->get()->each(function ($permission) {
            $roles = Permissions::findRolesForPermission($permission->name);
            $permission->syncRoles($roles);
        });
    }

    protected function syncGroups()
    {
        $this->info('Syncing permission groups.');

        $groups_from_config = config('permissions.groups', []);

        if(empty($groups_from_config) || !is_array($groups_from_config)) {
            return;
        }

        $group_identifiers = array_keys($groups_from_config);

        PermissionGroup::syncGroups($group_identifiers);

        $groups = PermissionGroup::query()->get()->keyBy('slug');

        foreach($group_identifiers as $group_identifier) {

            $group_permissions = $groups_from_config[$group_identifier];
            $group = $groups->get($group_identifier);

            if (!$group) {
                continue;
            }

            $group_roles = config('permissions.group_roles.' . $group->slug, []);

            if(!empty($group_roles) && is_array($group_roles)) {
                $group->syncRoles($group_roles);
            }

            $group->syncPermissions($group_permissions);

        }

        PermissionGroup::syncRolePermissions();

    }

}

