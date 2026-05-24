<?php

declare(strict_types=1);

namespace App\Services\RouteForge\Rules;

use App\Models\Event;
use App\Services\RouteForge\Contracts\LintRule;
use App\Services\RouteForge\LintContext;
use App\Services\RouteForge\LintIssue;
use Illuminate\Support\Carbon;

/**
 * L8 — Bundle window doesn't overlap event window (warning).
 *
 * Only runs when an event is selected. Compares the bundle's effective
 * schedule window against the event's date range using standard interval
 * overlap math:
 *
 *   overlap iff (bundleStart <= eventEnd) AND (eventStart <= bundleEnd)
 *
 * Partial bundle dates (only start or only end set) extend toward infinity
 * on the missing side. When the bundle has no dates at all, the warning is
 * suppressed — flights are open-ended recurring schedules and can plausibly
 * cover any event window.
 */
final class L8EventDatesOutsideWindow implements LintRule
{
    public function id(): string
    {
        return 'L8';
    }

    public function severity(): string
    {
        return LintIssue::SEVERITY_WARNING;
    }

    public function check(LintContext $ctx): array
    {
        $event = $ctx->event;
        if (!$event instanceof Event) {
            return [];
        }

        $bundleStart = $ctx->bundle->start_date;
        $bundleEnd = $ctx->bundle->end_date;

        // No effective window → can't compute overlap → skip per design.
        if ($bundleStart === null && $bundleEnd === null) {
            return [];
        }

        $eventStart = Carbon::instance($event->start_date);
        $eventEnd = Carbon::instance($event->end_date);

        // Treat missing bundle bounds as -∞ / +∞ for the overlap predicate.
        $startsBeforeEventEnds = $bundleStart === null || $bundleStart->lessThanOrEqualTo($eventEnd);
        $endsAfterEventStarts = $bundleEnd === null || $bundleEnd->greaterThanOrEqualTo($eventStart);

        if ($startsBeforeEventEnds && $endsAfterEventStarts) {
            return [];
        }

        return [
            new LintIssue(
                ruleId: $this->id(),
                severity: $this->severity(),
                message: __('filament.routeforge.lint.l8_event_dates_outside_window', [
                    'event' => $event->name,
                ]),
                rowIndex: null,
                details: [
                    'event_id'          => $event->id,
                    'event_start_date'  => $eventStart->toDateString(),
                    'event_end_date'    => $eventEnd->toDateString(),
                    'bundle_start_date' => $bundleStart?->toDateString(),
                    'bundle_end_date'   => $bundleEnd?->toDateString(),
                ],
            ),
        ];
    }
}
