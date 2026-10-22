<?php

declare(strict_types=1);

namespace App\Http\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** The flown portion of a PIREP's route. Empty before the flight has flown (briefing case). */
#[TypeScript]
final class MapFlownData extends Data
{
    /**
     * @param list<MapTrackPointData> $points
     */
    public function __construct(
        public array $points,
    ) {}
}
