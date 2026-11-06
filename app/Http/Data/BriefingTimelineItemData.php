<?php

declare(strict_types=1);

namespace App\Http\Data;

use App\Enums\BriefingItemKind;
use App\Enums\BriefingSeverity;
use App\Enums\BriefingStationRole;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

/**
 * One row of the flight briefing timeline. `anchor` is the ICAO for station
 * rows, the fix ident for fix and phase rows, and for notices the anchor of
 * the row they belong under.
 */
final class BriefingTimelineItemData extends Data
{
    public function __construct(
        public readonly BriefingItemKind $kind,
        public readonly ?string $anchor,
        public readonly string $title,
        public readonly ?string $detail = null,
        public readonly ?string $icon = null,
        public readonly ?int $elapsedMinutes = null,
        public readonly ?int $altitudeFt = null,
        public readonly ?string $viaAirway = null,
        public readonly ?CarbonImmutable $at = null,
        public readonly BriefingSeverity $severity = BriefingSeverity::Info,
        public readonly string $source = 'core',
        public readonly ?BriefingStationRole $role = null,
    ) {}
}
