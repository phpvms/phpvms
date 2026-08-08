<?php

declare(strict_types=1);

use App\Filament\Pages\Dashboard;
use App\Http\Middleware\UpdatePending;
use Database\Seeders\RolesPermissionsSeeder;

/**
 * The sidebar carries x-cloak and theme.css hides cloaked elements, so without
 * this script the whole rail is display:none until Alpine boots and then pops
 * in. It only beats that window by running inside the <aside> while the page is
 * still parsing, which is a property of *where* it is registered rather than of
 * the markup itself — nothing else would fail if the hook moved.
 */
beforeEach(function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->withoutMiddleware(UpdatePending::class);
    $this->actingAs(createAdminUser());
});

test('the sidebar state script renders inside the sidebar, ahead of the rail clock', function (): void {
    $html = $this->get(Dashboard::getUrl())->assertSuccessful()->getContent();

    $sidebar = strpos($html, 'class="fi-sidebar fi-main-sidebar"');
    $script = strpos($html, 'isOpenDesktop');
    $clock = strpos($html, 'fi-rail-clock');

    expect($sidebar)->not->toBeFalse()
        ->and($script)->not->toBeFalse()
        ->and($clock)->not->toBeFalse();

    // Inside the aside, so currentScript.closest() can reach it, and before the
    // clock so the collapse state is settled as early in the rail as possible.
    expect($script)->toBeGreaterThan($sidebar)
        ->and($script)->toBeLessThan($clock);
});

test('the sidebar state script reads the key the Filament store persists', function (): void {
    $html = $this->get(Dashboard::getUrl())->assertSuccessful()->getContent();

    // stores/sidebar.js settles the desktop sidebar from isOpenDesktop, and
    // drives the layout through .fi-sidebar-open. Reading a different key, or
    // setting a different class, restores the flash without breaking anything
    // else.
    expect($html)->toContain("localStorage.getItem('isOpenDesktop')")
        ->and($html)->toContain("classList.toggle('fi-sidebar-open'");
});
