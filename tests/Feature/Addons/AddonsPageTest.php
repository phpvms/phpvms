<?php

declare(strict_types=1);

use App\Addons\Services\AddonDiscoveryService;
use App\Addons\Support\BootCache;
use App\Filament\Pages\Addons;
use Database\Seeders\ShieldSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(ShieldSeeder::class);
    $this->actingAs(createAdminUser());

    // Ensure the boot cache and DB are seeded with the bundled modules.
    app(BootCache::class)->delete();
    app(AddonDiscoveryService::class)->run();
});

afterEach(function (): void {
    app(BootCache::class)->delete();
});

it('lists addons from AddonRegistry on the page', function (): void {
    Livewire::test(Addons::class)
        ->assertSuccessful()
        ->assertSee('Sample');
});

it('shows enabled status for addons', function (): void {
    Livewire::test(Addons::class)
        ->assertSuccessful()
        ->assertSee('Sample');
});
