<?php

namespace MorningTrain\Laravel\Permissions\Console;


use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
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

        // All permissions which need to be reacted
        $this->target = array_unique(array_merge(
            array_keys(static::dot(config('permissions.custom_permission_roles', []))),
            ResourceRepository::getRestrictedOperationIdentifiers()
        ));

        $this->deleteDeprecated();

        $this->refreshPermissions();
        $this->syncRoles();

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

        Permission::query()->get()->each(function ($permission) use ($permissions) {
            $roles = Arr::get($permissions, $permission->name, []);
            $permission->syncRoles($roles);
        });
    }

    /**
     * Overwritten version of Arr::dot()
     * It wont go deeper, if none of the values are an array.
     * Returns all keys accessible by Arr:get()
     *
     * @param $array
     * @param string $prepend
     * @return array
     */
    public static function dot($array, $prepend = '')
    {
        $results = [];

        foreach ($array as $key => $value) {
            if (is_array($value)
                && !empty($value)
                && collect($value)->contains(function ($values) {
                    return is_array($values);
                })
            ) {
                $results = array_merge($results, static::dot($value, $prepend . $key . '.'));
            } else {
                $results[$prepend . $key] = $value;
            }
        }

        return $results;
    }
}

