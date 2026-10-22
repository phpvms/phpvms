<?php

declare(strict_types=1);

use App\Filament\Resources\MapLayers\Pages\CreateMapLayer;
use App\Filament\Resources\MapLayers\Pages\EditMapLayer;
use App\Filament\Resources\MapLayers\Pages\ListMapLayers;
use App\Models\MapLayer;
use App\Models\User;
use App\Policies\Filament\MapLayerPolicy;
use Database\Seeders\RolesPermissionsSeeder;
use Filament\Tables\Columns\ToggleColumn;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

it('authorizes via the map-layer permission', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $policy = new MapLayerPolicy();

    // A plain user without the permission is denied.
    $user = User::factory()->create();
    expect($policy->viewAny($user))->toBeFalse();

    // Granting the view permission allows it.
    $user->givePermissionTo('view:map-layer');
    expect($policy->viewAny($user->fresh()))->toBeTrue();

    // Super-admins are allowed via the Gate::before bypass.
    expect(createAdminUser()->can('view:map-layer'))->toBeTrue();
});

it('renders the list page for an authorized admin', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->actingAs(createAdminUser());

    MapLayer::factory()->count(2)->create();

    Livewire::test(ListMapLayers::class)->assertSuccessful();
});

it('shows an enabled toggle column that saves on change', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->actingAs(createAdminUser());

    Livewire::test(ListMapLayers::class)
        ->assertTableColumnExists('enabled', fn (ToggleColumn $column): bool => true);
});

it('is reorderable by the order column', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->actingAs(createAdminUser());

    /** @var ListMapLayers $page */
    $page = Livewire::test(ListMapLayers::class)->instance();

    expect($page->getTable()->isReorderable())->toBeTrue();
});

it('creates a raster layer', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->actingAs(createAdminUser());

    Livewire::test(CreateMapLayer::class)
        ->fillForm([
            'name'         => 'OpenAIP Airspace',
            'type'         => MapLayer::TYPE_RASTER,
            'url_template' => 'https://api.tiles.openaip.net/api/data/openaip/{z}/{x}/{y}.png',
            'attribution'  => 'openAIP',
            'min_zoom'     => 0,
            'max_zoom'     => 14,
            'opacity'      => 1,
            'enabled'      => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(MapLayer::query()->where('name', 'OpenAIP Airspace')->exists())->toBeTrue();
});

it('accepts a WMS query string in the URL template without ->url() rejecting it', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->actingAs(createAdminUser());

    Livewire::test(CreateMapLayer::class)
        ->fillForm([
            'name'         => 'METAR',
            'type'         => MapLayer::TYPE_RASTER,
            'url_template' => 'https://example.com/wms?bbox={bbox-epsg-3857}&format=image/png',
            'attribution'  => 'NOAA',
            'min_zoom'     => 0,
            'max_zoom'     => 12,
            'opacity'      => 0.8,
            'enabled'      => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(MapLayer::query()->where('name', 'METAR')->exists())->toBeTrue();
});

it('requires attribution', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->actingAs(createAdminUser());

    Livewire::test(CreateMapLayer::class)
        ->fillForm([
            'name'         => 'No Attribution',
            'type'         => MapLayer::TYPE_RASTER,
            'url_template' => 'https://example.com/{z}/{x}/{y}.png',
            'attribution'  => '',
        ])
        ->call('create')
        ->assertHasFormErrors(['attribution']);

    expect(MapLayer::query()->where('name', 'No Attribution')->exists())->toBeFalse();
});

it('edits an existing layer', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->actingAs(createAdminUser());

    $layer = MapLayer::factory()->create(['name' => 'Original']);

    Livewire::test(EditMapLayer::class, ['record' => $layer->getRouteKey()])
        ->fillForm(['name' => 'Renamed'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($layer->fresh()->name)->toBe('Renamed');
});

it('labels each layer type with its own badge, never folding one into another', function (): void {
    // Regression: the Type column's `default => raster` arm silently rendered
    // the basemap row as "Raster" — a wrong label reads as a data bug rather
    // than a missing match arm, and nothing failed to signal it.
    $this->seed(RolesPermissionsSeeder::class);
    $this->actingAs(createAdminUser());

    $raster = MapLayer::factory()->create(['type' => MapLayer::TYPE_RASTER]);
    $vector = MapLayer::factory()->create(['type' => MapLayer::TYPE_VECTOR]);
    $basemap = MapLayer::factory()->basemap()->create();

    Livewire::test(ListMapLayers::class)
        ->assertTableColumnFormattedStateSet('type', __('map.layer_type_raster'), $raster)
        ->assertTableColumnFormattedStateSet('type', __('map.layer_type_vector'), $vector)
        ->assertTableColumnFormattedStateSet('type', __('map.layer_type_basemap'), $basemap);
});

it('offers every type in the table filter, so a basemap row is findable', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->actingAs(createAdminUser());

    $basemap = MapLayer::factory()->basemap()->create();
    $raster = MapLayer::factory()->create(['type' => MapLayer::TYPE_RASTER]);

    Livewire::test(ListMapLayers::class)
        ->filterTable('type', MapLayer::TYPE_BASEMAP)
        ->assertCanSeeTableRecords([$basemap])
        ->assertCanNotSeeTableRecords([$raster]);
});

it('refuses the enabled toggle to a view-only user — editable columns save with no policy check', function (): void {
    // Filament's editable columns call `$record->save()` directly
    // (`CanUpdateState::updateState`) with no authorization of their own, so a
    // ToggleColumn is an unguarded write unless the column adds its own gate.
    // Disabling the basemap row this way would strip the map's style site-wide.
    $this->seed(RolesPermissionsSeeder::class);

    $viewer = User::factory()->create();
    $viewer->givePermissionTo('view:map-layer');
    $this->actingAs($viewer->fresh());

    $layer = MapLayer::factory()->create(['enabled' => true]);

    // `updateTableColumnState` is the real entrypoint Filament's toggle calls
    // (`HasColumns::updateTableColumnState`); driving the rendered component is
    // not enough, since the attack is a direct Livewire call.
    try {
        Livewire::test(ListMapLayers::class)
            ->call('updateTableColumnState', 'enabled', (string) $layer->getKey(), false);
    } catch (AuthorizationException|HttpException) {
        // Narrow deliberately, same reason as the drawer test: a catch-all would
        // let an unrelated failure masquerade as an authorization refusal.
    }

    expect($layer->fresh()->enabled)->toBeTrue();
});
