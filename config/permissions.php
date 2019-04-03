<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Roles
    |--------------------------------------------------------------------------
    |
    | Register your roles here
    |
    */

    'roles' => [
        'admin',
        'user',
    ],


    /*
    |--------------------------------------------------------------------------
    | Super Admin
    |--------------------------------------------------------------------------
    |
    | Super admins automatically get complete access.
    | If you don't want any roles to have this kind of power,
    | Just leave the array empty.
    |
    | Keep in mind a super_admin doesn't need any permissions,
    | So they don't need to be included in permission_roles.
    |
    */

    'super_admin' => [
        'admin',
    ],


    /*
    |--------------------------------------------------------------------------
    | Permission roles
    |--------------------------------------------------------------------------
    |
    | List all roles you want seeded for each permission here.
    |
    */

    'permission_roles' => [

        // Permission example
        'article_read' => [
            'user',
        ],
        'article_update' => [
            'user',
        ],

        // Permission example
        'article' => [
            'read' => [
                'user'
            ],
            'update' => [
                'user'
            ],
        ],
    ],
];
