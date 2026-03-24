<?php

declare(strict_types=1);

use App\Services\VersionService;

arch()->preset()->php();

// arch()->preset()->strict();

arch()->preset()->security()->ignoring(['assert', 'md5', 'sha1', VersionService::class]);

/*
 Those settings are quite strict our codebase is just not ready for them yet
arch()->preset()->laravel()
    ->ignoring([
        App\Providers\Filament\AdminPanelProvider::class,
        App\Filament\Plugins\LanguageSwitcherPlugin::class,
    ]);

arch('strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch('avoid open for extension')
    ->expect('App')
    ->classes()
    ->toBeFinal();

arch('ensure no extends')
    ->expect('App')
    ->classes()
    ->not->toBeAbstract();

arch('avoid mutation')
    ->expect('App')
    ->classes()
    ->toBeReadonly()
    ->ignoring([
        'App\Data',
        'App\Jobs',
        'App\Filament',
        'App\Livewire',
        'App\Models',
        'App\Providers',
    ]);

arch('avoid inheritance')
    ->expect('App')
    ->classes()
    ->toExtendNothing()
    ->ignoring([
        'App\Data',
        'App\Filament',
        'App\Livewire',
        'App\Models',
        'App\Providers',
    ]);

arch('annotations')
    ->expect('App')
    ->toHavePropertiesDocumented()
    ->toHaveMethodsDocumented();
*/
