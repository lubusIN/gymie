<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\FilamentDefaultsServiceProvider;

return [
    AppServiceProvider::class,
    FilamentDefaultsServiceProvider::class,
    AdminPanelProvider::class,
];
