<?php

declare(strict_types=1);

use App\Http\Middleware\DisableActivityLoggingByDefault;
use App\Http\Middleware\EnableActivityLogging;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Spatie\Activitylog\ActivityLogStatus;

/**
 * Octane keeps the framework booted across requests on the same worker.
 * The DisableActivityLoggingByDefault middleware exists to reset logging
 * to "off" at the start of every request, so an earlier request's
 * EnableActivityLogging call does not leak into the next request.
 *
 * @group octane
 */
pest()->group('octane');

test('disable middleware turns activity logging off at request start', function (): void {
    activity()->enableLogging();

    expect(app(ActivityLogStatus::class)->disabled())->toBeFalse();

    (new DisableActivityLoggingByDefault())->handle(Request::create('/'), fn (): Response => new Response());

    expect(app(ActivityLogStatus::class)->disabled())->toBeTrue();
});

test('logging from request A does not leak into request B on the same worker', function (): void {
    $disable = new DisableActivityLoggingByDefault();
    $enable = new EnableActivityLogging();

    // Request A — enabled via EnableActivityLogging.
    $disable->handle(Request::create('/admin'), fn (): Response => $enable->handle(Request::create('/admin'), function (): Response {
        expect(app(ActivityLogStatus::class)->disabled())->toBeFalse();

        return new Response();
    }));

    // Request B — same booted application, no EnableActivityLogging this time.
    $disable->handle(Request::create('/'), function (): Response {
        expect(app(ActivityLogStatus::class)->disabled())->toBeTrue();

        return new Response();
    });
});
