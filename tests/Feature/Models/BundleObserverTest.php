<?php

declare(strict_types=1);

use App\Jobs\RecomputeBundleVisibility;
use App\Models\Flight;
use App\Models\FlightBundle;
use Illuminate\Support\Facades\Queue;

it('dispatches a recompute job when a new bundle is created', function (): void {
    Queue::fake();

    $bundle = FlightBundle::factory()->create(['enabled' => true]);

    Queue::assertPushed(RecomputeBundleVisibility::class, fn (RecomputeBundleVisibility $job): bool => $job->bundleId === $bundle->id);
});

it('dispatches a recompute job when enabled is toggled on an existing bundle', function (): void {
    $bundle = FlightBundle::factory()->create(['enabled' => true]);

    Queue::fake();

    $bundle->enabled = false;
    $bundle->save();

    Queue::assertPushed(RecomputeBundleVisibility::class, fn (RecomputeBundleVisibility $job): bool => $job->bundleId === $bundle->id);
});

it('does not dispatch a recompute when only the bundle name is changed', function (): void {
    $bundle = FlightBundle::factory()->create(['enabled' => true]);

    Queue::fake();

    $bundle->name = 'Renamed';
    $bundle->save();

    Queue::assertNotPushed(RecomputeBundleVisibility::class);
});

it('dispatches a recompute job on bundle delete', function (): void {
    $bundle = FlightBundle::factory()->create(['enabled' => true]);

    Queue::fake();

    $bundle->delete();

    Queue::assertPushed(RecomputeBundleVisibility::class);
});

it('dispatches a recompute job on bundle restore', function (): void {
    $bundle = FlightBundle::factory()->create(['enabled' => true]);
    $bundle->delete();

    Queue::fake();

    $bundle->restore();

    Queue::assertPushed(RecomputeBundleVisibility::class);
});

it('does not cascade soft-delete to child flights', function (): void {
    $bundle = FlightBundle::factory()->create();

    $flights = Flight::factory()->count(3)->create([
        'bundle_id' => $bundle->id,
    ]);

    $bundle->delete();

    foreach ($flights as $flight) {
        $flight->refresh();
        expect($flight->deleted_at)->toBeNull();
    }
});
