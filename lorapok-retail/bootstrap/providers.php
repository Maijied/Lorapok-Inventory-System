<?php

use App\Providers\AppServiceProvider;
use App\Providers\ParallelTestingServiceProvider;
use App\Providers\TenancyServiceProvider;

return [
    AppServiceProvider::class,
    ParallelTestingServiceProvider::class,
    TenancyServiceProvider::class,
];
