<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Merge roles
    |--------------------------------------------------------------------------
    |
    | Should roles be merges when seeding (set this to true if DB is master)
    |
    */

    'merge_permissions' => false,
    'can_check_when_exporting_user_permissions' => true,
    'can_check_on_unrestricted_when_exporting_user_permissions' => false,

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
    | List all roles you want seeded for each restricted Resource permission here.
    |
    */

    'permission_roles' => [

        // Permission example
        'article_read'   => [
            'user',
        ],
        'article_update' => [
            'user',
        ],

        // Permission example
        'article'        => [
            'read'   => [
                'user',
            ],
            'update' => [
                'user',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom permission roles
    |--------------------------------------------------------------------------
    |
    | Here you can define some custom permissions,
    | Which don't need to be based on a Resource.
    | List all roles you want seeded for each custom permission here.
    |
    */

    'custom_permission_roles' => [

        // Permission example
        'article_read' => [
            'user',
        ],

    ],

];

