<?php

namespace App\Support\Dto\SimBriefOfp;

use Spatie\LaravelData\Dto;

final class SimBriefOfpTlrLandingRunway extends Dto
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
        public int $max_weight_dry,
        public int $max_weight_wet
    ) {}
}
