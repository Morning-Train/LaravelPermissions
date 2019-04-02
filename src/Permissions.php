<?php

namespace MorningTrain\Laravel\Permissions;


use Illuminate\Support\Facades\Facade;
use MorningTrain\Laravel\Permissions\Services\Permissions as PermissionService;

class Permissions extends Facade
{
    protected static function getFacadeAccessor()
    {
        return PermissionService::class;
    }
}
