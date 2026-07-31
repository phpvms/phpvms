<?php

namespace App\Support\Dto\SimBriefOfp;

use Spatie\LaravelData\Dto;

final class SimBriefOfpTlr extends Dto
{
    public function __construct(
        public ?SimBriefOfpTlrTakeoff $takeoff,
        public ?SimBriefOfpTlrLanding $landing,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            takeoff: filled($data['takeoff'] ?? null) ? SimBriefOfpTlrTakeoff::from($data['takeoff']) : null,
            landing: filled($data['landing'] ?? null) ? SimBriefOfpTlrLanding::from($data['landing']) : null,
        );
    }
}
