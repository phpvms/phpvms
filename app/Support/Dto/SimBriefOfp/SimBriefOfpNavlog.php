<?php

namespace App\Support\Dto\SimBriefOfp;

use Spatie\LaravelData\Dto;

final class SimBriefOfpNavlog extends Dto
{
    /**
     * @param SimBriefOfpWindData[]|null $wind_data
     */
    public function __construct(
        public string $ident,
        public string $name,
        public string $type,
        public string $icao_region,
        public string $region_code,
        public string $frequency,
        public float $pos_lat,
        public float $pos_long,
        public string $stage,
        public string $via_airway,
        public bool $is_sid_star,
        public int $distance,
        public int $track_true,
        public int $track_mag,
        public int $heading_true,
        public int $heading_mag,
        public int $altitude_feet,
        public int $ind_airspeed,
        public int $true_airspeed,
        public float $mach,
        public float $mach_thousandths,
        public int $wind_component,
        public int $groundspeed,
        public string $time_leg,
        public string $time_total,
        public int $fuel_flow,
        public int $fuel_leg,
        public int $fuel_totalused,
        public int $fuel_min_onboard,
        public int $fuel_plan_onboard,
        public int $oat,
        public int $oat_isa_dev,
        public int $wind_dir,
        public int $wind_spd,
        public int $shear,
        public int $tropopause_feet,
        public int $ground_height,
        public string $fir,
        public string $fir_units,
        public string $fir_valid_levels,
        public ?int $mora,
        public ?array $wind_data,
        public ?array $fir_crossing
    ) {}
}
