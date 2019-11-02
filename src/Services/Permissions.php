<?php

namespace MorningTrain\Laravel\Permissions\Services;


use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
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

    public function getUserPermissions($user = null)
    {
        // Check if $user HasPermissions
        if ($user === null || !is_object($user) || !method_exists($user, 'getAllPermissions')) {
            return $this->getUnrestrictedOperationIdentifiers();
        }

        $params = ResourceRepository::getOperationPolicyParameters();

        // Return all permissions if super-admin
        return $this->isSuperAdmin($user) ?
            ResourceRepository::getOperationIdentifiers()->all() :
            array_merge(
                $user->getAllPermissions()
                    ->pluck('name')
                    ->reject(function ($permission) use ($user, $params) {
                        $param = $params->get($permission);

                        return $param !== null ?
                            !$user->can($permission, $param) :
                            !$user->can($permission);
                    })
                    ->all(),
                $this->getUnrestrictedOperationIdentifiers()
            );
    }

    public function export()
    {
        Context::localization()->provide('env',
            function () {
                return [
                    'user_permissions' => $this->getUserPermissions(Auth::user()),
                ];
            });
    }

    public function registerPolicies($policies = [])
    {
        foreach ($policies as $model => $policy) {
            foreach (ResourceRepository::getModelPermissions($model) as $permission) {
                $parts  = explode('.', $permission);
                $method = array_pop($parts);

                if (method_exists($policy, $method)) {
                    Gate::define($permission, function (?Authorizable $user, ...$args) use ($method, $policy) {

                        $params = (new \ReflectionClass($policy))
                            ->getMethod($method)
                            ->getParameters();

                        $matches = collect($params)->every(function (\ReflectionParameter $param, $index) use ($args, $user) {
                            if ($index === 0) {
                                if ($user !== null) return true;

                                return ($param->getClass() && $param->allowsNull()) ||
                                    ($param->isDefaultValueAvailable() && is_null($param->getDefaultValue()));
                            }

                            if (!isset($args[$index - 1])) {
                                return false;
                            }

                            return true;
                        });

                        return $matches ?
                            call_user_func([app()->make($policy), $method], $user, ...$args)
                            : null;
                    });
                }
            }
        }
    }

    public function isRestricted($identifier)
    {
        return in_array($identifier, $this->getRestrictedOperationIdentifiers());
    }

    /**
     * Returns a list of all restricted operation identifiers for the provided namespace
     *
     * @param string $namespace
     * @return array
     * @throws Exception
     */
    public function getRestrictedOperationIdentifiers(string $namespace = null)
    {
        $key = $namespace === null ? '' : "_{$namespace}";

        return Cache::rememberForever('restricted_operations'.$key, function () use ($namespace) {
            return $this->getFilteredOperationIdentifiers($namespace, true);
        });
    }

    /**
     * Returns a list of all non-restricted operation identifiers for the provided namespace
     *
     * @param string $namespace
     * @return array
     * @throws \Exception
     */
    public function getUnrestrictedOperationIdentifiers(string $namespace = null)
    {
        $key = $namespace === null ? '' : "_{$namespace}";

        return Cache::rememberForever('unrestricted_operations'.$key, function () use ($namespace) {
            return $this->getFilteredOperationIdentifiers($namespace, false);
        });
    }

    private function getFilteredOperationIdentifiers(string $namespace = null, bool $restricted = true)
    {
        $permissions = config('permissions.permission_roles', []);

        return ResourceRepository::getOperationIdentifiers($namespace)
            ->filter(function ($identifier) use ($restricted, $permissions) {
                $isRestricted = Arr::get($permissions, $identifier, null) !== null;

                return $restricted ? $isRestricted : !$isRestricted;
            })
            ->values()->all();
    }

}
