<?php

declare(strict_types=1);

use App\Addons\Filament\FilamentPanelExtender;
use Filament\Facades\Filament;

it('apply() does not throw when no addon has Filament dirs and admin panel resolves', function (): void {
    $extender = app(FilamentPanelExtender::class);

    // Must not throw even when no addon provides Filament classes.
    expect(fn () => $extender->apply())->not->toThrow(Throwable::class);

    // The core admin panel must still be accessible after apply().
    expect(Filament::getPanel('admin'))->not->toBeNull();
});

it('apply() is idempotent — calling twice does not throw and admin panel remains accessible', function (): void {
    $extender = app(FilamentPanelExtender::class);

    // First call.
    $extender->apply();

    // Second call — must not throw and must not corrupt panel state.
    expect(fn () => $extender->apply())->not->toThrow(Throwable::class);

    expect(Filament::getPanel('admin'))->not->toBeNull();
});
