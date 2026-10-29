<?php

declare(strict_types=1);

use Illuminate\Foundation\Bootstrap\HandleExceptions;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;

/*
 * Laravel only defines the `deprecations` channel when the first deprecation
 * fires, and under Octane it writes that onto the worker's original config
 * rather than the request's copy. The request then fails with "Log
 * [deprecations] is not defined" on any deprecated call (in production:
 * public_asset() in the admin panel switcher). The channel must exist from boot.
 */

it('defines the deprecations channel before any deprecation fires', function (): void {
    expect(config('logging.channels.deprecations.driver'))->toBe('stack')
        ->and(config('logging.channels.deprecations.channels'))->toBeArray()->not->toBeEmpty();

    expect(Log::channel('deprecations'))->toBeInstanceOf(LoggerInterface::class);
});

it('logs a deprecation without throwing', function (): void {
    Log::channel('deprecations')->warning('probe');

    // Same entry point the framework's HandleExceptions uses.
    app(HandleExceptions::class)
        ->handleError(E_USER_DEPRECATED, 'probe deprecation', __FILE__, __LINE__);
})->throwsNoExceptions();
