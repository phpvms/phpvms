<?php

declare(strict_types=1);

use App\Models\Flight;

it('active scope produces same SQL as visible scope', function (): void {
    $activeSql = Flight::active()->toSql();
    $visibleSql = Flight::visible()->toSql();

    expect($activeSql)->toBe($visibleSql);
});

it('active scope is a plain alias and does not trigger deprecation notices', function (): void {
    $triggered = false;
    set_error_handler(function (int $errno, string $errstr) use (&$triggered): bool {
        if ($errno === E_DEPRECATED || $errno === E_USER_DEPRECATED) {
            $triggered = true;

            return true;
        }

        // Anything else is outside what this test asserts on, so hand it back to
        // PHP rather than swallowing it and hiding a real warning from the run.
        return false;
    });

    Flight::active()->toSql();

    restore_error_handler();

    expect($triggered)->toBeFalse();
});
