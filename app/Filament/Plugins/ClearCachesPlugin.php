<?php

declare(strict_types=1);

namespace App\Filament\Plugins;

use App\Livewire\Filament\ClearCaches;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;

final class ClearCachesPlugin implements Plugin
{
    public static function make(): self
    {
        return new self();
    }

    public function getId(): string
    {
        return 'clear-caches';
    }

    public function register(Panel $panel): void
    {
        $panel->renderHook(
            PanelsRenderHook::USER_MENU_BEFORE,
            fn (): string => Blade::render(
                '@livewire($component)',
                ['component' => ClearCaches::class],
            ),
        );
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
