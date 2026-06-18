<?php

use App\Enums\PirepState;
use App\Models\Pirep;
use Database\Seeders\RolesPermissionsSeeder;

/**
 * Smoke test: feed-style PIREP admin list renders.
 *
 * Verifies the Filament Layout\View + custom row partial wiring works
 * end-to-end against the real list page route.
 */
test('admin pirep list renders feed-style rows', function (): void {
    $this->seed(RolesPermissionsSeeder::class);

    $admin = createAdminUser();
    $pirep = Pirep::factory()->create(['state' => PirepState::PENDING]);

    $this->actingAs($admin)
        ->get('/admin/pireps')
        ->assertSuccessful()
        ->assertSee($pirep->dpt_airport_id)
        ->assertSee($pirep->arr_airport_id)
        ->assertSee('fi-pirep-row', false);
});
