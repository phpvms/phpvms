<?php

namespace App\Support\Dto\SimBriefOfp;

use Spatie\LaravelData\Dto;

final class SimBriefOfpEtopsEqualTimePoint extends Dto
{
    /**
     * @param SimBriefOfpEtopsDivAirport[] $div_airport
     */
    public function __construct(
        public float $pos_lat,
        public float $pos_long,
        public string $elapsed_time,
        public int $min_fob,
        public int $est_fob,
        public string $etops_condition,
        public string $div_time,
        public int $div_burn,
        public int $critical_fuel,
        public int $div_altitude,
        public array $div_airport,
    ) {}
}
