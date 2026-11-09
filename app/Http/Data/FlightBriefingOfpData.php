<?php

declare(strict_types=1);

namespace App\Http\Data;

use Spatie\LaravelData\Data;

final class FlightBriefingOfpData extends Data
{
    public function __construct(
        public readonly string $briefingId,
        public readonly string $briefingUrl,
        public readonly ?int $blockFuel,
        public readonly ?int $zeroFuelWeight,
        /** 'lb' or 'kg'. */
        public readonly string $weightUnit,
        public readonly ?int $airTimeMinutes,
        public readonly ?string $departureRunway,
        public readonly ?string $arrivalRunway,
        public readonly ?string $aircraftRegistration,
    ) {}
}
