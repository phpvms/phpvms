<?php

declare(strict_types=1);

namespace App\Features\Hooks\Points;

use App\Features\Hooks\FilterHook;
use App\Http\Data\BriefingTimelineItemData;
use App\Models\Flight;
use App\Models\SimBrief;
use App\Models\User;

/**
 * Filter over the timeline of a pilot's flight briefing.
 *
 * A listener receives `$hook->value`, the current list of
 * `BriefingTimelineItemData`, and returns the new list. Core renders every item
 * it gets back. A notice attaches under the first non-notice row with the same
 * `anchor`; a notice with no match goes under the departure station. Non-notice
 * rows keep the order of the list. A result that is not a list of
 * `BriefingTimelineItemData` is dropped and the previous list stays.
 *
 * Items are immutable: build a new item to change one. A station row from an
 * addon without a `role` is a plain station.
 *
 * `$briefing` is the pilot's own OFP for this flight, or null before one exists.
 */
final class BuildingFlightBriefing extends FilterHook
{
    /**
     * @param list<BriefingTimelineItemData> $items
     */
    public function __construct(
        public readonly Flight $flight,
        public readonly User $user,
        public readonly ?SimBrief $briefing,
        array $items,
    ) {
        parent::__construct($items);
    }

    public static function name(): string
    {
        return 'flight.briefing.timeline';
    }

    public function accepts(mixed $value): bool
    {
        if (!is_array($value) || !array_is_list($value)) {
            return false;
        }

        return array_all($value, fn ($item): bool => $item instanceof BriefingTimelineItemData);
    }
}
