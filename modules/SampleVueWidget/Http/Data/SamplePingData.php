<?php

declare(strict_types=1);

namespace Modules\SampleVueWidget\Http\Data;

use Spatie\LaravelData\Data;

/**
 * Typed payload returned by this addon's own endpoint.
 *
 * Extending Spatie\LaravelData\Data makes this class Responsable: return it from
 * a controller and it serializes to JSON automatically (see SamplePingController).
 * The public typed properties ARE the response shape — the Vue widget's
 * `PingSuccess` interface mirrors them by hand here, but a real addon can
 * GENERATE the matching TypeScript type from this class with
 * spatie/typescript-transformer (`php artisan typescript:transform`), keeping the
 * client and server contracts in lock-step.
 */
final class SamplePingData extends Data
{
    public function __construct(
        public string $addon,
        public string $message,
        public string $time,
    ) {}
}
