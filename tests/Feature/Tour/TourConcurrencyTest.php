<?php

declare(strict_types=1);

use App\Exceptions\BidExistsForAircraft;
use App\Features\Tour\Models\UserTour;
use App\Models\Bid;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    if (DB::getDriverName() === 'sqlite') {
        $this->markTestSkipped('Concurrent tour tests require database row locks.');
    }

    tourSettingsBaseline();
});

/**
 * Race N addBid() calls in separate processes, using the same worker the
 * ordinary bid race uses — the tour path is reached through addBid().
 *
 * A near-copy of raceBidAttempts() in tests/Feature/Skylight/BidConcurrencyTest.php,
 * under its own name: Pest loads both files into one process, so sharing the
 * name would be a redeclaration.
 *
 * @param  list<array{user: int, flight: string, aircraft: int|null}> $attempts
 * @return list<array{status: string, type: string|null}>
 */
function raceTourStarts(array $attempts): array
{
    $directory = sys_get_temp_dir().'/phpvms-tour-race-'.bin2hex(random_bytes(8));
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
            throw new RuntimeException('Could not start concurrent tour worker.');
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
            throw new RuntimeException('Concurrent tour workers did not become ready.');
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
 * Commit fixtures so the forked workers can see them, then put the database and
 * the RefreshDatabase transaction back afterwards.
 *
 * @param Closure(): void $assertions
 */
function withCommittedTourFixtures(Closure $assertions): void
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

it('lets only one pilot reserve the aircraft for a tour', function (): void {
    updateSetting('bids.block_aircraft', true);

    ['user' => $first, 'flights' => $flights, 'aircraft' => $aircraft] = makeTour(3);
    $second = User::factory()->create([
        'airline_id'      => $first->airline_id,
        'curr_airport_id' => $first->curr_airport_id,
    ]);

    withCommittedTourFixtures(function () use ($first, $second, $flights, $aircraft): void {
        $results = raceTourStarts([
            ['user' => $first->id, 'flight' => $flights[0]->id, 'aircraft' => $aircraft->id],
            ['user' => $second->id, 'flight' => $flights[0]->id, 'aircraft' => $aircraft->id],
        ]);

        expect(collect($results)->where('status', 'created')->count())->toBe(1)
            ->and(collect($results)->where('type', BidExistsForAircraft::class)->count())->toBe(1)
            ->and(UserTour::query()->count())->toBe(1)
            ->and(Bid::query()->where('aircraft_id', $aircraft->id)->count())->toBe(3);
    });
});

it('creates one tour when the same pilot double-submits a start', function (): void {
    ['user' => $user, 'flights' => $flights, 'aircraft' => $aircraft] = makeTour(3);

    withCommittedTourFixtures(function () use ($user, $flights, $aircraft): void {
        $results = raceTourStarts([
            ['user' => $user->id, 'flight' => $flights[0]->id, 'aircraft' => $aircraft->id],
            ['user' => $user->id, 'flight' => $flights[0]->id, 'aircraft' => $aircraft->id],
        ]);

        expect(collect($results)->where('status', 'created')->count())->toBe(2)
            ->and(UserTour::query()->count())->toBe(1)
            ->and(Bid::query()->where('user_id', $user->id)->count())->toBe(3);
    });
});
