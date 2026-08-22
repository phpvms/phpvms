<?php

use App\Enums\PirepState;
use App\Filament\Resources\Pireps\PirepResource;
use App\Models\Pirep;
use App\Services\PirepService;
use App\Support\PirepView\PirepViewTabRegistry;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Support\Facades\View;

/**
 * Registers the addon tab fixture views under the `pireptabs::` namespace and
 * hands back an EMPTY tab registry — whatever addons this checkout happens to
 * have enabled must not add tabs to the assertions below.
 */
function pirepTabRegistry(): PirepViewTabRegistry
{
    View::addNamespace('pireptabs', __DIR__.'/fixtures/pirep-tabs');

    $registry = new PirepViewTabRegistry();
    app()->instance(PirepViewTabRegistry::class, $registry);

    return $registry;
}

test('admin view-pirep page renders detail layout', function (): void {
    $this->seed(RolesPermissionsSeeder::class);

    $admin = createAdminUser();
    $pirep = Pirep::factory()->create(['state' => PirepState::PENDING]);

    $this->actingAs($admin)
        ->get(PirepResource::getUrl('view', ['record' => $pirep]))
        ->assertSuccessful()
        ->assertSee('subtabs', false)
        ->assertSee($pirep->dpt_airport_id)
        ->assertSee($pirep->arr_airport_id)
        ->assertSee($pirep->ident);
});

test('admin pirep list links each card to view-pirep page', function (): void {
    $this->seed(RolesPermissionsSeeder::class);

    $admin = createAdminUser();
    $pirep = Pirep::factory()->create(['state' => PirepState::PENDING]);

    $expectedUrl = PirepResource::getUrl('view', ['record' => $pirep]);

    $this->actingAs($admin)
        ->get(PirepResource::getUrl('index'))
        ->assertSuccessful()
        ->assertSee($expectedUrl, false);
});

test('view-pirep page renders the original flight card from the archive after flight and aircraft are hard-deleted', function (): void {
    $this->seed(RolesPermissionsSeeder::class);

    $admin = createAdminUser();
    $pirep = Pirep::factory()->create(['state' => PirepState::IN_PROGRESS]);
    $pirepSvc = app(PirepService::class);
    $pirepSvc->file($pirep);
    $pirepSvc->submit($pirep);

    $pirep->refresh();

    $flight = $pirep->flight;
    $aircraft = $pirep->aircraft;
    $flight->forceDelete();
    $aircraft->forceDelete();

    $archivedCallsign = $pirep->metadata->flight['callsign'];
    $archivedRegistration = $pirep->metadata->aircraft['registration'];

    $this->actingAs($admin)
        ->get(PirepResource::getUrl('view', ['record' => $pirep]))
        ->assertSuccessful()
        ->assertSee(__('filament.original_flight'))
        ->assertSee($archivedCallsign)
        ->assertSee($archivedRegistration);
});

test('view-pirep page renders without error for a pirep with no archive row', function (): void {
    $this->seed(RolesPermissionsSeeder::class);

    $admin = createAdminUser();
    $pirep = Pirep::factory()->create(['state' => PirepState::PENDING]);

    $this->actingAs($admin)
        ->get(PirepResource::getUrl('view', ['record' => $pirep]))
        ->assertSuccessful()
        ->assertSee(__('filament.original_flight_empty'));
});

test('view-pirep page renders an addon-registered tab with a record-derived label and badge', function (): void {
    $this->seed(RolesPermissionsSeeder::class);

    $admin = createAdminUser();
    $pirep = Pirep::factory()->create(['state' => PirepState::PENDING]);

    pirepTabRegistry()->register([
        'id'    => 'acme.debrief',
        'label' => fn (Pirep $p): string => 'ACME '.$p->ident,
        'badge' => fn (Pirep $p): string => 'BADGE-'.$p->ident,
        'view'  => 'pireptabs::ok',
    ]);

    $this->actingAs($admin)
        ->get(PirepResource::getUrl('view', ['record' => $pirep]))
        ->assertSuccessful()
        ->assertSee('ACME '.$pirep->ident)
        ->assertSee('BADGE-'.$pirep->ident)
        // Alpine wiring + the dot-free DOM id derived from the namespaced id.
        ->assertSee('id="tab-acme-debrief"', false)
        ->assertSee('TAB-PANEL-OK '.$pirep->ident)
        // The addon view sees only the record, not the page's variables.
        ->assertSee('SCOPE-CLEAN')
        ->assertDontSee('SCOPE-LEAKED');
});

