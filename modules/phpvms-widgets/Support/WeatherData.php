<?php

declare(strict_types=1);

namespace Modules\PhpvmsWidgets\Support;

/**
 * The weather tile's summary of one station's METAR.
 */
final readonly class WeatherData
{
    public function __construct(
        public string $icao,
        public ?string $metar,
        public ?string $conditions,
        public ?string $temperature,
        public ?string $wind,
    ) {}
}
