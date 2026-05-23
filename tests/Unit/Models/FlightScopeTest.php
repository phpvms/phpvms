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
    set_error_handler(function ($errno, $errstr) use (&$triggered): void {
        if (str_contains($errstr, 'deprecated')) {
            $triggered = true;
        }
    });

    Flight::active()->toSql();

    restore_error_handler();

    expect($triggered)->toBeFalse();
});
