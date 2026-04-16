<?php

namespace App\Support\Dto\SimBriefOfp;

use Carbon\Carbon;
use Spatie\LaravelData\Dto;

final class SimBriefOfpAtis extends Dto
{
    public function __construct(
        public string $network,
        public Carbon $issued,
        public string $letter,
        public string $phonetic,
        public string $type,
        public string $message
    ) {}
}
