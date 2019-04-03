<?php

namespace MorningTrain\Laravel\Permissions\Services;


use Illuminate\Support\Facades\Auth;
use MorningTrain\Laravel\Context\Context;
use MorningTrain\Laravel\Resources\ResourceRepository;

class Permissions
{
    /**
     * Checks if the provided object has a super_admin role
     *
     * @param $model
     * @return bool
     */
    public function isSuperAdmin($model): bool
    {
        if (!is_object($model)) {
            return false;
        }

        return method_exists($model, 'hasRole') &&
            $model->hasRole(config('permissions.super_admin'));
    }

    public function export()
    {
        Context::localization()->provide('env',
            function () {
                if (!Auth::check()) {
                    return;
                }

                $user = Auth::user();

                // Check if $user HasPermissions
                if (!method_exists($user, 'getAllPermissions')) {
                    return;
                }

                // Export all permissions if super-admin
                $permissions = $this->isSuperAdmin($user) ?
                    ResourceRepository::getAllPermissions() :
                    $user->getAllPermissions()->pluck('name')->all();

                return [
                    'user_permissions' => $permissions,
                ];
            });
    }
}
