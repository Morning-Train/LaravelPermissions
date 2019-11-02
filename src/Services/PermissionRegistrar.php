<?php

namespace MorningTrain\Laravel\Permissions\Services;

use Illuminate\Contracts\Auth\Access\Authorizable;
use MorningTrain\Laravel\Permissions\Permissions;
use Spatie\Permission\PermissionRegistrar as SpatiePermissionRegistrar;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

class PermissionRegistrar extends SpatiePermissionRegistrar
{
    public function registerPermissions(): bool
    {
        /// The behaviour in Spatie/PermissionRegistrar
        /// Immediately allows access (returns true) if the user has the permission
        /// And only continues running if they dont, or if the permission doesn't exist.
        ///
        /// Here we flip the default behavior,
        /// In order to run further checks in model Policies.
        /// This means if the user doesn't have the general permission, we immediately reject.
        /// But if they do have it, we return null, and allow the defined policies to run.
        $this->gate->before(function (?Authorizable $user, string $ability) {
            try {
                if ($user === null && Permissions::isRestricted($ability)) {
                    return false;
                }
                else if (method_exists($user, 'hasPermissionTo')) {
                    return $user->hasPermissionTo($ability) ? null : false;
                }
            } catch (PermissionDoesNotExist $e) {
            }
        });

        /// The above however triggers an unwanted behavior:
        /// If the user has the general permission,
        /// But there is no policy/policy method to return a positive result
        /// We end up with a false negative.
        ///
        /// To fix that, we check if the result isn't specifically negative
        /// (if it came from a policy, or lack of general permission)
        /// And do the initial check again, this time returning any positive or negative result.
        ///
        /// Additionally if a permission doesn't exist, we just return true.
        /// This allows us to omit registering non-restricted operations.
        $this->gate->after(function (?Authorizable $user, string $ability, $result, $args) {
            if ($result !== false) {
                try {
                    if ($user === null && $result === null) {
                        return true;
                    }
                    else if (method_exists($user, 'hasPermissionTo')) {
                        $result = $user->hasPermissionTo($ability);
                    }
                } catch (PermissionDoesNotExist $e) {
                    $result = true;
                }
            }

            return $result;
        });

        return true;
    }

}
