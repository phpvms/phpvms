<?php

namespace App\Support\Dto\SimBriefOfp;

use Spatie\LaravelData\Dto;

final class SimBriefOfpGeneral extends Dto
{
    /**
     * @param string[] $dx_rmk
     * @param string[] $sys_rmk
     */
    public function __construct(
        public int $release,
        public string $icao_airline,
        public string $flight_number,
        public bool $is_etops,
        public array $dx_rmk,
        public array $sys_rmk,
        public bool $is_detailed_profile,
        public string $cruise_profile,
        public string $climb_profile,
        public string $descent_profile,
        public string $alternate_profile,
        public string $reserve_profile,
        public int $costindex,
        public string $cont_rule,
        public int $initial_altitude,
        public string $stepclimb_string,
        public int $avg_temp_dev,
        public int $avg_tropopause,
        public int $avg_wind_comp,
        public int $avg_wind_dir,
        public int $avg_wind_spd,
        public int $gc_distance,
        public int $route_distance,
        public int $air_distance,
        public int $total_burn,
        public int $cruise_tas,
        public float $cruise_mach,
        public int $passengers,
        public string $route,
        public string $route_ifps,
        public string $route_navigraph,
        public string $sid_ident,
        public string $sid_trans,
        public string $star_ident,
        public string $star_trans
    ) {}
}
