<?php

declare(strict_types=1);

use App\Enums\BundleType;
use App\Features\Tour\Enums\TourStatus;
use App\Features\Tour\Models\UserTour;
use App\Filament\Resources\FlightBundles\Resources\Flight\FlightResource;
use App\Filament\Resources\Tours\Pages\CreateTour;
use App\Filament\Resources\Tours\Pages\EditTour;
use App\Filament\Resources\Tours\Pages\ListTours;
use App\Models\Flight;
use App\Models\FlightBundle;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Filament\Forms\Components\Hidden;
use Filament\Schemas\Components\Component;
use Livewire\Livewire;

/**
 * The tours resource: the same bundle pages, scoped to `type = tour`, with the
 * type itself no longer something the admin picks.
 */
beforeEach(function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->admin = createAdminUser();

    $this->tour = FlightBundle::factory()->create([
        'name' => 'Pacific Chain',
        'type' => BundleType::Tour,
    ]);
});

it('lists tours and leaves flights bundles out', function (): void {
    $regular = FlightBundle::factory()->create(['type' => BundleType::Flights]);

    Livewire::test(ListTours::class)
        ->assertCanSeeTableRecords([$this->tour])
        ->assertCanNotSeeTableRecords([$regular]);
});

it('creates a tour from the list drawer without asking for a type', function (): void {
    Livewire::test(ListTours::class)
        ->mountAction('create')
        ->setActionData(['name' => 'Silk Road'])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect(FlightBundle::query()->where('name', 'Silk Road')->sole()->type)
        ->toBe(BundleType::Tour);
});

it('creates a tour from the create page, with no type field on the form', function (): void {
    $page = Livewire::test(CreateTour::class);

    // The select is gone; a hidden field carries the type instead.
    $page->assertSuccessful()
        ->assertDontSee(__('filament.bundles.fields.type_helper'));

    $page->fillForm(['name' => 'Baltic Loop'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(FlightBundle::query()->where('name', 'Baltic Loop')->sole()->type)
        ->toBe(BundleType::Tour);
});

it('offers no type field in the tour edit drawer, so the record stays in scope', function (): void {
    Livewire::test(EditTour::class, ['record' => $this->tour->getRouteKey()])
        ->mountAction('edit')
        ->assertSchemaComponentExists(
            'type',
            checkComponentUsing: fn (Component $field): bool => $field instanceof Hidden,
        );
});

/**
 * The nested flight resource is parented to the bundles resource — Filament
 * allows one parent per resource — so leg editing from a tour lands on the
 * `/admin/flights/{bundle}/flight/{leg}/edit` page. That destination is what
 * this asserts; the link to it cannot be asserted at all, because Filament
 * refuses to guess a nested resource's parent parameter under a Livewire test
 * and the relation manager holding the link is lazy over HTTP.
 */
it('edits a tour leg on the bundles-parented flight page', function (): void {
    $leg = Flight::factory()->create(['bundle_id' => $this->tour->id, 'route_leg' => 1]);

    $this->actingAs($this->admin)
        ->get(FlightResource::getUrl('edit', ['record' => $leg, 'bundle' => $this->tour]))
        ->assertSuccessful();
});

it('carries the live-runs panel onto the tour edit page', function (): void {
    $leg = Flight::factory()->create(['bundle_id' => $this->tour->id, 'route_leg' => 1]);

    $flying = User::factory()->create(['name' => 'Amelia Reyes']);
    UserTour::factory()->for($flying)->create([
        'bundle_id'      => $this->tour->id,
        'flight_id'      => $leg->id,
        'status'         => TourStatus::InProgress,
        'legs_completed' => 0,
        'legs_total'     => 1,
    ]);

    Livewire::test(EditTour::class, ['record' => $this->tour->getRouteKey()])
        ->assertSuccessful()
        ->assertSee(__('filament.bundles.live_tours.heading'))
        ->assertSee('Amelia Reyes');
});
