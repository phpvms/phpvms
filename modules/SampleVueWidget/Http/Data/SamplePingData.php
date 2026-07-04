<?php

declare(strict_types=1);

namespace Modules\SampleVueWidget\Http\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Typed payload returned by this addon's own endpoint.
 *
 * Extending Spatie\LaravelData\Data makes this class Responsable: return it from
 * a controller and it serializes to JSON automatically (see SamplePingController).
 * The public typed properties ARE the response shape. The #[TypeScript] attribute
 * opts this class into `php artisan typescript:transform`, which GENERATES the
 * matching TypeScript type into
 * resources/skylight/apps/spa/types/generated.d.ts — the Vue widget imports that
 * generated `SamplePingData` type instead of hand-mirroring the shape, keeping
 * the client and server contracts in lock-step.
 */
#[TypeScript]
final class SamplePingData extends Data
{
    public function __construct(
        public string $addon,
        public string $message,
        public string $time,
    ) {}
}
