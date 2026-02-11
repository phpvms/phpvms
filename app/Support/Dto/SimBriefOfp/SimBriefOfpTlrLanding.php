<?php

namespace App\Support\Dto\SimBriefOfp;

use Spatie\LaravelData\Dto;

final class SimBriefOfpTlrLanding extends Dto
{
    /**
     * @param SimBriefOfpTlrLandingRunway[] $runway
     */
    public function __construct(
        public SimBriefOfpTlrLandingConditions $conditions,
        public SimBriefOfpTlrLandingDistance $distance_dry,
        public SimBriefOfpTlrLandingDistance $distance_wet,
        public array $runway,
    ) {}
}
