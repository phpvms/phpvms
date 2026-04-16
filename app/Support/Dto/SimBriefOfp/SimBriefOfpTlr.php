<?php

namespace App\Support\Dto\SimBriefOfp;

use Spatie\LaravelData\Dto;

final class SimBriefOfpTlr extends Dto
{
    public function __construct(
        public SimBriefOfpTlrTakeoff $takeoff,
        public SimBriefOfpTlrLanding $landing,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            takeoff: SimBriefOfpTlrTakeoff::from($data['takeoff'] ?? []),
            landing: SimBriefOfpTlrLanding::from($data['landing'] ?? []),
        );
    }
}
