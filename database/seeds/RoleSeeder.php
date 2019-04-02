<?php

namespace MorningTrain\Laravel\Permissions\Database\Seeds;

use Illuminate\Database\Seeder;
use MorningTrain\Laravel\Resources\ResourceRepository;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run()
    {
        foreach (config('permissions.roles', []) as $role) {
            Role::create(['name' => $role]);
        }
    }
}
