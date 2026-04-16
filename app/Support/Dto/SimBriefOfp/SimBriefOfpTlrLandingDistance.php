<?php

namespace App\Support\Dto\SimBriefOfp;

use Spatie\LaravelData\Dto;

final class SimBriefOfpTlrLandingDistance extends Dto
{
    public function __construct(
        public int $weight,
        public string $flap_setting,
        public string $brake_setting,
        public string $reverser_credit,
        public int $speeds_vref,
        public int $actual_distance,
        public int $factored_distance
    ) {}
}
