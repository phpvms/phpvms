<?php

declare(strict_types=1);

use App\Exceptions\BidExistsForAircraft;
use App\Exceptions\BidExistsForFlight;
use App\Models\Aircraft;
use App\Models\Airline;
use App\Models\Airport;
use App\Models\Bid;
use App\Models\Flight;
use App\Models\Subfleet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    if (DB::getDriverName() === 'sqlite') {
        $this->markTestSkipped('Concurrent bid tests require database row locks.');
    }

    updateSetting('units.distance', 'nmi');
    updateSetting('bids.allow_multiple_bids', true);
    updateSetting('bids.disable_flight_on_bid', false);
    updateSetting('bids.block_aircraft', false);
    updateSetting('pireps.restrict_aircraft_to_rank', false);
    updateSetting('pireps.restrict_aircraft_to_typerating', false);
    updateSetting('pireps.only_aircraft_at_dpt_airport', false);
    updateSetting('flights.only_company_aircraft', false);
    updateSetting('simbrief.block_aircraft', false);
});

/**
 * @param  list<array{user: int, flight: string, aircraft: int|null}> $attempts
 * @return list<array{status: string, type: string|null}>
 */
function raceBidAttempts(array $attempts): array
{
    $directory = sys_get_temp_dir().'/phpvms-bid-race-'.bin2hex(random_bytes(8));
    mkdir($directory, 0700);
    $processes = [];
    $connection = config('database.connections.'.DB::getDefaultConnection());

    foreach ($attempts as $index => $attempt) {
        $payload = base64_encode(json_encode([
            'attempt'    => $attempt,
            'ready'      => $directory.'/ready-'.$index,
            'go'         => $directory.'/go',
            'result'     => $directory.'/result-'.$index,
            'kvp'        => config('phpvms.kvp_storage_path'),
            'connection' => [
                'driver'   => DB::getDriverName(),
                'url'      => $connection['url'] ?? null,
                'host'     => $connection['host'] ?? null,
                'port'     => $connection['port'] ?? null,
                'database' => $connection['database'] ?? null,
                'username' => $connection['username'] ?? null,
                'password' => $connection['password'] ?? null,
            ],
        ], JSON_THROW_ON_ERROR));
        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, base_path('tests/Support/bid-race-worker.php'), $payload],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            base_path(),
        );
        if (!is_resource($process)) {
            throw new RuntimeException('Could not start concurrent bid worker.');
        }

        $processes[] = ['process' => $process, 'pipes' => $pipes];
    }

    try {
        $deadline = microtime(true) + 10;
        do {
            $ready = count(glob($directory.'/ready-*') ?: []);
            if ($ready === count($attempts)) {
                break;
            }

            usleep(1000);
        } while (microtime(true) < $deadline);

        if ($ready !== count($attempts)) {
            throw new RuntimeException('Concurrent bid workers did not become ready.');
        }

        touch($directory.'/go');
        foreach ($processes as $worker) {
            $stdout = stream_get_contents($worker['pipes'][1]);
            $stderr = stream_get_contents($worker['pipes'][2]);
            fclose($worker['pipes'][1]);
            fclose($worker['pipes'][2]);
            $exitCode = proc_close($worker['process']);
            if ($exitCode !== 0) {
                throw new RuntimeException(trim($stderr) ?: trim($stdout));
            }
        }

        return array_map(
            fn (int $index): array => json_decode(
                (string) file_get_contents($directory.'/result-'.$index),
                true,
                flags: JSON_THROW_ON_ERROR,
            ),
            array_keys($attempts),
        );
    } finally {
        foreach (glob($directory.'/*') ?: [] as $file) {
            unlink($file);
        }

        rmdir($directory);
    }
}

/**
 * Commit fixtures so forked workers can see them, then restore a clean database
 * and transaction for RefreshDatabase after the race.
 *
 * @param Closure(): void $assertions
 */
function withCommittedConcurrencyFixtures(Closure $assertions): void
{
    DB::commit();

    try {
        $assertions();
    } finally {
        DB::disconnect();
        DB::reconnect();
        Artisan::call('migrate:fresh', ['--force' => true]);
        RefreshDatabaseState::$migrated = true;
        DB::beginTransaction();
    }
}

it('serializes concurrent bids for an exclusive flight', function (): void {
    updateSetting('bids.disable_flight_on_bid', true);

    $first = User::factory()->create();
    $second = User::factory()->create(['airline_id' => $first->airline_id]);
    $flight = Flight::factory()->create(['airline_id' => $first->airline_id]);

    withCommittedConcurrencyFixtures(function () use ($first, $second, $flight): void {
        $results = raceBidAttempts([
            ['user' => $first->id, 'flight' => $flight->id, 'aircraft' => null],
            ['user' => $second->id, 'flight' => $flight->id, 'aircraft' => null],
        ]);
        $created = collect($results)->where('status', 'created')->count();
        $conflicts = collect($results)->where('type', BidExistsForFlight::class)->count();

        expect($created)->toBe(1)
            ->and($conflicts)->toBe(1)
            ->and(Bid::query()->where('flight_id', $flight->id)->count())->toBe(1);
    });
});

it('serializes concurrent reservations for one aircraft', function (): void {
    updateSetting('bids.block_aircraft', true);

    $first = User::factory()->create();
    $second = User::factory()->create(['airline_id' => $first->airline_id]);
    $airline = Airline::query()->findOrFail($first->airline_id);
    $departure = Airport::factory()->create();
    $arrival = Airport::factory()->create();
    $subfleet = Subfleet::factory()->create(['airline_id' => $airline->id]);
    $aircraft = Aircraft::factory()->create([
        'subfleet_id' => $subfleet->id,
        'airport_id'  => $departure->id,
    ]);
    $flights = Flight::factory()->count(2)->hasAttached($subfleet)->create([
        'airline_id'     => $airline->id,
        'dpt_airport_id' => $departure->id,
        'arr_airport_id' => $arrival->id,
    ]);

    withCommittedConcurrencyFixtures(function () use ($first, $second, $flights, $aircraft): void {
        $results = raceBidAttempts([
            ['user' => $first->id, 'flight' => $flights[0]->id, 'aircraft' => $aircraft->id],
            ['user' => $second->id, 'flight' => $flights[1]->id, 'aircraft' => $aircraft->id],
        ]);

        $created = collect($results)->where('status', 'created')->count();
        $conflicts = collect($results)->where('type', BidExistsForAircraft::class)->count();

        expect($created)->toBe(1)
            ->and($conflicts)->toBe(1)
            ->and(Bid::query()->where('aircraft_id', $aircraft->id)->count())->toBe(1);
    });
});
