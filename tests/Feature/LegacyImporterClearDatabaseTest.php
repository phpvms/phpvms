<?php

declare(strict_types=1);

use App\Models\Fare;
use App\Models\FlightBundle;
use App\Models\Rank;
use App\Models\Subfleet;
use App\Services\LegacyImporter\ClearDatabase;
use App\Services\LegacyImporterService;
use Illuminate\Support\Facades\DB;

/**
 * The v5 legacy importer empties the target database before it writes anything
 * into it. Anything it leaves behind is not merely stale: every id it clears is
 * a reused auto-increment, so a surviving child row rebinds itself onto
 * whichever freshly imported parent lands on the old id.
 */
beforeEach(function (): void {
    // BaseImporter builds an ImporterDB from the stored credentials in its
    // constructor. It does not connect -- ClearDatabase never reads the legacy
    // database -- but the array has to be there to be read.
    app(LegacyImporterService::class)->saveCredentials([
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'legacy_v5',
        'user' => 'phpvms',
        'pass' => '',
    ]);
});

test('the cleanup takes every subfleet pivot down with the subfleets', function (): void {
    $subfleet = Subfleet::factory()->create();
    $bundle = FlightBundle::factory()->create();

    $bundle->subfleets()->attach($subfleet->id);
    $subfleet->fares()->attach(Fare::factory()->create()->id);
    $subfleet->ranks()->attach(Rank::factory()->create()->id);

    // No Typerating factory, and the pivot has no foreign keys -- the row is
    // what matters here, not what it points at.
    DB::table('typerating_subfleet')->insert([
        'typerating_id' => 1,
        'subfleet_id'   => $subfleet->id,
    ]);

    $pivots = ['bundle_subfleet', 'subfleet_fare', 'subfleet_rank', 'typerating_subfleet'];
    $count = fn (): array => collect($pivots)
        ->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()])
        ->all();

    expect($count())->toBe(array_fill_keys($pivots, 1));

    new ClearDatabase()->run();

    // A bundle's subfleet defaults cannot outlive the subfleets they name, and
    // neither can a fare override, a rank grant or a type rating.
    expect($count())->toBe(array_fill_keys($pivots, 0))
        ->and(Subfleet::count())->toBe(0);

    // The failure mode is silent mis-binding rather than a dangling row: the
    // truncate above resets the subfleet auto-increment, so a default left
    // behind would name whichever subfleet the importer writes into that id
    // next, and rung 2 would hand that one to every flight on the bundle.
    $imported = Subfleet::factory()->create();

    expect($bundle->subfleets()->count())->toBe(0)
        ->and($imported->bundles()->count())->toBe(0);
});
