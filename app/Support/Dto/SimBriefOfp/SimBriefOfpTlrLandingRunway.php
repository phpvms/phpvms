<?php

declare(strict_types=1);

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
        // SimBrief sends '' for a runway it has no landing performance figure
        // for — a short strip the aircraft cannot use. Same reason
        // `ils_frequency` above and `max_weight` on the takeoff runway are
        // widened: an empty string cannot coerce to int and blows up
        // hydration for the whole OFP.
        public int|string $max_weight_dry,
        public int|string $max_weight_wet
    ) {}
}
