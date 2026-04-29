<?php

use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use Spatie\Permission\PermissionServiceProvider;

return [
    AppServiceProvider::class,
    AuthServiceProvider::class,
    PermissionServiceProvider::class,
];
