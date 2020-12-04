<?php

namespace MorningTrain\Laravel\Permissions\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use MorningTrain\Laravel\Permissions\Models\PermissionGroup;

class SyncGroupedPermissions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->queue = 'default';
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        PermissionGroup::syncRolePermissions();
        PermissionGroup::syncUserPermissions();
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array
     */
    public function tags()
    {
        return ['permissions', 'sync'];
    }


}
