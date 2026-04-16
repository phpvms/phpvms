<?php

namespace App\Support\Dto\SimBriefOfp;

use App\Support\Casts\CarbonImmutableOrFalseCast;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Dto;

final class SimBriefOfpEtopsSuitableAirport extends Dto
{
    /**
     * @param SimBriefOfpAtis[]  $atis
     * @param SimBriefOfpNotam[] $notam
     */
    public function __construct(
        public string $icao_code,
        public string $iata_code,
        public string $faa_code,
        public string $icao_region,
        public string $name,
        public float $pos_lat,
        public float $pos_long,
        public int $elevation,
        public float $timezone,
        public int $fcst_cig,
        public int $fcst_vis,
        public string $plan_rwy,
        public int $trans_alt,
        public int $trans_level,
        #[Date]
        public CarbonImmutable $suitability_start,
        #[Date]
        public CarbonImmutable $suitability_end,
        public string $metar,
        #[WithCast(CarbonImmutableOrFalseCast::class)]
        public false|CarbonImmutable $metar_time,
        public string $metar_category,
        public int $metar_visibility,
        public int $metar_ceiling,
        public string $taf,
        #[WithCast(CarbonImmutableOrFalseCast::class)]
        public false|CarbonImmutable $taf_time,
        public array $atis,
        public array $notam
    ) {}
}
