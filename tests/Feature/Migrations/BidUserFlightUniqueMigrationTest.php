<?php

declare(strict_types=1);

use App\Models\Flight;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->migration = require database_path('migrations/2026_08_10_210000_add_unique_user_flight_to_bids.php');
    $this->migration->down();
    $this->user = User::factory()->create();
    $this->flight = Flight::factory()->create(['airline_id' => $this->user->airline_id]);
});

it('stops without deleting duplicate bid data', function (): void {
    $rows = [
        ['user_id' => $this->user->id, 'flight_id' => $this->flight->id, 'aircraft_id' => null, 'created_at' => now(), 'updated_at' => now()],
        ['user_id' => $this->user->id, 'flight_id' => $this->flight->id, 'aircraft_id' => null, 'created_at' => now(), 'updated_at' => now()],
    ];
    DB::table('bids')->insert($rows);

    expect(fn () => $this->migration->up())
        ->toThrow(RuntimeException::class)
        ->and(DB::table('bids')->count())->toBe(2);
});

it('adds and rolls back the pilot-flight unique constraint', function (): void {
    $this->migration->up();
    DB::table('bids')->insert([
        'user_id'     => $this->user->id,
        'flight_id'   => $this->flight->id,
        'aircraft_id' => null,
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);

    $insertDuplicate = fn () => DB::table('bids')->insert([
        'user_id'     => $this->user->id,
        'flight_id'   => $this->flight->id,
        'aircraft_id' => null,
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);

    if (DB::getDriverName() === 'pgsql') {
        $insertDuplicate = fn () => DB::transaction($insertDuplicate);
    }

    expect($insertDuplicate)->toThrow(QueryException::class);

    $this->migration->down();
    DB::table('bids')->insert([
        'user_id'     => $this->user->id,
        'flight_id'   => $this->flight->id,
        'aircraft_id' => null,
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);
    expect(DB::table('bids')->count())->toBe(2);
});
