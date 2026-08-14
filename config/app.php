<?php

use App\Providers\AppServiceProvider;

return [
    'name' => 'Deps',
    'version' => app('git.version'),
    'env' => 'development',
    'providers' => [
        AppServiceProvider::class,
    ],
];
