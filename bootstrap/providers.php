<?php

declare(strict_types=1);

use App\Features\Tour\TourServiceProvider;
use App\Providers\AddonServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\SystemPanelProvider;
use App\Providers\FirstPartyMetricsServiceProvider;
use App\Providers\MetricsServiceProvider;
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

    // Same reasoning as SkylightServiceProvider: the `metrics` binding must
    // exist before any addon ServiceProvider::boot() calls the Metrics
    // facade. FirstPartyMetricsServiceProvider registers core's own metrics
    // from boot() the same way an addon would, so it is listed after the hub
    // and (like the addon engine) after the binding is registered.
    MetricsServiceProvider::class,
    FirstPartyMetricsServiceProvider::class,

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
