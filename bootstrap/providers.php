<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\SystemPanelProvider;
use App\Providers\ModulesServiceProvider;
use SocialiteProviders\Manager\ServiceProvider;

return [
    /*
     * Application Service Providers...
     */
    AppServiceProvider::class,
    ModulesServiceProvider::class,
    AdminPanelProvider::class,
    SystemPanelProvider::class,

    /**
     * Package Service Providers
     */
    ServiceProvider::class,
];
