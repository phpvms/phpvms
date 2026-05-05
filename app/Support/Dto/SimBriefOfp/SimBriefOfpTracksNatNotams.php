<?php

declare(strict_types=1);

namespace App\Support\Dto\SimBriefOfp;

use Spatie\LaravelData\Dto;

final class SimBriefOfpTracksNatNotams extends Dto
{
    public function __construct(
        public string $eggx,
        public string $czqx
    ) {}
}
