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
        Context::env(function () {
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

    public function isMultidimensionalArray($array)
    {
        return count($array) !== count($array, COUNT_RECURSIVE);
    }


    /**
     * Flatten a multi-dimensional associative array with dots.
     *
     * @param  iterable  $array
     * @param  string  $prepend
     * @return array
     */
    public function dotArrayExceptLastArray($array, $prepend = '')
    {
        $results = [];
        foreach ($array as $key => $value) {
            if (is_array($value) && ! empty($value) && $this->isMultidimensionalArray($value)) {
                $results = array_merge($results, $this->dotArrayExceptLastArray($value, $prepend.$key.'.'));
            } else {
                $results[$prepend.$key] = $value;
            }
        }

        return $results;
    }

    private function dotAndWildcardPermissions($permissions)
    {
        $dotted_permissions = $this->dotArrayExceptLastArray($permissions);

        $permissions = array_keys($dotted_permissions);

        $wildcarded_permissions = array_map(function($permission) {
            return $permission . '*';
        }, $permissions);

        return $wildcarded_permissions;
    }

    public function getMappedPermissionsFromGroups()
    {
        $permissions = config('permissions.groups', []);
        $group_rules = $this->dotArrayExceptLastArray(config('permissions.group_roles', []));

        if(!is_array($group_rules) || empty($group_rules) || !is_array($permissions) || empty($permissions)) {
            return [];
        }

        $mapped_permissions = [];

        foreach($group_rules as $group_permission => $group_rule) {
            if(isset($permissions[$group_permission])) {
                foreach ($permissions[$group_permission] as $permission) {
                    $mapped_permissions[$permission] = $group_rule;
                }
                $mapped_permissions[$group_permission] = $group_rule;
            }
        }

        return $mapped_permissions;
    }

    public function getFilteredOperationIdentifiers(string $namespace = null, bool $restricted = true)
    {

        /// Here we will return a list of operation identifiers that are either restricted or not

        /// 1) Get permissions config -> It configures which operations are restricted
        $permissions_from_config = array_merge_recursive(
            config('permissions.permission_roles', []),
            config('permissions.custom_permission_roles', []),
            $this->getMappedPermissionsFromGroups(),
        );

        /// 2) Dot (collapse) permission config from a multidimensional array to dotted keys => values
        $dotted_permissions = $this->dotArrayExceptLastArray($permissions_from_config);
        $permissions = array_keys($dotted_permissions);

        /// 3) Get all available operation identifiers for namespace we are working on
        $operation_identifiers = ResourceRepository::getOperationIdentifiers($namespace);

        $restricted_operations = collect();
        $unrestricted_operations = collect();

        $partially_matching = [];

        if (!empty($permissions)) {
            foreach ($permissions as $permission) {

                $wildcarded_permission = $permission . '*';

                $rule = $dotted_permissions[$permission];

                foreach($operation_identifiers as $identifier) {

                    /// Is there an exact match?

                    if($permission === $identifier) {

                        /// Is the rule null - then the operation is considered to be unrestricted
                        /// If the rule is an array, it is restricted

                        if($rule === null) {
                            $unrestricted_operations->push($identifier);
                        } elseif(is_array($rule)) {
                            $restricted_operations->push($identifier);
                        } else {
                            throw new \Exception("Operation $identifier has invalid permission rule, expected array or null.");
                        }

                    } else {

                        /// It is not an exact match
                        /// Look for a partial match

                        $is_matching = fnmatch($wildcarded_permission, $identifier);

                        if($is_matching) {

                            if(!isset($partially_matching[$identifier])) {
                                $partially_matching[$identifier] = [];
                            }

                            $partially_matching[$identifier][] = $permission;

                        }

                    }

                }

            }
        }

        if(!empty($partially_matching)) {
            foreach($partially_matching as $identifier => $operation_matches) {

                /// We are assuming that the best match is the longest string
                $mapping = array_combine($operation_matches, array_map('strlen', $operation_matches));
                $best_matching_permission = array_keys($mapping, max($mapping))[0];

                $rule = $dotted_permissions[$best_matching_permission];

                /// Is the rule null - then the operation is considered to be unrestricted
                /// If the rule is an array, it is restricted

                if($rule === null) {
                    if(!$restricted_operations->contains($identifier)) {
                        $unrestricted_operations->push($identifier);
                    }
                } elseif(is_array($rule)) {
                    if(!$unrestricted_operations->contains($identifier)) {
                        $restricted_operations->push($identifier);
                    }
                } else {
                    throw new \Exception("Operation $identifier has invalid permission rule, expected array or null.");
                }

            }
        }

        foreach($operation_identifiers as $identifier) {
            if(!$unrestricted_operations->contains($identifier) && !$restricted_operations->contains($identifier)) {
                $unrestricted_operations->push($identifier);
            }
        }

        if($restricted) {
            return $restricted_operations->unique()->sort()->values()->all();
        }

        return $unrestricted_operations->unique()->sort()->values()->all();
    }

    public function findRolesForPermission($identifier)
    {

        $permissions_from_config = array_merge_recursive(
            config('permissions.permission_roles', []),
            config('permissions.custom_permission_roles', []),
            config('permissions.group_roles', []),
        );

        $dotted_permissions = $this->dotArrayExceptLastArray($permissions_from_config);
        $keys = array_keys($dotted_permissions);

        if(empty($keys)) {
            return [];
        }

        foreach($dotted_permissions as $key => $roles) {
            $pattern = $key.'*';

            if(fnmatch($pattern, $identifier)) {
                return $roles;
            }

        }

        return [];
    }

}
