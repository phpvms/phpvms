<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

function pirepArchiveReshapeMigration(): object
{
    return require base_path('database/migrations/2026_08_10_000000_reshape_pirep_archive_columns.php');
}

/** The shape the create migration first shipped, before it was rewritten. */
function recreateLegacyPirepArchiveTable(): void
{
    Schema::dropIfExists('pirep_archive');

    Schema::create('pirep_archive', function (Blueprint $table): void {
        $table->string('pirep_id', 36)->primary();
        $table->string('flight_id', 36)->nullable();
        $table->json('data');
        $table->timestamps();
    });
}

test('reshapes a legacy pirep_archive table into the split columns', function (): void {
    recreateLegacyPirepArchiveTable();

    pirepArchiveReshapeMigration()->up();

    expect(Schema::hasColumn('pirep_archive', 'data'))->toBeFalse()
        ->and(Schema::hasColumns('pirep_archive', ['flight', 'aircraft', 'simbrief', 'navlog']))->toBeTrue()
        ->and(Schema::hasColumns('pirep_archive', ['pirep_id', 'flight_id']))->toBeTrue();
});

test('leaves an already-correct pirep_archive table alone', function (): void {
    pirepArchiveReshapeMigration()->up();

    expect(Schema::hasColumns('pirep_archive', ['flight', 'aircraft', 'simbrief', 'navlog']))->toBeTrue();
});
