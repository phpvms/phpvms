<?php

namespace App\Support\Dto\SimBriefOfp;

use Spatie\LaravelData\Dto;

final class SimBriefOfpAtc extends Dto
{
    /**
     * @param string[] $fir_altn
     * @param string[] $fir_etops
     * @param string[] $fir_enroute
     */
    public function __construct(
        public string $flightplan_text,
        public string $route,
        public string $route_ifps,
        public string $callsign,
        public string $flight_type,
        public string $flight_rules,
        public string $initial_spd,
        public string $initial_spd_unit,
        public string $initial_alt,
        public string $initial_alt_unit,
        public string $section18,
        public string $fir_orig,
        public string $fir_dest,
        public array $fir_altn,
        public array $fir_etops,
        public array $fir_enroute
    ) {}
}
