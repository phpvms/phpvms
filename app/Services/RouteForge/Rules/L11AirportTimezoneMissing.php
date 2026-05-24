<?php

declare(strict_types=1);

namespace App\Services\RouteForge\Rules;

use App\Services\RouteForge\Contracts\LintRule;
use App\Services\RouteForge\LintContext;
use App\Services\RouteForge\LintIssue;

/**
 * L11 — Per-row airport timezone missing (warning).
 *
 * Fires when the row's origin OR destination airport has `timezone = NULL`.
 * The presence of this warning signals that the arrival time was computed
 * with a "no shift" naïve fallback (origin-local + block time, no DST or
 * timezone-offset adjustment) — the arr_time on the row is informational,
 * not accurate.
 *
 * The row payload carries `dpt_timezone` / `arr_timezone` (populated by the
 * preview-airports endpoint) so this rule does not touch the database.
 */
final class L11AirportTimezoneMissing implements LintRule
{
    public function id(): string
    {
        return 'L11';
    }

    public function severity(): string
    {
        return LintIssue::SEVERITY_WARNING;
    }

    public function check(LintContext $ctx): array
    {
        $issues = [];

        foreach ($ctx->rows as $index => $row) {
            $dptTz = $row['dpt_timezone'] ?? null;
            $arrTz = $row['arr_timezone'] ?? null;

            if ($dptTz !== null && $arrTz !== null) {
                continue;
            }

            $missing = [];
            if ($dptTz === null) {
                $missing[] = $row['dpt_airport_id'] ?? 'origin';
            }

            if ($arrTz === null) {
                $missing[] = $row['arr_airport_id'] ?? 'destination';
            }

            $issues[] = new LintIssue(
                ruleId: $this->id(),
                severity: $this->severity(),
                message: __('filament.routeforge.lint.l11_airport_timezone_missing', [
                    'airports' => implode(', ', $missing),
                ]),
                rowIndex: $index,
                details: [
                    'missing_timezone_airports' => $missing,
                ],
            );
        }

        return $issues;
    }
}
