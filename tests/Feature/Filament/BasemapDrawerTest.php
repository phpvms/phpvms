<?php

declare(strict_types=1);

use App\Filament\Resources\MapLayers\Pages\ListMapLayers;
use App\Filament\Resources\MapLayers\Schemas\BasemapForm;
use App\Models\MapLayer;
use App\Models\Permission;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Notifications\Notification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/**
 * The basemap pair moved out of the Settings page's map tab and into a
 * `map_layers` row of type `basemap`, edited from the "Base Maps" drawer on
 * the Map Layers list page. These are the same behaviours the old
 * `SettingsMapTabTest` covered, retargeted at the new location — this file
 * IS that file, renamed.
 */

/**
 * TestMapStyleAction is attached via ->hintAction() on the custom-URL fields,
 * not as a page-level action, so it must be addressed through its schema
 * component (Filament's "Testing actions in a schema" docs) rather than a
 * bare callAction('testMapStyle'). It is nested inside the basemaps action's
 * own modal schema.
 */
function testMapStyleAction(string $field = 'custom_url_light'): TestAction
{
    return TestAction::make('testMapStyle')->schemaComponent($field);
}

function basemapsAction(): TestAction
{
    return TestAction::make('basemaps');
}

beforeEach(function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->actingAs(createAdminUser());
});

it('defaults a fresh install to the vacentral basemap pair', function (): void {
    // No basemap row yet — the drawer opens on the defaults rather than blank.
    Livewire::test(ListMapLayers::class)
        ->mountAction('basemaps')
        ->assertSchemaStateSet([
            'url_template'      => BasemapForm::VACENTRAL_LIGHT,
            'url_template_dark' => BasemapForm::VACENTRAL_DARK,
        ]);
});

it('creates the basemap row on first save when none exists', function (): void {
    Livewire::test(ListMapLayers::class)
        ->callAction(basemapsAction(), [
            'url_template'      => BasemapForm::VACENTRAL_LIGHT,
            'url_template_dark' => BasemapForm::VACENTRAL_DARK,
        ])
        ->assertHasNoActionErrors();

    $basemap = MapLayer::query()->where('type', MapLayer::TYPE_BASEMAP)->sole();

    expect($basemap->url_template)->toBe(BasemapForm::VACENTRAL_LIGHT)
        ->and($basemap->url_template_dark)->toBe(BasemapForm::VACENTRAL_DARK)
        ->and($basemap->enabled)->toBeTrue();
});

it('saves a curated basemap pair verbatim onto the existing row', function (): void {
    $basemap = MapLayer::factory()->basemap()->create();

    Livewire::test(ListMapLayers::class)
        ->callAction(basemapsAction(), [
            'url_template'      => 'https://basemaps.cartocdn.com/gl/voyager-nolabels-gl-style/style.json',
            'url_template_dark' => 'https://basemaps.cartocdn.com/gl/dark-matter-gl-style/style.json',
        ])
        ->assertHasNoActionErrors();

    expect(MapLayer::query()->where('type', MapLayer::TYPE_BASEMAP)->count())->toBe(1)
        ->and($basemap->fresh()->url_template)->toBe('https://basemaps.cartocdn.com/gl/voyager-nolabels-gl-style/style.json')
        ->and($basemap->fresh()->url_template_dark)->toBe('https://basemaps.cartocdn.com/gl/dark-matter-gl-style/style.json');
});

it('substitutes the custom style URL when a side is set to Custom', function (): void {
    MapLayer::factory()->basemap()->create();

    Livewire::test(ListMapLayers::class)
        ->callAction(basemapsAction(), [
            'url_template'      => BasemapForm::CUSTOM,
            'custom_url_light'  => 'https://example.com/my-style.json',
            'url_template_dark' => BasemapForm::VACENTRAL_DARK,
        ])
        ->assertHasNoActionErrors();

    // The __custom__ sentinel must never reach the column — MapConfigService
    // reads it back verbatim and maplibre would reject it as a style URL.
    expect(MapLayer::query()->where('type', MapLayer::TYPE_BASEMAP)->sole()->url_template)
        ->toBe('https://example.com/my-style.json');
});

it('requires the custom style URL when a side is set to Custom', function (): void {
    Livewire::test(ListMapLayers::class)
        ->callAction(basemapsAction(), [
            'url_template'      => BasemapForm::CUSTOM,
            'custom_url_light'  => '',
            'url_template_dark' => BasemapForm::VACENTRAL_DARK,
        ])
        ->assertHasActionErrors(['custom_url_light']);
});

it('reloads a custom basemap as the Custom option rather than a raw URL', function (): void {
    MapLayer::factory()->basemap()->create(['url_template' => 'https://example.com/already-custom.json']);

    Livewire::test(ListMapLayers::class)
        ->mountAction('basemaps')
        ->assertSchemaStateSet([
            'url_template'     => BasemapForm::CUSTOM,
            'custom_url_light' => 'https://example.com/already-custom.json',
        ]);
});

/**
 * vacentral is a real URL per side (unlike ESRI's sentinel or Custom's
 * swapped-in value), so — unlike Voyager/Dark Matter, offered on both sides —
 * the light field offers the light style and the dark field the dark one.
 */
