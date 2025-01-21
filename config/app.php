<?php

/**
 * DO NOT EDIT THIS OR ANY OF THE CONFIG FILES DIRECTLY
 * IF YOU DO, YOU NEED TO RESTORE THOSE CHANGES AFTER AN UPDATE.
 */

use Carbon\Carbon;

return [
    'name'          => env('APP_NAME', 'phpvms'),
    'env'           => env('APP_ENV', 'dev'),
    'debug'         => env('APP_DEBUG', true),
    'url'           => env('APP_URL', ''),
    'version'       => '7.0.0',
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

    'providers' => \Illuminate\Support\ServiceProvider::defaultProviders()->merge([
        /*
         * Package Service Providers...
         */
        Laracasts\Flash\FlashServiceProvider::class,
        Prettus\Repository\Providers\RepositoryServiceProvider::class,
        Igaster\LaravelTheme\themeServiceProvider::class,
        Nwidart\Modules\LaravelModulesServiceProvider::class,
        SocialiteProviders\Manager\ServiceProvider::class,

        /*
         * Application Service Providers...
         */
        App\Providers\AppServiceProvider::class,
        App\Providers\AuthServiceProvider::class,
        App\Providers\BindServiceProviders::class,
        App\Providers\BroadcastServiceProvider::class,
        App\Providers\ViewComposerServiceProvider::class,
        App\Providers\CronServiceProvider::class,
        App\Providers\DirectiveServiceProvider::class,
        App\Providers\EventServiceProvider::class,
        App\Providers\MeasurementsProvider::class,
        App\Providers\ObserverServiceProviders::class,
        App\Providers\Filament\AdminPanelProvider::class,
        App\Providers\Filament\SystemPanelProvider::class,
        App\Providers\RouteServiceProvider::class,
    ])->toArray(),

    'aliases' => \Illuminate\Support\Facades\Facade::defaultAliases()->merge([
        'Carbon' => Carbon::class,
        'Flash'  => Laracasts\Flash\Flash::class,
        'Form'   => Collective\Html\FormFacade::class,
        'Html'   => Collective\Html\HtmlFacade::class,
        'Theme'  => Igaster\LaravelTheme\Facades\Theme::class,
        'Yaml'   => Symfony\Component\Yaml\Yaml::class,

        // ENUMS
        'ActiveState' => App\Models\Enums\ActiveState::class,
        'UserState'   => App\Models\Enums\UserState::class,
        'PirepSource' => App\Models\Enums\PirepSource::class,
        'PirepState'  => App\Models\Enums\PirepState::class,
        'PirepStatus' => App\Models\Enums\PirepStatus::class,
    ])->toArray(),
];
