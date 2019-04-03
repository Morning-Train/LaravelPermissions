<?php

namespace MorningTrain\Laravel\Permissions\Services;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Spatie\Permission\PermissionRegistrar as SpatiePermissionRegistrar;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

class PermissionRegistrar extends SpatiePermissionRegistrar
{
    public function registerPermissions(): bool
    {
        $this->gate->before(function (Authorizable $user, string $ability) {
            try {
                if (method_exists($user, 'hasPermissionTo')) {
                    return $user->hasPermissionTo($ability) ? null : false;
                }
            } catch (PermissionDoesNotExist $e) {
            }
        });

        $this->gate->after(function (Authorizable $user, string $ability, $result, $args) {
            if ($result !== false) {
                try {
                    if (method_exists($user, 'hasPermissionTo')) {
                        $result = $user->hasPermissionTo($ability);
                    }
                } catch (PermissionDoesNotExist $e) {
                }
            }

            return $result;
        });

        return true;
    }

}
