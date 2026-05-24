<?php

declare(strict_types=1);

namespace App\Services\RouteForge\Rules;

use App\Services\RouteForge\Contracts\LintRule;
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
    public function id(): string
    {
        return 'L6';
    }

    public function severity(): string
    {
        return LintIssue::SEVERITY_ERROR;
    }

    public function check(LintContext $ctx): array
    {
        $issues = [];

        foreach ($ctx->rows as $index => $row) {
            $dpt = $row['dpt_airport_id'] ?? null;
            $arr = $row['arr_airport_id'] ?? null;
            if ($dpt === null) {
                continue;
            }

            if ($arr === null) {
                continue;
            }

            // Normalize to string before comparison: payload values may arrive
            // as int 42 or string "42" depending on serialization. Strict !==
            // would skip a true self-loop in that mixed-type case.
            if ((string) $dpt !== (string) $arr) {
                continue;
            }

            $issues[] = new LintIssue(
                ruleId: $this->id(),
                severity: $this->severity(),
                message: __('filament.routeforge.lint.l6_origin_equals_destination', [
                    'airport' => $dpt,
                ]),
                rowIndex: $index,
                details: [
                    'airport' => $dpt,
                ],
            );
        }

        return $issues;
    }
}
