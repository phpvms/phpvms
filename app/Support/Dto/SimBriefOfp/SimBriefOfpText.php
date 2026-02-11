<?php

namespace App\Support\Dto\SimBriefOfp;

use Spatie\LaravelData\Dto;

final class SimBriefOfpText extends Dto
{
    public function __construct(
        public string $nat_tracks,
        public string $tlr_section,
        public string $plan_html
    ) {}
}
