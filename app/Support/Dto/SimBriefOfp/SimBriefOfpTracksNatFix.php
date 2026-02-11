<?php

namespace App\Support\Dto\SimBriefOfp;

use Spatie\LaravelData\Dto;

final class SimBriefOfpTracksNatFix extends Dto
{
    public function __construct(
        public string $ident,
        public float $pos_lat,
        public float $pos_long
    ) {}
}
