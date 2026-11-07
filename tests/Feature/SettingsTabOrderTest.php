<?php

declare(strict_types=1);

use App\Filament\Pages\Settings;
use Database\Seeders\RolesPermissionsSeeder;
use Livewire\Livewire;

/*
 * The categories used to follow the order the database returned settings tied
 * on `order`, so they changed places between loads. General leads now and the
 * rest are alphabetical.
 */
it('lists the settings categories with General first, then alphabetically', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->actingAs(createAdminUser());

    $html = Livewire::test(Settings::class)->html();
    preg_match_all('/fi-section-header-heading"\s*>\s*([^<]*?)\s*</', $html, $matches);
    $categories = $matches[1];

    $rest = array_slice($categories, 1);
    $sorted = $rest;
    natcasesort($sorted);

    expect($categories)->toContain('Airports', 'General', 'Pilots')
        ->and($categories[0])->toBe('General')
        ->and($rest)->toBe(array_values($sorted));
});
