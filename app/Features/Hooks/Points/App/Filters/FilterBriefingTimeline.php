<?php

declare(strict_types=1);

namespace App\Features\Hooks\Points\App\Filters;

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
 * it gets back, placed by `offsetMinutes`, the offset in minutes from
 * departure (0): set it to 42 to show an item at +0:42. Items with the same
 * offset stack, in list order. Items without one follow the timed items, in
 * list order. A result that is not a list of
 * `BriefingTimelineItemData` is dropped and the previous list stays.
 *
 * Items are immutable: build a new item to change one. A station row from an
 * addon without a `role` is a plain station.
 *
 * `$briefing` is the pilot's own OFP for this flight, or null before one exists.
 */
final class FilterBriefingTimeline extends FilterHook
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
