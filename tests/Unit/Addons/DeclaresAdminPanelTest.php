<?php

declare(strict_types=1);

use App\Contracts\Addons\AddonServiceProvider;
use Filament\Panel;
use Illuminate\Support\ServiceProvider;

test('a provider that overrides registerAdminFilamentPanel declares a panel', function (): void {
    $provider = new class(app()) extends AddonServiceProvider
    {
        public function registerAdminFilamentPanel(Panel $panel): Panel
        {
            return $panel;
        }
    };

    expect(AddonServiceProvider::declaresAdminPanel($provider::class))->toBeTrue();
});

test('a provider that leaves registerAdminFilamentPanel alone declares no panel', function (): void {
    $provider = new class(app()) extends AddonServiceProvider {};

    expect(AddonServiceProvider::declaresAdminPanel($provider::class))->toBeFalse();
});

test('a class that is not an addon provider declares no panel', function (): void {
    $provider = new class(app()) extends ServiceProvider {};

    expect(AddonServiceProvider::declaresAdminPanel($provider::class))->toBeFalse()
        ->and(AddonServiceProvider::declaresAdminPanel('Missing\\Provider'))->toBeFalse();
});
