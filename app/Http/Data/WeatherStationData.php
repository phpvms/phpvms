<?php

declare(strict_types=1);

namespace App\Http\Data;

use App\Models\Airport;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class WeatherStationData extends Data
{
    public function __construct(
        public string $icao,
        public ?string $timezone,
    ) {}

    public static function fromModel(?Airport $airport): ?self
    {
        return $airport ? new self(
            icao: $airport->icao,
            timezone: $airport->timezone,
        ) : null;
    }
}
