<?php

namespace MorningTrain\Laravel\Permissions\Console;


use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class RefreshRoles extends Command
{
    protected $name        = 'mt:refresh-roles';
    protected $description = 'Refreshes roles';
    protected $target;

    public function handle()
    {
        $this->info('Refreshing application roles.');

        $this->target = config('permissions.roles', []);

        $this->deleteDeprecated();

        $existing = Role::get();

        $this->refreshPermissions($existing);

        $this->info('Done refreshing roles.');
    }

    protected function deleteDeprecated()
    {
        // Delete all deprecated
        $deleted = Role::whereNotIn('name', $this->target)->delete();
        $this->info("Deleted $deleted deprecated " . Str::plural('role', $deleted));
    }

    protected function refreshPermissions(Collection $existing)
    {
        // Create all new
        $existing = $existing->pluck('name')->all();
        $new      = array_diff($this->target, $existing);
        $count    = count($new);

        if ($count > 0) {
            $this->info("Creating " . $count . " " . Str::plural('role', $count));
            $bar = $this->output->createProgressBar($count);

            foreach ($new as $permission) {
                Role::create(['name' => $permission]);
                $bar->advance();
            }

            $bar->finish();
            $this->info('');
        }
    }
}
