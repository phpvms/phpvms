<?php

declare(strict_types=1);

namespace App\Http\Data;

use App\Enums\BriefingItemKind;
use App\Enums\BriefingSeverity;
use App\Enums\BriefingStationRole;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

/**
 * One item of the flight briefing timeline. `offsetMinutes` is its offset in
 * minutes from departure (0) and places it on the timeline; items with the
 * same offset stack. `anchor` is the ICAO for station rows and the fix ident
 * for fix and phase rows.
 */
final class BriefingTimelineItemData extends Data
{
    public function __construct(
        public readonly BriefingItemKind $kind,
        public readonly ?string $anchor,
        public readonly string $title,
        public readonly ?string $detail = null,
        public readonly ?string $icon = null,
        public readonly ?int $offsetMinutes = null,
        public readonly ?int $altitudeFt = null,
        public readonly ?string $viaAirway = null,
        public readonly ?CarbonImmutable $at = null,
        public readonly BriefingSeverity $severity = BriefingSeverity::Info,
        public readonly string $source = 'core',
        public readonly ?BriefingStationRole $role = null,
    ) {}
}
