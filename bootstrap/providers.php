<?php

declare(strict_types=1);

use App\Features\Tour\TourServiceProvider;
use App\Providers\AddonServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\SystemPanelProvider;
use App\Providers\PassportServiceProvider;
use App\Providers\SkylightServiceProvider;
use App\Providers\TypeScriptTransformerServiceProvider;
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
    PassportServiceProvider::class,

    // Hooks the tour slice to the PIREP lifecycle; event discovery does not
    // reach app/Features, and the deletion hook is a model event.
    TourServiceProvider::class,

    AdminPanelProvider::class,
    SystemPanelProvider::class,

    // Generates TypeScript types from PHP DTOs for the skylight SPA.
    TypeScriptTransformerServiceProvider::class,

    /**
     * Package Service Providers
     */
    ServiceProvider::class,
];
