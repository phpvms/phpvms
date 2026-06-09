<?php

declare(strict_types=1);

use App\Addons\Support\OctaneReloader;

it('does not run when not under octane', function (): void {
    $ran = false;
    $reloader = new OctaneReloader(
        underOctane: fn (): bool => false,
        runner: function () use (&$ran): void {
            $ran = true;
        },
    );

    $reloader->reload();

    expect($ran)->toBeFalse();
});

it('runs the reload command when under octane', function (): void {
    $captured = null;
    $reloader = new OctaneReloader(
        underOctane: fn (): bool => true,
        runner: function (array $cmd) use (&$captured): void {
            $captured = $cmd;
        },
    );

    $reloader->reload();

    expect($captured)->toContain('octane:reload');
});

it('swallows runner failures', function (): void {
    $reloader = new OctaneReloader(
        underOctane: fn (): bool => true,
        runner: function (): void {
            throw new RuntimeException('boom');
        },
    );

    $reloader->reload();
})->throwsNoExceptions();
