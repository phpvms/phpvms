<?php

declare(strict_types=1);

use App\Providers\AddonServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\SystemPanelProvider;
use App\Providers\SkylightServiceProvider;
use SocialiteProviders\Manager\ServiceProvider;

return [
    /*
     * Application Service Providers...
     */
    AppServiceProvider::class,

    // Register the skylight extension hub BEFORE the addon engine so the
    // `skylight` binding exists when addon providers boot and register widgets.
    SkylightServiceProvider::class,

    AddonServiceProvider::class,
    AdminPanelProvider::class,
    SystemPanelProvider::class,

    /**
     * Package Service Providers
     */
    ServiceProvider::class,
];
