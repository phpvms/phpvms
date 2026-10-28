<?php

declare(strict_types=1);

namespace App\Support\Dto\SimBriefOfp;

use App\Support\Casts\EmptyNodeAsStringCast;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Dto;

final class SimBriefOfpTlrTakeoffRunway extends Dto
{
    public function __construct(
        public string $identifier,
        public int $length,
        public int $length_tora,
        public int $length_toda,
        public int $length_asda,
        public int $length_lda,
        public int $elevation,
        public float $gradient,
        public int $true_course,
        public int $magnetic_course,
        public int $headwind_component,
        public int $crosswind_component,
        public float|string $ils_frequency,
        public int|string $flap_setting,
        public string $thrust_setting,
        public string $bleed_setting,
        public string $anti_ice_setting,
        public int|string $flex_temperature,
        public int|string $max_temperature,
        public int|string $max_weight,
        public string $limit_code,
        public array $limit_obstacle,
        public int|string $speeds_v1,
        public int|string $speeds_vr,
        public int|string $speeds_v2,
        public int|string $speeds_v2_id,
        // An airframe with no speed beyond V1/VR/V2 (a Phenom 300) gets [].
        #[WithCast(EmptyNodeAsStringCast::class)]
        public int|string $speeds_other,
        #[WithCast(EmptyNodeAsStringCast::class)]
        public int|string $speeds_other_id,
        public int $distance_decide,
        public int $distance_reject,
        public int $distance_margin,
        public int $distance_continue
    ) {}
}
