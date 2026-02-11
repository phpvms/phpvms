<?php

namespace App\Support\Dto\SimBriefOfp;

use Spatie\LaravelData\Dto;

final class SimbriefOfpTracksNatNotams extends Dto
{
    public function __construct(
        public string $eggx,
        public string $czqx
    ) {}
}
