<?php

declare(strict_types=1);

beforeEach(function (): void {
    $this->migration = require database_path('migrations/2026_08_11_010000_create_simbrief_attempts_table.php');
    $this->migration->down();
});

it('adds and rolls back the additive SimBrief attempt storage', function (): void {
    expect(Schema::hasTable('simbrief_attempts'))->toBeFalse()
        ->and(Schema::hasColumn('simbrief', 'static_id'))->toBeFalse();

    $this->migration->up();

    expect(Schema::hasTable('simbrief_attempts'))->toBeTrue()
        ->and(Schema::hasColumns('simbrief_attempts', [
            'static_id',
            'user_id',
            'flight_id',
            'aircraft_id',
            'fare_data',
            'expires_at',
        ]))->toBeTrue()
        ->and(Schema::hasColumn('simbrief', 'static_id'))->toBeTrue();

    $this->migration->down();

    expect(Schema::hasTable('simbrief_attempts'))->toBeFalse()
        ->and(Schema::hasColumn('simbrief', 'static_id'))->toBeFalse();
});
