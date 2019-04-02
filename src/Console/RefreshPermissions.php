<?php

namespace MorningTrain\Laravel\Permissions\Console;


use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use MorningTrain\Laravel\Resources\ResourceRepository;
use Spatie\Permission\Models\Permission;

class RefreshPermissions extends Command
{
    protected $name        = 'mt:refresh-permissions';
    protected $description = 'Refreshes permissions';
    protected $target      = [];

    public function __construct()
    {
        parent::__construct();

        $this->target = ResourceRepository::getAllPermissions();
    }

    public function handle()
    {
        $this->call('mt:refresh-roles');

        $this->info('Refreshing application permissions.');

        $this->deleteDeprecated();

        $existing = Permission::get();

        $this->refreshPermissions($existing);
        $this->syncRoles($existing);

        $this->info('Done refreshing permissions.');
    }

    protected function deleteDeprecated()
    {
        // Delete all deprecated
        $deleted = Permission::whereNotIn('name', $this->target)->delete();
        $this->info("Deleted $deleted deprecated " . Str::plural('permission', $deleted));
    }

    protected function refreshPermissions(Collection $existing)
    {
        // Create all new
        $existing = $existing->pluck('name')->all();
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

    protected function syncRoles(Collection $existing)
    {
        $this->info('Syncing permission roles.');

        $existing->each(function ($permission) {
            $roles = config('permissions.permission_roles', [])[$permission->name] ?? [];
            $permission->syncRoles($roles);
        });
    }
}
