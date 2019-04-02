<?php

namespace MorningTrain\Laravel\Permissions\Services;


use Illuminate\Support\Facades\Auth;
use MorningTrain\Laravel\Context\Context;
use MorningTrain\Laravel\Resources\ResourceRepository;

class Permissions
{
    public function export()
    {
        Context::localization()->provide('env',
            function () {
                if (!Auth::check()) {
                    return;
                }

                $user = Auth::user();

                // TODO - check if permission methods exist on user
                $permissions = $user->hasRole(config('permissions.super_admin')) ?
                    ResourceRepository::getAllPermissions() :
                    $user->getAllPermissions()->pluck('name')->all();

                return [
                    'user_permissions' => $permissions,
                ];
            });
    }
}
