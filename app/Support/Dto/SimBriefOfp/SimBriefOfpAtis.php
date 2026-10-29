<?php

declare(strict_types=1);

namespace App\Support\Dto\SimBriefOfp;

use App\Support\Casts\CarbonOrNullCast;
use App\Support\Casts\EmptyNodeAsStringCast;
use Carbon\Carbon;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Dto;

final class SimBriefOfpAtis extends Dto
{
    public function __construct(
        public string $network,
        #[WithCast(CarbonOrNullCast::class)]
        public ?Carbon $issued,
        #[WithCast(EmptyNodeAsStringCast::class)]
        public string $letter,
        #[WithCast(EmptyNodeAsStringCast::class)]
        public string $phonetic,
        #[WithCast(EmptyNodeAsStringCast::class)]
        public string $type,
        #[WithCast(EmptyNodeAsStringCast::class)]
        public string $message
    ) {}
}
