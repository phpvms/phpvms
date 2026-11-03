<?php

declare(strict_types=1);

use App\Filament\Pages\Settings;
use Database\Seeders\RolesPermissionsSeeder;
use Livewire\Livewire;

/*
 * The tabs used to follow the order the database returned settings tied on
 * `order`, so they changed places between loads. They are alphabetical now.
 */
it('lists the settings tabs in alphabetical order', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->actingAs(createAdminUser());

    $html = Livewire::test(Settings::class)->html();
    preg_match_all('/fi-tabs-item-label">\s*([^<]*?)\s*</', $html, $matches);
    $tabs = $matches[1];

    $sorted = $tabs;
    natcasesort($sorted);

    expect($tabs)->toContain('Airports', 'General', 'Pilots')
        ->and($tabs)->toBe(array_values($sorted));
});
