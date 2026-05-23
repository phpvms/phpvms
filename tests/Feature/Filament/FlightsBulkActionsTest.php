<?php

declare(strict_types=1);

use App\Filament\Resources\FlightBundles\Pages\EditFlightBundle;
use App\Filament\Resources\FlightBundles\RelationManagers\FlightsRelationManager;
use App\Jobs\RecomputeBundleVisibility;
use App\Models\Flight;
use App\Models\FlightBundle;
use App\Models\Subfleet;
use Database\Seeders\ShieldSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(ShieldSeeder::class);

    $admin = createAdminUser();

    $this->actingAs($admin);
});

it('bulk-enables selected flights and dispatches visibility recompute', function (): void {
    $bundle = FlightBundle::factory()->create(['enabled' => true]);
    $flights = Flight::factory()->count(3)->create([
        'bundle_id' => $bundle->id,
        'enabled'   => false,
    ]);

    // Fake the queue AFTER factories so observer-dispatched jobs don't count.
    Queue::fake();

    Livewire::test(FlightsRelationManager::class, [
        'ownerRecord' => $bundle,
        'pageClass'   => EditFlightBundle::class,
    ])
        ->selectTableRecords($flights->pluck('id')->all())
        ->callAction(TestAction::make('enable')->table()->bulk())
        ->assertHasNoErrors()
        ->assertNotified();

    $flights->each(function (Flight $flight): void {
        expect($flight->fresh()->enabled)->toBeTrue();
    });

    Queue::assertPushed(
        RecomputeBundleVisibility::class,
        fn (RecomputeBundleVisibility $job): bool => $job->bundleId === $bundle->id,
    );
    Queue::assertPushedTimes(RecomputeBundleVisibility::class, 1);
});

it('bulk-disables selected flights and dispatches visibility recompute', function (): void {
    $bundle = FlightBundle::factory()->create(['enabled' => true]);
    $flights = Flight::factory()->count(2)->create([
        'bundle_id' => $bundle->id,
        'enabled'   => true,
    ]);

    Queue::fake();

    Livewire::test(FlightsRelationManager::class, [
        'ownerRecord' => $bundle,
        'pageClass'   => EditFlightBundle::class,
    ])
        ->selectTableRecords($flights->pluck('id')->all())
        ->callAction(TestAction::make('disable')->table()->bulk())
        ->assertHasNoErrors()
        ->assertNotified();

    $flights->each(function (Flight $flight): void {
        expect($flight->fresh()->enabled)->toBeFalse();
    });

    Queue::assertPushed(
        RecomputeBundleVisibility::class,
        fn (RecomputeBundleVisibility $job): bool => $job->bundleId === $bundle->id,
    );
    Queue::assertPushedTimes(RecomputeBundleVisibility::class, 1);
});

it('bulk-moves selected flights to another bundle and recomputes both bundles', function (): void {
    $source = FlightBundle::factory()->create(['name' => 'Source Bundle']);
    $destination = FlightBundle::factory()->create(['name' => 'Destination Bundle']);

    $flights = Flight::factory()->count(2)->create(['bundle_id' => $source->id]);

    Queue::fake();

    Livewire::test(FlightsRelationManager::class, [
        'ownerRecord' => $source,
        'pageClass'   => EditFlightBundle::class,
    ])
        ->selectTableRecords($flights->pluck('id')->all())
        ->callAction(TestAction::make('move_to_bundle')->table()->bulk(), [
            'bundle_id' => $destination->id,
        ])
        ->assertHasNoErrors()
        ->assertNotified();

    $flights->each(function (Flight $flight) use ($destination): void {
        expect($flight->fresh()->bundle_id)->toBe($destination->id);
    });

    Queue::assertPushed(
        RecomputeBundleVisibility::class,
        fn (RecomputeBundleVisibility $job): bool => $job->bundleId === $source->id,
    );
    Queue::assertPushed(
        RecomputeBundleVisibility::class,
        fn (RecomputeBundleVisibility $job): bool => $job->bundleId === $destination->id,
    );
    Queue::assertPushedTimes(RecomputeBundleVisibility::class, 2);
});

it('bulk-attaches subfleets to selected flights idempotently', function (): void {
    $bundle = FlightBundle::factory()->create();
    $flights = Flight::factory()->count(2)->create(['bundle_id' => $bundle->id]);
    $subfleets = Subfleet::factory()->count(2)->create();

    // Pre-attach one subfleet to one flight to verify idempotency.
    $flights->first()->subfleets()->attach($subfleets->first()->id);

    Livewire::test(FlightsRelationManager::class, [
        'ownerRecord' => $bundle,
        'pageClass'   => EditFlightBundle::class,
    ])
        ->selectTableRecords($flights->pluck('id')->all())
        ->callAction(TestAction::make('attach_subfleets')->table()->bulk(), [
            'subfleet_ids' => $subfleets->pluck('id')->all(),
        ])
        ->assertHasNoErrors()
        ->assertNotified();

    $flights->each(function (Flight $flight) use ($subfleets): void {
        $attached = $flight->fresh()->subfleets->pluck('id')->all();
        expect($attached)->toContain(...$subfleets->pluck('id')->all());
    });
});

it('bulk-detaches subfleets from selected flights', function (): void {
    $bundle = FlightBundle::factory()->create();
    $flights = Flight::factory()->count(2)->create(['bundle_id' => $bundle->id]);
    $subfleets = Subfleet::factory()->count(2)->create();

    $flights->each(function (Flight $flight) use ($subfleets): void {
        $flight->subfleets()->attach($subfleets->pluck('id')->all());
    });

    Livewire::test(FlightsRelationManager::class, [
        'ownerRecord' => $bundle,
        'pageClass'   => EditFlightBundle::class,
    ])
        ->selectTableRecords($flights->pluck('id')->all())
        ->callAction(TestAction::make('detach_subfleets')->table()->bulk(), [
            'subfleet_ids' => [$subfleets->first()->id],
        ])
        ->assertHasNoErrors()
        ->assertNotified();

    $flights->each(function (Flight $flight) use ($subfleets): void {
        $attached = $flight->fresh()->subfleets->pluck('id')->all();
        expect($attached)->not->toContain($subfleets->first()->id);
        expect($attached)->toContain($subfleets->last()->id);
    });
});
