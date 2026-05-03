<?php

declare(strict_types=1);

use App\Filament\System\Updater;

arch('globals')
    ->expect(['dd', 'dump', 'ray', 'die', 'var_dump', 'sleep', 'dispatch', 'dispatch_sync'])
    ->not->toBeUsed()
    ->ignoring([
        Updater::class,
        'App\Console\Commands',
    ]);

arch('http helpers')
    ->expect(['session', 'auth', 'request'])
    ->toOnlyBeUsedIn([
        'App\Http',
        'App\Filament',
        'App\Livewire',
        'App\Providers\Filament',
    ]);
