<?php

namespace MorningTrain\Laravel\Permissions\Policies;

abstract class PermissionPolicy
{
    public function __call($name, $args)
    {
        $user     = $args[0];
        $resource = null;

        if (isset($args[1])) {
            $resource = $args[1];
        }

        $parts     = explode('.', $name); // TODO - This is kind of an assumption into how permission names are structured.
        $operation = array_pop($parts);

        if (method_exists($this, $operation)) {
            return call_user_func([$this, $operation], $user, $resource);
        }
    }
}
