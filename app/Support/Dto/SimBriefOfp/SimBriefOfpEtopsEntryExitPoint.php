<?php

namespace App\Support\Dto\SimBriefOfp;

use Spatie\LaravelData\Dto;

final class SimBriefOfpEtopsEntryExitPoint extends Dto
{
    public function __construct(
        public string $icao_code,
        public string $iata_code,
        public string $faa_code,
        public string $icao_region,
        public float $pos_lat_apt,
        public float $pos_long_apt,
        public float $pos_lat_fix,
        public float $pos_long_fix,
        public string $elapsed_time,
        public int $min_fob,
        public int $est_fob,
        public string $etops_condition,
        public string $div_time,
        public int $div_burn,
        public int $critical_fuel,
        public int $div_altitude,
        public SimBriefOfpEtopsDivAirport $div_airport,
    ) {}
}
