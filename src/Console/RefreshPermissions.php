<?php

namespace MorningTrain\Laravel\Permissions\Console;


use Illuminate\Console\Command;
use Illuminate\Support\Str;
use MorningTrain\Laravel\Resources\ResourceRepository;
use Spatie\Permission\Models\Permission;

class RefreshPermissions extends Command
{
    protected $name        = 'mt:refresh-permissions';
    protected $description = 'Refreshes permissions';

    public function handle()
    {
        $this->info('Refreshing application permissions.');

        $target = ResourceRepository::getAllPermissions();

        // Delete all deprecated
        $deleted = Permission::whereNotIn('name', $target)->delete();
        $this->info("Deleted $deleted deprecated " . Str::plural('permission', $deleted));


        // Create all new
        $existing = Permission::get(['name'])->pluck('name')->all();
        $new      = array_diff($target, $existing);
        $count = count($new);

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

        $this->info('Done refreshing permissions.');
    }
}
