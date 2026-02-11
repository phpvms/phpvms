<?php

namespace App\Support\Dto\SimBriefOfp;

use Spatie\LaravelData\Dto;

final class SimBriefOfpTracks extends Dto
{
    /**
     * @param SimbriefOfpTracksNat[] $nat
     */
    public function __construct(
        public SimbriefOfpTracksNatNotams $nat_notams,
        public array $nat
    ) {}
}
