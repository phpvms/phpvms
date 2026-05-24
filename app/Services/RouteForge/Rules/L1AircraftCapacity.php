<?php

declare(strict_types=1);

namespace App\Services\RouteForge\Rules;

use App\Models\Subfleet;
use App\Services\RouteForge\Contracts\LintRule;
use App\Services\RouteForge\LintContext;
use App\Services\RouteForge\LintIssue;

/**
 * L1 — Aircraft capacity warning.
 *
 * Fires when the selected subfleets collectively carry fewer than half the
 * active aircraft needed for the batch:
 *
 *   selected_aircraft_count < (row_count / 2)
 *
 * Subfleet::aircraft() is already filtered to AircraftStatus::ACTIVE in the
 * model, so `aircraft_count` reads as the active-only count without an extra
 * query. Batch-wide finding (rowIndex = null).
 */
final class L1AircraftCapacity implements LintRule
{
    public function id(): string
    {
        return 'L1';
    }

    public function severity(): string
    {
        return LintIssue::SEVERITY_WARNING;
    }

    public function check(LintContext $ctx): array
    {
        $rowCount = $ctx->rowCount();
        if ($rowCount === 0) {
            return [];
        }

        $selectedAircraftCount = $ctx->selectedSubfleets->sum(
            fn (Subfleet $subfleet): int => (int) ($subfleet->aircraft_count ?? $subfleet->aircraft->count()),
        );

        // Half-ratio threshold, rounded up: a 3-row batch with 1 aircraft is
        // genuinely under-capacity but intdiv(3, 2) = 1 would silently pass.
        $threshold = intdiv($rowCount + 1, 2);

        if ($selectedAircraftCount >= $threshold) {
            return [];
        }

        return [
            new LintIssue(
                ruleId: $this->id(),
                severity: $this->severity(),
                message: __('filament.routeforge.lint.l1_capacity', [
                    'selected' => $selectedAircraftCount,
                    'needed'   => $threshold,
                    'rows'     => $rowCount,
                ]),
                rowIndex: null,
                details: [
                    'selected_aircraft_count' => $selectedAircraftCount,
                    'row_count'               => $rowCount,
                    'threshold'               => $threshold,
                ],
            ),
        ];
    }
}
