<?php

declare(strict_types=1);

namespace App\Services\RouteForge\Rules;

use App\Enums\FlightType;
use App\Models\Subfleet;
use App\Services\RouteForge\Contracts\LintRule;
use App\Services\RouteForge\Enums\LintSeverity;
use App\Services\RouteForge\LintContext;
use App\Services\RouteForge\LintIssue;
use Illuminate\Support\Collection;

/**
 * L2b — Flight type vs subfleet route_types mismatch.
 *
 * Fires when the batch's single batch-wide flight_type is not contained in any
 * selected subfleet's `route_types` set. Subfleets with `route_types = NULL`
 * are treated as unrestricted (always compatible). The rule trigger is
 * batch-wide but the spec requires per-row attachment, so we emit one issue
 * per row when the check fails.
 */
final class L2bTypeMismatch implements LintRule
{
    public const string ID = 'L2b';

    public const LintSeverity SEVERITY = LintSeverity::Warning;

    public function check(LintContext $ctx): array
    {
        $flightType = $ctx->flightType;
        if (!$flightType instanceof FlightType) {
            return [];
        }

        if ($ctx->selectedSubfleets->isEmpty()) {
            return [];
        }

        $compatible = $ctx->selectedSubfleets->contains(
            fn (Subfleet $subfleet): bool => $this->subfleetCovers($subfleet, $flightType),
        );

        if ($compatible) {
            return [];
        }

        $issues = [];
        foreach ($ctx->rows as $row) {
            $issues[] = new LintIssue(
                ruleId: self::ID,
                severity: self::SEVERITY,
                message: __('filament.routeforge.lint.l2b_type_mismatch', [
                    'type' => $flightType->value,
                ]),
                rowIndex: $row->index,
                details: [
                    'flight_type' => $flightType->value,
                ],
            );
        }

        return $issues;
    }

    private function subfleetCovers(Subfleet $subfleet, FlightType $type): bool
    {
        /** @var Collection<int, FlightType>|null $routeTypes */
        $routeTypes = $subfleet->route_types;

        // NULL route_types = unrestricted = always compatible.
        if ($routeTypes === null) {
            return true;
        }

        return $routeTypes->contains(fn (FlightType $t): bool => $t === $type);
    }
}
