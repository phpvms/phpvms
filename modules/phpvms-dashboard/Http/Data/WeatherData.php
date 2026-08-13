<?php

declare(strict_types=1);

namespace Modules\PhpvmsDashboard\Http\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Typed success payload returned by this addon's weather endpoint.
 *
 * Extending Spatie\LaravelData\Data makes this class Responsable: return it from
 * a controller and it serializes to JSON automatically (see WeatherController).
 * The public typed properties ARE the success response shape — a lean mirror of
 * the core /api/weather/{icao} success payload, carrying only the fields the
 * widget renders (station id, raw METAR, and the three summarized rows).
 *
 * The #[TypeScript] attribute opts this class into `php artisan
 * typescript:transform`, which generates the matching TypeScript type into the
 * host SPA's generated.d.ts as
 * `Modules.PhpvmsDashboard.Http.Data.WeatherData`. The widget itself is a
 * standalone ESM module (built outside the SPA tsconfig scope) so it keeps a
 * hand-written mirror of this shape rather than importing the generated type.
 */
#[TypeScript]
final class WeatherData extends Data
{
    public function __construct(
        public string $icao,
        public ?string $metar,
        public ?string $conditions,
        public ?string $temperature,
        public ?string $wind,
    ) {}
}
