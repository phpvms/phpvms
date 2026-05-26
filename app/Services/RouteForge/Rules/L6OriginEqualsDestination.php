<?php

declare(strict_types=1);

namespace App\Services\RouteForge\Rules;

use App\Services\RouteForge\Contracts\LintRule;
use App\Services\RouteForge\Enums\LintSeverity;
use App\Services\RouteForge\LintContext;
use App\Services\RouteForge\LintIssue;

/**
 * L6 — Origin equals destination (ERROR).
 *
 * Self-loop rows make no operational sense and are an obvious user mistake.
 * The generator itself excludes these per the route-forge-tool spec, so this
 * rule's primary value is catching user-edited rows or hand-crafted commit
 * payloads. Commit-blocking error.
 */
final class L6OriginEqualsDestination implements LintRule
{
    public const string ID = 'L6';

    public const LintSeverity SEVERITY = LintSeverity::Error;

    public function check(LintContext $ctx): array
    {
        $issues = [];

        foreach ($ctx->rows as $row) {
            $dpt = $row->dptAirportId;
            $arr = $row->arrAirportId;
            if ($dpt === null) {
                continue;
            }

            if ($arr === null) {
                continue;
            }

            if ($dpt !== $arr) {
                continue;
            }

            $issues[] = new LintIssue(
                ruleId: self::ID,
                severity: self::SEVERITY,
                message: __('filament.routeforge.lint.l6_origin_equals_destination', [
                    'airport' => $dpt,
                ]),
                rowIndex: $row->index,
                details: [
                    'airport' => $dpt,
                ],
            );
        }

        return $issues;
    }
}
