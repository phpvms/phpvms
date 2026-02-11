<?php

namespace App\Support\Dto\SimBriefOfp;

use Spatie\LaravelData\Dto;

final class SimBriefOfpEtopsDivAirport extends Dto
{
    public function __construct(
        public string $icao_code,
        public int $track_true,
        public int $track_mag,
        public int $distance,
        public int $avg_wind_comp,
        public int $avg_temp_dev,
        public int $est_fob
    ) {}
}
