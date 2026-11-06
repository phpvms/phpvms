<?php

declare(strict_types=1);

namespace App\Http\Data;

use Spatie\LaravelData\Data;

final class FlightBriefingData extends Data
{
    /**
     * @param list<BriefingTimelineItemData> $timeline
     * @param list<MapPlannedFixData>        $plannedFixes
     */
    public function __construct(
        public readonly array $timeline,
        public readonly array $plannedFixes,
        public readonly ?FlightBriefingOfpData $ofp,
    ) {}
}
