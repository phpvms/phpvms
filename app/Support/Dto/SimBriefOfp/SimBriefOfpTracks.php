<?php

namespace App\Support\Dto\SimBriefOfp;

use Spatie\LaravelData\Dto;

final class SimBriefOfpTracks extends Dto
{
    /**
     * @param SimBriefOfpTracksNat[] $nat
     */
    public function __construct(
        public SimBriefOfpTracksNatNotams $nat_notams,
        public array $nat
    ) {}
}