it("reloads a basemap holding the wrong side's vacentral URL as Custom", function (): void {
    // Not reachable through the select itself (each side only lists its own
    // vacentral URL) — this simulates a raw DB edit, proving the per-side
    // check is real rather than "any vacentral URL passes on either side".
    MapLayer::factory()->basemap()->create(['url_template_dark' => BasemapForm::VACENTRAL_LIGHT]);

    Livewire::test(ListMapLayers::class)
        ->mountAction('basemaps')
        ->assertSchemaStateSet(['url_template_dark' => BasemapForm::CUSTOM]);
});

it('reports a successful style test for a reachable, valid style document', function (): void {
    Http::fake([
        'https://example.com/style.json' => Http::response([
            'version' => 8,
            'sources' => [],
            'layers'  => [],
        ]),
    ]);

    Livewire::test(ListMapLayers::class)
        ->mountAction('basemaps')
        // The URL field only renders once its select is on "Custom style…".
        ->set('mountedActions.0.data.url_template', BasemapForm::CUSTOM)
        ->set('mountedActions.0.data.custom_url_light', 'https://example.com/style.json')
        ->callAction(testMapStyleAction())
        ->assertNotified(__('map.test_style_success'));
});

it('reports a failed style test for a response missing required style keys', function (): void {
    Http::fake([
        'https://example.com/not-a-style.json' => Http::response(['hello' => 'world']),
    ]);

    Livewire::test(ListMapLayers::class)
        ->mountAction('basemaps')
        ->set('mountedActions.0.data.url_template', BasemapForm::CUSTOM)
        ->set('mountedActions.0.data.custom_url_light', 'https://example.com/not-a-style.json')
        ->callAction(testMapStyleAction())
        ->assertNotified(
            Notification::make()
                ->danger()
                ->title(__('map.test_style_failed'))
                ->body(__('map.test_style_invalid')),
        );
});

it('reports a failed style test for an unreachable URL', function (): void {
    Http::fake([
        'https://example.com/gone.json' => Http::response(null, 404),
    ]);

    Livewire::test(ListMapLayers::class)
        ->mountAction('basemaps')
        ->set('mountedActions.0.data.url_template', BasemapForm::CUSTOM)
        ->set('mountedActions.0.data.custom_url_light', 'https://example.com/gone.json')
        ->callAction(testMapStyleAction())
        ->assertNotified(
            Notification::make()
                ->danger()
                ->title(__('map.test_style_failed'))
                ->body(__('map.test_style_bad_status', ['status' => '404'])),
        );
});

it('refuses the basemaps drawer to a user who can view map layers but not edit them', function (): void {
    // The drawer CREATES or UPDATES a map_layers row, so viewing the page is not
    // sufficient authority. Filament auto-authorizes its built-in CreateAction
    // via the policy; a hand-rolled Action gets nothing unless it asks.
    $viewer = User::factory()->create();
    Permission::firstOrCreate(['name' => 'view:map-layer', 'guard_name' => 'web']);
    $viewer->givePermissionTo('view:map-layer');
    $this->actingAs($viewer->fresh());

    MapLayer::factory()->basemap()->create(['url_template' => 'https://untouched.example.com/style.json']);

    Livewire::test(ListMapLayers::class)
        ->assertActionHidden(basemapsAction());

    // Hidden is not blocked. Driven on the component INSTANCE rather than through
    // the Livewire test helpers: `callAction()`/`mountAction()` are overridden by
    // Filament's testing macros, which assert the action is visible and fail the
    // test before any server-side refusal is exercised. The instance reaches the
    // same entrypoint a crafted request would, with the button never rendered.
    try {
        /** @var ListMapLayers $page */
        $page = Livewire::test(ListMapLayers::class)->instance();
        $page->mountAction('basemaps');
        $page->callMountedAction();
    } catch (AuthorizationException|HttpException) {
        // Narrow on purpose: catching every exception type would also swallow an
        // unrelated error thrown before the save, leaving the row intact and the
        // test passing for the wrong reason.
    }

    expect(MapLayer::query()->where('type', MapLayer::TYPE_BASEMAP)->sole()->url_template)
        ->toBe('https://untouched.example.com/style.json');
});

it('edits a disabled basemap row rather than creating a second one', function (): void {
    // `activeBasemap` excludes disabled rows — correct for rendering, wrong for
    // the drawer's "create if none exists" branch, which then made a duplicate.
    // One click on the table's enabled toggle is enough to reach this.
    $basemap = MapLayer::factory()->basemap()->create(['enabled' => false]);

    Livewire::test(ListMapLayers::class)
        ->callAction(basemapsAction(), [
            'url_template'      => BasemapForm::VACENTRAL_LIGHT,
            'url_template_dark' => BasemapForm::VACENTRAL_DARK,
        ])
        ->assertHasNoActionErrors();

    expect(MapLayer::query()->where('type', MapLayer::TYPE_BASEMAP)->count())->toBe(1)
        ->and($basemap->fresh()->url_template)->toBe(BasemapForm::VACENTRAL_LIGHT);
});
