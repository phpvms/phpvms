<?php

namespace App\Support\Dto\SimBriefOfp;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Dto;

final class SimBriefOfpTracksNat extends Dto
{
    /**
     * @param SimBriefOfpTracksNatFix[] $fixes
     */
    public function __construct(
        public string $id,
        public string $group,
        public string $addr,
        public string $tmi,
        public string $route,
        public string $levels,
        #[Date]
        public CarbonImmutable $start,
        #[Date]
        public CarbonImmutable $end,
        public array $fixes,
    ) {}
}
