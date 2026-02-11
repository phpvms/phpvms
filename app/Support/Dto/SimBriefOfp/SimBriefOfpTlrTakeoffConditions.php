<?php

namespace App\Support\Dto\SimBriefOfp;

use Spatie\LaravelData\Dto;

final class SimBriefOfpTlrTakeoffConditions extends Dto
{
    public function __construct(
        public string $airport_icao,
        public string $planned_runway,
        public int $planned_weight,
        public int $wind_direction,
        public int $wind_speed,
        public int $temperature,
        public float $altimeter,
        public string $surface_condition
    ) {}
}
