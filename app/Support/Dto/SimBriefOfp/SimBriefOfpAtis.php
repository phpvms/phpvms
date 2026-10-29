<?php

declare(strict_types=1);

namespace App\Support\Dto\SimBriefOfp;

use App\Support\Casts\CarbonOrNullCast;
use Carbon\Carbon;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Dto;

final class SimBriefOfpAtis extends Dto
{
    public function __construct(
        public string $network,
        #[WithCast(CarbonOrNullCast::class)]
        public ?Carbon $issued,
        public string $letter,
        public string $phonetic,
        public string $type,
        public string $message
    ) {}
}
