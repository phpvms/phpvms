<?php

declare(strict_types=1);

use App\Models\Flight;
use App\Models\Subfleet;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * The migration rebuilds `flight_subfleet` on SQLite rather than skipping it, so
 * the constraint is real on the test connection and its behaviour is directly
 * observable here. Tests that need to exercise up() against pre-existing bad
 * data call down() first to get back to the unconstrained shape.
 */

beforeEach(function (): void {
    $this->migration = require database_path('migrations/2026_07_25_020000_add_flight_subfleet_foreign_keys.php');
});

it('constrains both pivot columns', function (): void {
    $columns = collect(Schema::getForeignKeys('flight_subfleet'))
        ->flatMap(fn (array $key) => $key['columns']);

    expect($columns)->toContain('flight_id')->toContain('subfleet_id');
});

it('removes the pivot row when a subfleet is force-deleted', function (): void {
    $flight = Flight::factory()->create();
    $subfleet = Subfleet::factory()->create();

    DB::table('flight_subfleet')->insert(['flight_id' => $flight->id, 'subfleet_id' => $subfleet->id]);

    // Both models soft-delete, so only a force-delete reaches the database and
    // triggers the cascade — that is the case that used to leave the pivot behind.
    $subfleet->forceDelete();

    expect(DB::table('flight_subfleet')->where('subfleet_id', $subfleet->id)->count())->toBe(0);
});

it('removes the pivot row when a flight is force-deleted', function (): void {
    $flight = Flight::factory()->create();
    $subfleet = Subfleet::factory()->create();

    DB::table('flight_subfleet')->insert(['flight_id' => $flight->id, 'subfleet_id' => $subfleet->id]);

    $flight->forceDelete();

    expect(DB::table('flight_subfleet')->where('flight_id', $flight->id)->count())->toBe(0);
});

it('rejects a pivot row pointing at a subfleet that does not exist', function (): void {
    $flight = Flight::factory()->create();

    DB::table('flight_subfleet')->insert(['flight_id' => $flight->id, 'subfleet_id' => 424242]);
})->throws(QueryException::class);

it('sweeps orphaned rows before constraining the table', function (): void {
    $this->migration->down();

    $flight = Flight::factory()->create();
    $subfleet = Subfleet::factory()->create();

    DB::table('flight_subfleet')->insert([
        ['flight_id' => $flight->id, 'subfleet_id' => $subfleet->id],
        ['flight_id' => 'gone', 'subfleet_id' => $subfleet->id],
        ['flight_id' => $flight->id, 'subfleet_id' => 424242],
    ]);

    $this->migration->up();

    expect(DB::table('flight_subfleet')->get())->toHaveCount(1)
        ->and(DB::table('flight_subfleet')->value('flight_id'))->toBe($flight->id);
});

it('preserves the pivot indexes across the rebuild', function (): void {
    $this->migration->down();
    $this->migration->up();

    $indexes = collect(Schema::getIndexes('flight_subfleet'))->pluck('name');

    expect($indexes)->toContain('flight_subfleet_flight_id_subfleet_id_index')
        ->toContain('flight_subfleet_subfleet_id_flight_id_index');
})->skip(fn (): bool => DB::connection()->getDriverName() !== 'sqlite', 'Only the SQLite path rebuilds the table.');

it('recovers when a previous run died before swapping the rebuilt table in', function (): void {
    $this->migration->down();

    // Laravel's SQLiteGrammar opts out of migration transactions, so a killed
    // run really can leave the scratch table behind. Without the dropIfExists
    // guard every later attempt dies on "table already exists" and the install
    // can never take the constraint.
    Schema::create('flight_subfleet_rebuild', function (Blueprint $table): void {
        $table->bigIncrements('id');
    });

    $this->migration->up();

    $columns = collect(Schema::getForeignKeys('flight_subfleet'))->flatMap(fn (array $key) => $key['columns']);

    expect(Schema::hasTable('flight_subfleet_rebuild'))->toBeFalse()
        ->and($columns)->toContain('flight_id')->toContain('subfleet_id');
})->skip(fn (): bool => DB::connection()->getDriverName() !== 'sqlite', 'Only the SQLite path rebuilds the table.');

it('is a no-op when re-run against an already-constrained table', function (): void {
    $flight = Flight::factory()->create();
    $subfleet = Subfleet::factory()->create();

    DB::table('flight_subfleet')->insert(['flight_id' => $flight->id, 'subfleet_id' => $subfleet->id]);

    $this->migration->up();

    expect(DB::table('flight_subfleet')->count())->toBe(1)
        ->and(Schema::getForeignKeys('flight_subfleet'))->toHaveCount(2);
});
