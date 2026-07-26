<?php

declare(strict_types=1);

use App\Models\Aircraft;
use App\Models\Airline;
use App\Models\Bid;
use App\Models\Fare;
use App\Models\Flight;
use App\Models\FlightBundle;
use App\Models\Rank;
use App\Models\Subfleet;
use App\Models\User;
use App\Services\FareService;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Bundle-level subfleet defaults as seen through the LIST path — the
 * `Flight::withAccessibleSubfleets()` scope, not the single-flight
 * `accessibleSubfleetsFor()` method that BundleSubfleetInheritanceTest covers.
 *
 * Every test here drives the real scope on a real query builder. Nothing may
 * reimplement the cascade locally, or the test stops testing the code.
 *
 * The scope implements rungs 1 and 2 only:
 *   1. the flight's own live `flight_subfleet` pins
 *   2. else its bundle's live `bundle_subfleet` defaults
 * Rung 3 (the fallback to the airline's or the entire fleet) is deliberately
 * absent — it is unbounded and a list page would materialise it per flight.
 *
 * Note the Flight factory shares one "Default" bundle across a test run, so
 * every test below creates its own bundle to keep inheritance isolated.
 */
beforeEach(function (): void {
    updateSetting('pireps.restrict_aircraft_to_rank', true);
    updateSetting('pireps.restrict_aircraft_to_typerating', false);
    updateSetting('pireps.only_aircraft_at_dpt_airport', false);
    updateSetting('bids.block_aircraft', false);
    updateSetting('flights.only_company_aircraft', false);
});

/**
 * A subfleet with an active aircraft, flyable by the given ranks.
 *
 * Deliberately not shared with BundleSubfleetInheritanceTest's `flyableSubfleet`
 * — Pest only loads the files it is asked to run, so a helper borrowed across
 * files breaks the moment either file is run alone.
 */
function listableSubfleet(Airline $airline, string $name, array $ranks): Subfleet
{
    $subfleet = Subfleet::factory()->create(['airline_id' => $airline->id, 'name' => $name]);
    Aircraft::factory()->create(['subfleet_id' => $subfleet->id]);

    foreach ($ranks as $rank) {
        $rank->subfleets()->attach($subfleet->id);
    }

    return $subfleet;
}

/**
 * Resolve one flight through the real scope and hand back the hydrated model.
 */
function throughScope(Flight $flight, User $user): Flight
{
    return Flight::query()
        ->whereKey($flight->id)
        ->withAccessibleSubfleets($user)
        ->get()
        ->first();
}

test('a flight with live pins gets its pins and ignores its bundle', function (): void {
    $airline = Airline::factory()->create();
    $rank = Rank::factory()->create(['name' => 'Line']);

    $pinned = listableSubfleet($airline, 'Pinned', [$rank]);
    $bundled = listableSubfleet($airline, 'Bundled', [$rank]);

    $bundle = FlightBundle::factory()->create();
    $bundle->subfleets()->attach($bundled->id);

    $user = User::factory()->create(['rank_id' => $rank->id, 'airline_id' => $airline->id]);
    $flight = Flight::factory()->create(['airline_id' => $airline->id, 'bundle_id' => $bundle->id]);
    $flight->subfleets()->attach($pinned->id);

    // Replace, not union: the bundle's default must not leak in alongside.
    expect(throughScope($flight, $user)->subfleets->pluck('id')->all())->toBe([$pinned->id]);
});

test('a flight with no pins inherits its bundle defaults', function (): void {
    $airline = Airline::factory()->create();
    $rank = Rank::factory()->create(['name' => 'Line']);

    $bundled = listableSubfleet($airline, 'Bundled A', [$rank]);
    $alsoBundled = listableSubfleet($airline, 'Bundled B', [$rank]);

    // Reachable only via the (absent) fallback — proves rung 2 won.
    $unrelated = listableSubfleet($airline, 'Unrelated', [$rank]);

    $bundle = FlightBundle::factory()->create();
    $bundle->subfleets()->attach([$bundled->id, $alsoBundled->id]);

    $user = User::factory()->create(['rank_id' => $rank->id, 'airline_id' => $airline->id]);
    $flight = Flight::factory()->create(['airline_id' => $airline->id, 'bundle_id' => $bundle->id]);

    $got = throughScope($flight, $user)->subfleets->pluck('id');

    expect($got->sort()->values()->all())
        ->toBe(collect([$bundled->id, $alsoBundled->id])->sort()->values()->all())
        ->and($got)->not->toContain($unrelated->id);
});

test('a flight with no resolvable bundle resolves to nothing', function (): void {
    $airline = Airline::factory()->create();
    $rank = Rank::factory()->create(['name' => 'Line']);

    foreach (range(1, 3) as $i) {
        listableSubfleet($airline, 'Open '.$i, [$rank]);
    }

    $user = User::factory()->create(['rank_id' => $rank->id, 'airline_id' => $airline->id]);
    $flight = Flight::factory()->create(['airline_id' => $airline->id]);

    // `flights.bundle_id` is NOT NULL, so the only way to reach a null bundle is
    // a hydration that omits the column. The scope must degrade to empty rather
    // than error, and — unlike the single-flight method, which throws — an empty
    // list row is the safe direction: it narrows, it cannot widen.
    $partial = Flight::query()
        ->select('flights.id')
        ->whereKey($flight->id)
        ->withAccessibleSubfleets($user)
        ->get()
        ->first();

    expect($partial->bundle)->toBeNull()
        ->and($partial->subfleets)->toBeEmpty();
});

test('a flight whose bundle configures nothing gets nothing, not the fleet', function (): void {
    $airline = Airline::factory()->create();
    $rank = Rank::factory()->create(['name' => 'Line']);

    foreach (range(1, 3) as $i) {
        listableSubfleet($airline, 'Open '.$i, [$rank]);
    }

    $bundle = FlightBundle::factory()->create();

    $user = User::factory()->create(['rank_id' => $rank->id, 'airline_id' => $airline->id]);
    $flight = Flight::factory()->create(['airline_id' => $airline->id, 'bundle_id' => $bundle->id]);

    // Rung 3 is not implemented here. Three flyable subfleets exist and none of
    // them may appear.
    expect(throughScope($flight, $user)->subfleets)->toBeEmpty();
});

test('pins the user cannot access stay empty and do not fall through to the bundle', function (): void {
    $airline = Airline::factory()->create();
    $junior = Rank::factory()->create(['name' => 'Junior']);
    $senior = Rank::factory()->create(['name' => 'Senior']);

    $restricted = listableSubfleet($airline, 'Restricted', [$senior]);
    $bundled = listableSubfleet($airline, 'Bundled', [$junior, $senior]);

    $bundle = FlightBundle::factory()->create();
    $bundle->subfleets()->attach($bundled->id);

    $user = User::factory()->create(['rank_id' => $junior->id, 'airline_id' => $airline->id]);
    $flight = Flight::factory()->create(['airline_id' => $airline->id, 'bundle_id' => $bundle->id]);
    $flight->subfleets()->attach($restricted->id);

    // Eligibility is decided by configuration BEFORE access filtering. The
    // flight pins a subfleet, so it is a rung-1 flight, full stop. That the
    // junior cannot fly it is a legitimately empty answer — deciding
    // eligibility from the ACCESS-filtered pins instead would drop this flight
    // onto its bundle and offer aircraft the flight never listed. Regression
    // guard for 49b4632c.
    expect(throughScope($flight, $user)->subfleets)->toBeEmpty();
});

test('pins pointing only at soft deleted subfleets inherit the bundle instead', function (): void {
    $airline = Airline::factory()->create();
    $rank = Rank::factory()->create(['name' => 'Line']);

    $retired = listableSubfleet($airline, 'Retired', [$rank]);
    $bundled = listableSubfleet($airline, 'Bundled', [$rank]);

    $bundle = FlightBundle::factory()->create();
    $bundle->subfleets()->attach($bundled->id);

    $user = User::factory()->create(['rank_id' => $rank->id, 'airline_id' => $airline->id]);
    $flight = Flight::factory()->create(['airline_id' => $airline->id, 'bundle_id' => $bundle->id]);
    $flight->subfleets()->attach($retired->id);

    // Filament's DeleteAction soft deletes; nothing detaches the pivot row, so
    // the flight's only pin becomes dangling. A dangling pin is not
    // configuration — the flight must drop to its bundle rather than become
    // unbookable for everyone.
    $retired->delete();

    expect(throughScope($flight, $user)->subfleets->pluck('id')->all())->toBe([$bundled->id]);
});

test('inherited bundle defaults are access filtered', function (): void {
    $airline = Airline::factory()->create();
    $junior = Rank::factory()->create(['name' => 'Junior']);
    $senior = Rank::factory()->create(['name' => 'Senior']);

    $easy = listableSubfleet($airline, 'Easy', [$junior, $senior]);
    $hardA = listableSubfleet($airline, 'Hard A', [$senior]);
    $hardB = listableSubfleet($airline, 'Hard B', [$senior]);

    $bundle = FlightBundle::factory()->create();
    $bundle->subfleets()->attach([$easy->id, $hardA->id, $hardB->id]);

    $user = User::factory()->create(['rank_id' => $junior->id, 'airline_id' => $airline->id]);
    $flight = Flight::factory()->create(['airline_id' => $airline->id, 'bundle_id' => $bundle->id]);

    expect(throughScope($flight, $user)->subfleets->pluck('id')->all())->toBe([$easy->id]);
});

test('a soft deleted bundle still confers its defaults', function (): void {
    $airline = Airline::factory()->create();
    $rank = Rank::factory()->create(['name' => 'Line']);

    $bundled = listableSubfleet($airline, 'Bundled', [$rank]);

    $bundle = FlightBundle::factory()->create();
    $bundle->subfleets()->attach($bundled->id);

    $user = User::factory()->create(['rank_id' => $rank->id, 'airline_id' => $airline->id]);
    $flight = Flight::factory()->create(['airline_id' => $airline->id, 'bundle_id' => $bundle->id]);

    $bundle->delete();

    // Publication state is not eligibility. Matching accessibleSubfleetsFor,
    // the bundle's `deleted_at` is not consulted — the SoftDeletes scope on the
    // eager-loaded relation must be lifted or these flights silently widen (in
    // the list path, to nothing, which hides bookable aircraft).
    expect(throughScope($flight, $user)->subfleets->pluck('id')->all())->toBe([$bundled->id]);
});

test('inherited defaults are capped per bundle and the cap is deterministic', function (): void {
    $limit = (int) config('phpvms.subfleets.inherited_list_limit');

    expect($limit)->toBeGreaterThan(0);

    $airline = Airline::factory()->create();
    $rank = Rank::factory()->create(['name' => 'Line']);

    $user = User::factory()->create(['rank_id' => $rank->id, 'airline_id' => $airline->id]);

    $bundles = collect(range(1, 2))->map(function (int $b) use ($airline, $rank, $limit): array {
        $bundle = FlightBundle::factory()->create();

        $subfleets = collect(range(1, $limit + 3))
            ->map(fn (int $i): Subfleet => listableSubfleet($airline, 'Bundle '.$b.' Sub '.$i, [$rank]));

        $bundle->subfleets()->attach($subfleets->pluck('id')->all());

        return [
            'flight'   => Flight::factory()->create(['airline_id' => $airline->id, 'bundle_id' => $bundle->id]),
            'expected' => $subfleets->pluck('id')->sort()->values()->take($limit)->all(),
        ];
    });

    $run = fn (): Collection => Flight::query()
        ->whereIn('id', $bundles->pluck('flight.id')->all())
        ->withAccessibleSubfleets($user)
        ->get()
        ->keyBy('id');

    $first = $run();
    $second = $run();

    // A 100-subfleet bundle across a 100-flight page would otherwise be 10k
    // hydrated rows. The cap has to be per bundle — a flat limit on the eager
    // load would starve every bundle after the first — and the order has to be
    // pinned, or which N a pilot sees changes request to request.
    foreach ($bundles as ['flight' => $flight, 'expected' => $expected]) {
        expect($first[$flight->id]->subfleets->pluck('id')->all())->toBe($expected)
            ->and($second[$flight->id]->subfleets->pluck('id')->all())->toBe($expected);
    }
});

test('an unusable configured cap falls back instead of emptying the rung', function (array $subfleetConfig, int $expected): void {
    config(['phpvms.subfleets' => $subfleetConfig]);

    $airline = Airline::factory()->create();
    $rank = Rank::factory()->create(['name' => 'Line']);

    $user = User::factory()->create(['rank_id' => $rank->id, 'airline_id' => $airline->id]);

    $bundle = FlightBundle::factory()->create();
    $subfleets = collect(range(1, 6))
        ->map(fn (int $i): Subfleet => listableSubfleet($airline, 'Sub '.$i, [$rank]));
    $bundle->subfleets()->attach($subfleets->pluck('id')->all());

    $flight = Flight::factory()->create(['airline_id' => $airline->id, 'bundle_id' => $bundle->id]);

    // `limit(0)` compiles to `row_num <= 0`, which matches nothing: read
    // without a default, an absent or empty setting does not fall back, it
    // switches bundle inheritance off for the whole site and does it quietly.
    expect(throughScope($flight, $user)->subfleets->pluck('id')->all())
        ->toBe($subfleets->pluck('id')->sort()->values()->take($expected)->all());
})->with([
    // A config cache built before this release carries no key at all.
    'key absent' => [[], 5],
    'null'       => [['inherited_list_limit' => null], 5],
    // `PHPVMS_INHERITED_SUBFLEET_LIMIT=` in .env reads back as ''.
    'empty string' => [['inherited_list_limit' => ''], 5],
    'zero'         => [['inherited_list_limit' => 0], 5],
    'negative'     => [['inherited_list_limit' => -1], 1],
]);

test('the per bundle cap orders inside its window function', function (): void {
    $airline = Airline::factory()->create();
    $rank = Rank::factory()->create(['name' => 'Line']);

    $bundled = listableSubfleet($airline, 'Bundled', [$rank]);

    $bundle = FlightBundle::factory()->create();
    $bundle->subfleets()->attach($bundled->id);

    $user = User::factory()->create(['rank_id' => $rank->id, 'airline_id' => $airline->id]);
    $flight = Flight::factory()->create(['airline_id' => $airline->id, 'bundle_id' => $bundle->id]);

    $seen = [];
    DB::listen(function (QueryExecuted $query) use (&$seen): void {
        // Quoting is the driver's business — "x", `x` and [x] all mean x.
        $seen[] = str_replace(['"', '`', '[', ']'], '', $query->sql);
    });

    throughScope($flight, $user);

    $windowed = array_values(array_filter(
        $seen,
        fn (string $sql): bool => str_contains($sql, 'bundle_subfleet') && str_contains($sql, 'row_number()')
    ));

    // The per-bundle cap compiles the limit down to
    // `row_number() over (partition by bundle_subfleet.bundle_id order by subfleets.id)`.
    // Drop that inner order and SQLite still hands back insertion order, so the
    // cap test above keeps passing while MySQL and Postgres become free to
    // return a different N of the bundle's subfleets every request. The
    // guarantee is only observable in the SQL, so that is where it is asserted
    // — and inside the window specifically, because the outer query keeps an
    // `order by laravel_row` that survives the loss.
    expect($windowed)->toHaveCount(1)
        ->and($windowed[0])->toMatch(
            '/row_number\(\)\s*over\s*\(\s*partition\s+by\s+bundle_subfleet\.bundle_id\s+order\s+by\s+subfleets\.id\b/i'
        );
});

test('inheritance resolves through a nested eager load', function (): void {
    $airline = Airline::factory()->create();
    $rank = Rank::factory()->create(['name' => 'Line']);

    $pinned = listableSubfleet($airline, 'Pinned', [$rank]);
    $bundled = listableSubfleet($airline, 'Bundled', [$rank]);

    $bundle = FlightBundle::factory()->create();
    $bundle->subfleets()->attach($bundled->id);

    $user = User::factory()->create(['rank_id' => $rank->id, 'airline_id' => $airline->id]);

    $inherits = Flight::factory()->create(['airline_id' => $airline->id, 'bundle_id' => $bundle->id]);
    $pins = Flight::factory()->create(['airline_id' => $airline->id, 'bundle_id' => $bundle->id]);
    $pins->subfleets()->attach($pinned->id);

    foreach ([$inherits, $pins] as $flight) {
        Bid::create(['user_id' => $user->id, 'flight_id' => $flight->id]);
    }

    // BidService::findBidsForUser applies the scope exactly like this. The
    // afterQuery merge has to fire on the relation's own get(), not just on a
    // top-level one.
    $bids = Bid::query()
        ->where('user_id', $user->id)
        ->with(['flight' => fn ($q) => $q->withAccessibleSubfleets($user)])
        ->get()
        ->keyBy('flight_id');

    expect($bids[$inherits->id]->flight->subfleets->pluck('id')->all())->toBe([$bundled->id])
        ->and($bids[$pins->id]->flight->subfleets->pluck('id')->all())->toBe([$pinned->id]);
});

test('inherited subfleets arrive with their fares and aircraft loaded', function (): void {
    $airline = Airline::factory()->create();
    $rank = Rank::factory()->create(['name' => 'Line']);

    $bundled = listableSubfleet($airline, 'Bundled', [$rank]);
    $bundled->fares()->attach(Fare::factory()->create()->id, ['price' => 100]);

    $bundle = FlightBundle::factory()->create();
    $bundle->subfleets()->attach($bundled->id);

    $user = User::factory()->create(['rank_id' => $rank->id, 'airline_id' => $airline->id]);
    $flight = Flight::factory()->create(['airline_id' => $airline->id, 'bundle_id' => $bundle->id]);

    $subfleet = throughScope($flight, $user)->subfleets->first();

    // FareService::getReconciledFaresForFlight() reads `$subfleet->fares`, and
    // preventLazyLoading() is on outside production — the inherited branch has
    // to carry the same nested loads as the pinned one.
    expect($subfleet->relationLoaded('fares'))->toBeTrue()
        ->and($subfleet->relationLoaded('aircraft'))->toBeTrue()
        ->and($subfleet->fares)->toHaveCount(1)
        ->and($subfleet->aircraft)->toHaveCount(1);
});

test('flights sharing a bundle get their own copies of the inherited subfleets', function (): void {
    $airline = Airline::factory()->create();
    $rank = Rank::factory()->create(['name' => 'Line']);

    $fare = Fare::factory()->create(['price' => 100]);
    $bundled = listableSubfleet($airline, 'Bundled', [$rank]);
    $bundled->fares()->attach($fare->id, ['price' => 200]);

    $bundle = FlightBundle::factory()->create();
    $bundle->subfleets()->attach($bundled->id);

    $user = User::factory()->create(['rank_id' => $rank->id, 'airline_id' => $airline->id]);

    $cheap = Flight::factory()->create(['airline_id' => $airline->id, 'bundle_id' => $bundle->id]);
    $cheap->fares()->attach($fare->id, ['price' => 300]);

    $dear = Flight::factory()->create(['airline_id' => $airline->id, 'bundle_id' => $bundle->id]);
    $dear->fares()->attach($fare->id, ['price' => 900]);

    $flights = Flight::query()
        ->whereIn('id', [$cheap->id, $dear->id])
        ->with('fares')
        ->withAccessibleSubfleets($user)
        ->get()
        ->keyBy('id');

    // One hydrated Bundle backs every flight that points at it, so handing the
    // flights its Subfleet/Fare instances verbatim would let
    // getReconciledFaresForFlight() — which writes onto both — reconcile one
    // flight's overrides into its neighbours on the same page.
    $fareSvc = app(FareService::class);
    foreach ($flights as $flight) {
        $fareSvc->getReconciledFaresForFlight($flight);
    }

    expect($flights[$cheap->id]->subfleets->first())
        ->not->toBe($flights[$dear->id]->subfleets->first())
        ->and((float) $flights[$cheap->id]->subfleets->first()->fares->first()->price)->toBe(300.0)
        ->and((float) $flights[$dear->id]->subfleets->first()->fares->first()->price)->toBe(900.0);
});

test('the flight api response shape is unchanged by inheritance', function (): void {
    $airline = Airline::factory()->create();
    $rank = Rank::factory()->create(['name' => 'Line']);

    $pinned = listableSubfleet($airline, 'Pinned', [$rank]);
    $bundled = listableSubfleet($airline, 'Bundled', [$rank]);

    $bundle = FlightBundle::factory()->create();
    $bundle->subfleets()->attach($bundled->id);

    $user = User::factory()->create(['rank_id' => $rank->id, 'airline_id' => $airline->id]);

    $inherits = Flight::factory()->create([
        'airline_id' => $airline->id,
        'bundle_id'  => $bundle->id,
        'enabled'    => true,
        'visible'    => true,
    ]);

    $pins = Flight::factory()->create([
        'airline_id' => $airline->id,
        'bundle_id'  => $bundle->id,
        'enabled'    => true,
        'visible'    => true,
    ]);
    $pins->subfleets()->attach($pinned->id);

    $body = $this->withHeader('Authorization', $user->api_key)->get('/api/flights')->assertOk()->json();

    $rows = collect($body['data'])->keyBy('id');

    // FlightResource::toArray leans on parent::toArray, which serialises every
    // loaded attribute and relation. The scope's has-pins probe and the bundle
    // it loads to resolve rung 2 are internals — neither may reach the wire,
    // and both flights must serialise to the identical key set.
    expect($rows)->toHaveCount(2);

    foreach ([$inherits->id, $pins->id] as $id) {
        expect(array_keys($rows[$id]))
            ->not->toContain('bundle')
            ->and(array_keys($rows[$id]))->not->toContain('has_live_pins');
    }

    $inheritedKeys = array_keys($rows[$inherits->id]);
    $pinnedKeys = array_keys($rows[$pins->id]);
    sort($inheritedKeys);
    sort($pinnedKeys);

    expect($inheritedKeys)->toBe($pinnedKeys)
        ->and($rows[$inherits->id]['subfleets'])->toHaveCount(1)
        ->and($rows[$inherits->id]['subfleets'][0]['id'])->toBe($bundled->id)
        ->and($rows[$pins->id]['subfleets'][0]['id'])->toBe($pinned->id);
});

test('a bundle the caller eager loaded outlives the scope', function (): void {
    $airline = Airline::factory()->create();
    $rank = Rank::factory()->create(['name' => 'Line']);

    $bundled = listableSubfleet($airline, 'Bundled', [$rank]);

    $bundle = FlightBundle::factory()->create();
    $bundle->subfleets()->attach($bundled->id);

    $user = User::factory()->create(['rank_id' => $rank->id, 'airline_id' => $airline->id]);

    // Two flights, because Builder::hydrate only arms preventLazyLoading() on
    // a result set of more than one row — a single-row page would hide the
    // violation this test exists to catch.
    $flights = collect(range(1, 2))->map(fn (): Flight => Flight::factory()->create([
        'airline_id' => $airline->id,
        'bundle_id'  => $bundle->id,
    ]));

    $got = Flight::query()
        ->with('bundle')
        ->whereIn('id', $flights->pluck('id')->all())
        ->withAccessibleSubfleets($user)
        ->get();

    // The scope loads `bundle` for rung 2 and drops it again so it stays off
    // the wire, but a bundle the caller asked for is the caller's. Dropping it
    // turns this read into a lazy load: LazyLoadingViolationException outside
    // production, a silent N+1 inside it. Rung 2 still has to resolve on the
    // same query.
    expect($got->pluck('bundle.id')->all())->toBe([$bundle->id, $bundle->id])
        ->and($got->every(fn (Flight $flight): bool => $flight->relationLoaded('bundle')))->toBeTrue()
        ->and($got->pluck('subfleets.*.id')->all())->toBe([[$bundled->id], [$bundled->id]]);

    // And when nobody asked, it is still gone.
    expect(throughScope($flights->first(), $user)->relationLoaded('bundle'))->toBeFalse();
});

test('applying the scope twice resolves the same as applying it once', function (): void {
    $airline = Airline::factory()->create();
    $rank = Rank::factory()->create(['name' => 'Line']);

    $pinned = listableSubfleet($airline, 'Pinned', [$rank]);
    $bundled = listableSubfleet($airline, 'Bundled', [$rank]);

    $bundle = FlightBundle::factory()->create();
    $bundle->subfleets()->attach($bundled->id);

    $user = User::factory()->create(['rank_id' => $rank->id, 'airline_id' => $airline->id]);

    $inherits = Flight::factory()->create(['airline_id' => $airline->id, 'bundle_id' => $bundle->id]);
    $pins = Flight::factory()->create(['airline_id' => $airline->id, 'bundle_id' => $bundle->id]);
    $pins->subfleets()->attach($pinned->id);

    // Nothing chains the scope twice today, but it is a plain query scope and
    // two layers each adding it is one refactor away. The second pass must not
    // read the consumed has-pins probe and blank the pinned flight.
    $got = Flight::query()
        ->whereIn('id', [$inherits->id, $pins->id])
        ->withAccessibleSubfleets($user)
        ->withAccessibleSubfleets($user)
        ->get()
        ->keyBy('id');

    expect($got[$pins->id]->subfleets->pluck('id')->all())->toBe([$pinned->id])
        ->and($got[$inherits->id]->subfleets->pluck('id')->all())->toBe([$bundled->id]);
});

test('an inherited subfleet carries a flight pivot, not the bundle pivot it was loaded through', function (): void {
    $airline = Airline::factory()->create();
    $rank = Rank::factory()->create(['name' => 'Line']);

    $bundled = listableSubfleet($airline, 'Bundled', [$rank]);

    $bundle = FlightBundle::factory()->create();
    $bundle->subfleets()->attach($bundled->id);

    $user = User::factory()->create(['rank_id' => $rank->id, 'airline_id' => $airline->id]);
    $flight = Flight::factory()->create(['airline_id' => $airline->id, 'bundle_id' => $bundle->id]);

    $subfleet = throughScope($flight, $user)->subfleets->first();

    // These are hydrated off `bundle_subfleet`, so they arrive carrying that
    // pivot — and SubfleetResource serialises whatever `pivot` holds. Left
    // alone it publishes bundle ids on a flights endpoint and drops the
    // `flight_id` every subfleet row here has always had. The relation being
    // populated is `$flight->subfleets`, so the pivot has to describe that
    // pairing, whether or not a `flight_subfleet` row backs it.
    expect($subfleet->pivot)->toBeInstanceOf(Pivot::class)
        ->and($subfleet->pivot->flight_id)->toBe($flight->id)
        ->and($subfleet->pivot->subfleet_id)->toBe($bundled->id)
        ->and(array_keys($subfleet->pivot->getAttributes()))->toBe(['flight_id', 'subfleet_id'])
        ->and($subfleet->pivot->getAttributes())->not->toHaveKey('bundle_id');
});

test('a pinned subfleet keeps the real flight_subfleet pivot', function (): void {
    $airline = Airline::factory()->create();
    $rank = Rank::factory()->create(['name' => 'Line']);

    $pinned = listableSubfleet($airline, 'Pinned', [$rank]);
    $bundled = listableSubfleet($airline, 'Bundled', [$rank]);

    $bundle = FlightBundle::factory()->create();
    $bundle->subfleets()->attach($bundled->id);

    $user = User::factory()->create(['rank_id' => $rank->id, 'airline_id' => $airline->id]);
    $flight = Flight::factory()->create(['airline_id' => $airline->id, 'bundle_id' => $bundle->id]);
    $flight->subfleets()->attach($pinned->id);

    $subfleet = throughScope($flight, $user)->subfleets->first();

    // The rung-1 branch is untouched by the inherited-pivot restatement: this
    // pivot is the row the query joined through, and a matching row really is
    // in `flight_subfleet`. The inherited case above deliberately mirrors this
    // shape without a backing row.
    expect($subfleet->pivot->getTable())->toBe('flight_subfleet')
        ->and($subfleet->pivot->flight_id)->toBe($flight->id)
        ->and($subfleet->pivot->subfleet_id)->toBe($pinned->id)
        ->and(DB::table('flight_subfleet')
            ->where('flight_id', $flight->id)
            ->where('subfleet_id', $pinned->id)
            ->exists())->toBeTrue();
});

test('two flights on one bundle each get a pivot naming their own flight', function (): void {
    $airline = Airline::factory()->create();
    $rank = Rank::factory()->create(['name' => 'Line']);

    $bundled = listableSubfleet($airline, 'Bundled', [$rank]);

    $bundle = FlightBundle::factory()->create();
    $bundle->subfleets()->attach($bundled->id);

    $user = User::factory()->create(['rank_id' => $rank->id, 'airline_id' => $airline->id]);

    $first = Flight::factory()->create(['airline_id' => $airline->id, 'bundle_id' => $bundle->id]);
    $second = Flight::factory()->create(['airline_id' => $airline->id, 'bundle_id' => $bundle->id]);

    $flights = Flight::query()
        ->whereIn('id', [$first->id, $second->id])
        ->withAccessibleSubfleets($user)
        ->get()
        ->keyBy('id');

    $firstSubfleet = $flights[$first->id]->subfleets->first();
    $secondSubfleet = $flights[$second->id]->subfleets->first();

    // One hydrated Subfleet backs both flights. Stating the pivot on it rather
    // than on each flight's own clone would leave whichever flight was
    // processed last owning the id on both rows — one flight silently
    // advertising the other's subfleet pairing.
    expect($firstSubfleet->pivot)->not->toBe($secondSubfleet->pivot)
        ->and($firstSubfleet->pivot->flight_id)->toBe($first->id)
        ->and($secondSubfleet->pivot->flight_id)->toBe($second->id)
        ->and($firstSubfleet->pivot->subfleet_id)->toBe($bundled->id)
        ->and($secondSubfleet->pivot->subfleet_id)->toBe($bundled->id);
});

test('the serialised subfleet pivot is identical for pinned and inherited flights', function (): void {
    $airline = Airline::factory()->create();
    $rank = Rank::factory()->create(['name' => 'Line']);

    $pinned = listableSubfleet($airline, 'Pinned', [$rank]);
    $bundled = listableSubfleet($airline, 'Bundled', [$rank]);

    $bundle = FlightBundle::factory()->create();
    $bundle->subfleets()->attach($bundled->id);

    $user = User::factory()->create(['rank_id' => $rank->id, 'airline_id' => $airline->id]);

    $inherits = Flight::factory()->create([
        'airline_id' => $airline->id,
        'bundle_id'  => $bundle->id,
        'enabled'    => true,
        'visible'    => true,
    ]);

    $pins = Flight::factory()->create([
        'airline_id' => $airline->id,
        'bundle_id'  => $bundle->id,
        'enabled'    => true,
        'visible'    => true,
    ]);
    $pins->subfleets()->attach($pinned->id);

    $body = $this->withHeader('Authorization', $user->api_key)->get('/api/flights')->assertOk()->json();

    $rows = collect($body['data'])->keyBy('id');

    // The response-shape test above only compares top-level flight keys, so a
    // pivot swapped out underneath `subfleets[]` sails straight through it.
    // Consumers key off `pivot.flight_id`; `pivot.bundle_id` is an internal
    // join artefact that must never reach the wire.
    $pivotOf = function (array $row): array {
        expect($row['subfleets'])->toHaveCount(1)
            ->and($row['subfleets'][0])->toHaveKey('pivot');

        return $row['subfleets'][0]['pivot'];
    };

    $inheritedPivot = $pivotOf($rows[$inherits->id]);
    $pinnedPivot = $pivotOf($rows[$pins->id]);

    $inheritedKeys = array_keys($inheritedPivot);
    $pinnedKeys = array_keys($pinnedPivot);
    sort($inheritedKeys);
    sort($pinnedKeys);

    expect($inheritedKeys)->toBe($pinnedKeys)
        ->and($inheritedPivot)->not->toHaveKey('bundle_id')
        ->and($pinnedPivot)->not->toHaveKey('bundle_id')
        ->and($inheritedPivot['flight_id'])->toBe($inherits->id)
        ->and($inheritedPivot['subfleet_id'])->toBe($bundled->id)
        ->and($pinnedPivot['flight_id'])->toBe($pins->id)
        ->and($pinnedPivot['subfleet_id'])->toBe($pinned->id);
});
