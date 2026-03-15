<?php

/**
 * DO NOT EDIT THIS OR ANY OF THE CONFIG FILES DIRECTLY
 * IF YOU DO, YOU NEED TO RESTORE THOSE CHANGES AFTER AN UPDATE
 */

use App\Models\Enums\ActiveState;
use App\Models\Enums\PirepSource;
use App\Models\Enums\PirepState;
use App\Models\Enums\PirepStatus;
use App\Models\Enums\UserState;
use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\BindServiceProviders;
use App\Providers\BroadcastServiceProvider;
use App\Providers\CronServiceProvider;
use App\Providers\DirectiveServiceProvider;
use App\Providers\EventServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\SystemPanelProvider;
use App\Providers\MeasurementsProvider;
use App\Providers\ModulesServiceProvider;
use App\Providers\ObserverServiceProviders;
use App\Providers\RouteServiceProvider;
use App\Providers\ViewComposerServiceProvider;
use Carbon\Carbon;
use Igaster\LaravelTheme\Facades\Theme;
use Igaster\LaravelTheme\themeServiceProvider;
use Illuminate\Support\Facades\Facade;
use Laracasts\Flash\Flash;
use Laracasts\Flash\FlashServiceProvider;
use Prettus\Repository\Providers\RepositoryServiceProvider;
use SocialiteProviders\Manager\ServiceProvider;
use Symfony\Component\Yaml\Yaml;

return [
    'name'          => env('APP_NAME', 'phpvms'),
    'env'           => env('APP_ENV', 'dev'),
    'debug'         => env('APP_DEBUG', true),
    'url'           => env('APP_URL', ''),
    'version'       => '8.0.0',
    'debug_toolbar' => env('DEBUG_TOOLBAR', false),

    'locale'          => env('APP_LOCALE', 'en'),
    'fallback_locale' => 'en',

    //
    // Anything below here won't need changing and could break things
    //

    // DON'T CHANGE THIS OR ELSE YOUR TIMES WILL BE MESSED UP!
    'timezone' => 'UTC',

    // Is the default key cipher. Needs to be changed, otherwise phpVMS will think
    // that it isn't installed. Doubles as a security feature, so keys are scrambled
    'key'    => env('APP_KEY', 'base64:zdgcDqu9PM8uGWCtMxd74ZqdGJIrnw812oRMmwDF6KY='),
    'cipher' => 'AES-256-CBC',

    'providers' => Illuminate\Support\ServiceProvider::defaultProviders()->merge([
        /*
         * Package Service Providers...
         */
        FlashServiceProvider::class,
        RepositoryServiceProvider::class,
        themeServiceProvider::class,
        // Nwidart\Modules\LaravelModulesServiceProvider::class,
        ServiceProvider::class,

        /*
         * Application Service Providers...
         */
        AppServiceProvider::class,
        AuthServiceProvider::class,
        BindServiceProviders::class,
        BroadcastServiceProvider::class,
        ViewComposerServiceProvider::class,
        CronServiceProvider::class,
        DirectiveServiceProvider::class,
        EventServiceProvider::class,
        MeasurementsProvider::class,
        ObserverServiceProviders::class,
        ModulesServiceProvider::class,
        AdminPanelProvider::class,
        SystemPanelProvider::class,
        RouteServiceProvider::class,
    ])->toArray(),

    'aliases' => Facade::defaultAliases()->merge([
        'Carbon' => Carbon::class,
        'Flash'  => Flash::class,
        'Theme'  => Theme::class,
        'Yaml'   => Yaml::class,

        // ENUMS
        'ActiveState' => ActiveState::class,
        'UserState'   => UserState::class,
        'PirepSource' => PirepSource::class,
        'PirepState'  => PirepState::class,
        'PirepStatus' => PirepStatus::class,
    ])->toArray(),
];
