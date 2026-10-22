<?php

declare(strict_types=1);

use App\Enums\BundleType;
use App\Features\Tour\Models\UserTour;
use App\Filament\Pages\RouteForge;
use App\Filament\Resources\FlightBundles\Pages\EditFlightBundle;
use App\Filament\Resources\FlightBundles\Pages\ListFlightBundles;
use App\Filament\Resources\FlightBundles\Resources\Flight\Pages\EditFlight;
use App\Models\Flight;
use App\Models\FlightBundle;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Livewire\Livewire;

/**
 * The admin's view of a tour: which bundles are tours, who is running one right
 * now, and what they are told before they disturb a live run.
 */
beforeEach(function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    createAdminUser();

    $this->tour = FlightBundle::factory()->create([
        'name'       => 'Pacific Chain',
        'type'       => BundleType::Tour,
        'start_date' => null,
        'end_date'   => null,
    ]);
});

it('narrows the bundle list to tours, and shows every type by default', function (): void {
    $regular = FlightBundle::factory()->create(['type' => BundleType::Flights]);

    Livewire::test(ListFlightBundles::class)
        ->assertCanSeeTableRecords([$this->tour, $regular])
        ->filterTable('type', BundleType::Tour->value)
        ->assertCanSeeTableRecords([$this->tour])
        ->assertCanNotSeeTableRecords([$regular]);
});

it('shows the bundle type on the list', function (): void {
    Livewire::test(ListFlightBundles::class)
        ->assertSuccessful()
        ->assertSee(BundleType::Tour->getLabel());
});

it('lists only in-progress runs, with progress and the active leg', function (): void {
    $leg = Flight::factory()->create(['bundle_id' => $this->tour->id, 'route_leg' => 3]);

    $flying = User::factory()->create(['name' => 'Amelia Reyes']);
    UserTour::factory()->for($flying)->create([
        'bundle_id'      => $this->tour->id,
        'flight_id'      => $leg->id,
        'legs_completed' => 2,
        'legs_total'     => 5,
    ]);

    $finished = User::factory()->create(['name' => 'Gus Grissom']);
    UserTour::factory()->for($finished)->completed()->create(['bundle_id' => $this->tour->id]);

    $quit = User::factory()->create(['name' => 'Nora Vance']);
    UserTour::factory()->for($quit)->cancelled()->create(['bundle_id' => $this->tour->id]);

    Livewire::test(EditFlightBundle::class, ['record' => $this->tour->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('Amelia Reyes')
        ->assertSee('2 / 5')
        ->assertSee($leg->ident)
        ->assertDontSee('Gus Grissom')
        ->assertDontSee('Nora Vance');
});

it('shows an empty state rather than hiding the panel when nobody is running the tour', function (): void {
    Livewire::test(EditFlightBundle::class, ['record' => $this->tour->getRouteKey()])
        ->assertSuccessful()
        ->assertSee(__('filament.bundles.live_tours.heading'))
        ->assertSee(__('filament.bundles.live_tours.empty'));
});

it('leaves the panel off a flights bundle', function (): void {
    $regular = FlightBundle::factory()->create(['type' => BundleType::Flights]);

    UserTour::factory()->create(['bundle_id' => $regular->id]);

    Livewire::test(EditFlightBundle::class, ['record' => $regular->getRouteKey()])
        ->assertSuccessful()
        ->assertDontSee(__('filament.bundles.live_tours.heading'))
        ->assertDontSee(__('filament.bundles.live_tours.empty'));
});

/**
 * The warning rides on the leg field itself, so it is in front of the admin at
 * the moment they retype the number — and it never stops the save.
 *
 * Asserted against the schema rather than the rendered HTML: the leg lives in
 * the flight's settings drawer, whose body Livewire's test HTML does not carry.
 */
it('warns on the leg field when pilots are mid-tour, and still saves', function (): void {
    $flight = Flight::factory()->create([
        'bundle_id'     => $this->tour->id,
        'flight_number' => 421,
        'route_leg'     => 2,
    ]);

    UserTour::factory()->count(2)->create(['bundle_id' => $this->tour->id]);

    Livewire::test(EditFlight::class, [
        'record'       => $flight->getRouteKey(),
        'parentRecord' => $this->tour,
    ])
        ->mountAction('edit', ['recordKey' => $flight->getRouteKey()])
        ->assertSchemaComponentExists(
            'route_leg',
            checkComponentUsing: fn (TextInput $field): bool => (string) $field->getHint()
                === trans_choice('filament.flights.tour_live_runs_warning', 2, ['count' => 2]),
        )
        ->setActionData(['route_leg' => 4])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect($flight->fresh()->route_leg)->toBe(4);
});

it('says nothing on the leg field when no run is live', function (): void {
    $flight = Flight::factory()->create([
        'bundle_id'     => $this->tour->id,
        'flight_number' => 422,
        'route_leg'     => 1,
    ]);

    UserTour::factory()->cancelled()->create(['bundle_id' => $this->tour->id]);

    Livewire::test(EditFlight::class, [
        'record'       => $flight->getRouteKey(),
        'parentRecord' => $this->tour,
    ])
        ->mountAction('edit', ['recordKey' => $flight->getRouteKey()])
        ->assertSchemaComponentExists(
            'route_leg',
            checkComponentUsing: fn (TextInput $field): bool => $field->getHint() === null,
        );
});

it('links into RouteForge with the tour prefilled and the draft bypassed', function (): void {
    Livewire::test(EditFlightBundle::class, ['record' => $this->tour->getRouteKey()])
        ->assertActionExists('forgeFlights', function (Action $action): bool {
            $url = $action->getUrl();

            expect($url)->toStartWith(RouteForge::getUrl());

            parse_str((string) parse_url((string) $url, PHP_URL_QUERY), $query);

            return $query === [
                'topology'    => 'tour',
                'bundle'      => (string) $this->tour->id,
                'bundle_name' => 'Pacific Chain',
                'fresh'       => '1',
            ];
        });
});

it('offers no forge action on a flights bundle', function (): void {
    $regular = FlightBundle::factory()->create(['type' => BundleType::Flights]);

    Livewire::test(EditFlightBundle::class, ['record' => $regular->getRouteKey()])
        ->assertActionHidden('forgeFlights');
});