test('view-pirep page hides an addon tab whose visible closure returns false', function (): void {
    $this->seed(RolesPermissionsSeeder::class);

    $admin = createAdminUser();
    $pirep = Pirep::factory()->create(['state' => PirepState::PENDING]);

    pirepTabRegistry()->register([
        'id'      => 'acme.hidden',
        'label'   => 'ZZ-HIDDEN-TAB',
        'visible' => fn (Pirep $p): bool => false,
        'view'    => 'pireptabs::ok',
    ]);

    $this->actingAs($admin)
        ->get(PirepResource::getUrl('view', ['record' => $pirep]))
        ->assertSuccessful()
        ->assertDontSee('ZZ-HIDDEN-TAB')
        ->assertDontSee('TAB-PANEL-OK')
        ->assertDontSee('tab-acme-hidden', false);
});

test('view-pirep page orders addon tabs by their order value, after the built-ins', function (): void {
    $this->seed(RolesPermissionsSeeder::class);

    $admin = createAdminUser();
    $pirep = Pirep::factory()->create(['state' => PirepState::PENDING]);

    // Registered high-order first, so passing requires the sort, not luck.
    pirepTabRegistry()
        ->register(['id' => 'acme.late', 'label' => 'ZZ-LATE-TAB', 'order' => 200, 'view' => 'pireptabs::ok'])
        ->register(['id' => 'acme.early', 'label' => 'ZZ-EARLY-TAB', 'order' => 50, 'view' => 'pireptabs::ok']);

    $this->actingAs($admin)
        ->get(PirepResource::getUrl('view', ['record' => $pirep]))
        ->assertSuccessful()
        ->assertSeeInOrder([__('filament.original_flight'), 'ZZ-EARLY-TAB', 'ZZ-LATE-TAB']);
});

test('view-pirep page lets a duplicate tab id replace the earlier registration', function (): void {
    $this->seed(RolesPermissionsSeeder::class);

    $admin = createAdminUser();
    $pirep = Pirep::factory()->create(['state' => PirepState::PENDING]);

    pirepTabRegistry()
        ->register(['id' => 'acme.debrief', 'label' => 'ZZ-ORIGINAL-TAB', 'view' => 'pireptabs::ok'])
        ->register(['id' => 'acme.debrief', 'label' => 'ZZ-REPLACEMENT-TAB', 'view' => 'pireptabs::ok']);

    $this->actingAs($admin)
        ->get(PirepResource::getUrl('view', ['record' => $pirep]))
        ->assertSuccessful()
        ->assertSee('ZZ-REPLACEMENT-TAB')
        ->assertDontSee('ZZ-ORIGINAL-TAB');
});

test('view-pirep page survives an addon tab view that throws', function (): void {
    $this->seed(RolesPermissionsSeeder::class);

    $admin = createAdminUser();
    $pirep = Pirep::factory()->create(['state' => PirepState::PENDING]);

    pirepTabRegistry()
        ->register(['id' => 'acme.boom', 'label' => 'ZZ-BROKEN-TAB', 'view' => 'pireptabs::boom'])
        ->register(['id' => 'acme.ok', 'label' => 'ZZ-HEALTHY-TAB', 'view' => 'pireptabs::ok']);

    $this->actingAs($admin)
        ->get(PirepResource::getUrl('view', ['record' => $pirep]))
        ->assertSuccessful()
        // Built-in tabs intact, the healthy sibling tab intact.
        ->assertSee(__('filament.original_flight'))
        ->assertSee('ZZ-HEALTHY-TAB')
        ->assertSee('TAB-PANEL-OK '.$pirep->ident)
        // Failing panel: fallback content, and no half-rendered output leaked.
        ->assertSee('ZZ-BROKEN-TAB')
        ->assertSee(__('filament.extension_tab_error'))
        ->assertDontSee('TAB-PANEL-PARTIAL-OUTPUT');
});

test('view-pirep page renders the built-in tabs untouched when no addon registered a tab', function (): void {
    $this->seed(RolesPermissionsSeeder::class);

    $admin = createAdminUser();
    $pirep = Pirep::factory()->create(['state' => PirepState::PENDING]);

    expect(pirepTabRegistry()->ordered())->toBe([]);

    $this->actingAs($admin)
        ->get(PirepResource::getUrl('view', ['record' => $pirep]))
        ->assertSuccessful()
        ->assertSee(__('pireps.flight_log'))
        ->assertSee(__('filament.original_flight'))
        ->assertDontSee(__('filament.extension_tab_error'));
});
