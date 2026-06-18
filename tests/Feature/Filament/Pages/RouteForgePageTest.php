<?php

declare(strict_types=1);

use App\Filament\Pages\RouteForge;
use App\Models\Airline;
use App\Models\FlightBundle;
use App\Models\Subfleet;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolesPermissionsSeeder::class);
});

it('runs zero data-loading Eloquent queries at page mount', function (): void {
    $this->actingAs(createAdminUser());

    // Seed enough rows that any accidental "fetch all" call would surface.
    Airline::factory()->count(3)->create();
    FlightBundle::factory()->count(5)->create();
    Subfleet::factory()->count(4)->create();

    DB::enableQueryLog();
    DB::flushQueryLog();

    Livewire::test(RouteForge::class)
        ->assertSuccessful();

    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    $dataLoadingTables = ['airlines', 'flight_bundles', 'subfleets', 'flights'];
    foreach ($queries as $query) {
        $sql = strtolower((string) ($query['query'] ?? ''));
        foreach ($dataLoadingTables as $table) {
            // The auth + Spatie permission lookups touch `users`, `roles`, and
            // `model_has_*` tables — those are expected. We assert ONLY that
            // the RouteForge data-loading tables are absent.
            expect(str_contains($sql, sprintf(' %s ', $table)))
                ->toBeFalse(sprintf('Page mount should not query %s; saw: %s', $table, $sql));
            expect(str_contains($sql, sprintf('`%s`', $table)))
                ->toBeFalse(sprintf('Page mount should not query %s; saw: %s', $table, $sql));
            expect(str_contains($sql, sprintf('"%s"', $table)))
                ->toBeFalse(sprintf('Page mount should not query %s; saw: %s', $table, $sql));
        }
    }
});

it('renders the boot URL on the mount element as data-boot-url', function (): void {
    $this->actingAs(createAdminUser());

    Livewire::test(RouteForge::class)
        ->assertSuccessful()
        ->assertSeeHtml('data-boot-url="'.route('admin.routeforge.api.boot').'"')
        ->assertSeeHtml('id="routeforge-root"');
});

it('does NOT render the legacy window.routeforgeConfig envelope', function (): void {
    $this->actingAs(createAdminUser());

    Livewire::test(RouteForge::class)
        ->assertSuccessful()
        ->assertDontSeeHtml('window.routeforgeConfig');
});

it('blocks users without the edit:flight permission', function (): void {
    $this->actingAs(User::factory()->create());

    expect(RouteForge::canAccess())->toBeFalse();
});
