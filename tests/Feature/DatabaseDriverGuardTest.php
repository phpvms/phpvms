<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

test('the active database driver matches the CI matrix expectation', function (): void {
    // CI sets EXPECTED_DB_DRIVER from the job matrix (independent of the
    // phpunit.xml DB_CONNECTION the test bootstrap reads). If the database
    // wiring silently falls back to sqlite, the active driver won't match the
    // matrix and the whole shard fails instead of passing vacuously.
    $expected = getenv('EXPECTED_DB_DRIVER') ?: null;

    if (blank($expected)) {
        $this->markTestSkipped('EXPECTED_DB_DRIVER not set; only enforced in the CI matrix.');
    }

    expect(DB::connection()->getDriverName())->toBe($expected);
});
