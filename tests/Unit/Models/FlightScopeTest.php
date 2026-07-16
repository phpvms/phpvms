<?php

declare(strict_types=1);

use App\Models\Flight;

it('active scope produces same SQL as visible scope', function (): void {
    $activeSql = Flight::active()->toSql();
    $visibleSql = Flight::visible()->toSql();

    expect($activeSql)->toBe($visibleSql);
});

it('does not mistake the active scope for a relationship when read as an attribute', function (): void {
    $flight = new Flight();

    // `flights.active` was renamed to `enabled`, so there is no attribute to
    // read. Eloquent must not fall through to `getRelationValue()` and invoke
    // the scope method with zero arguments.
    expect($flight->isRelation('active'))->toBeFalse()
        ->and($flight->active)->toBeNull();
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
