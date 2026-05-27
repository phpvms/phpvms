<?php

declare(strict_types=1);

namespace App\Services\RouteForge\Rules;

use App\Services\RouteForge\Contracts\LintRule;
use App\Services\RouteForge\Enums\LintSeverity;
use App\Services\RouteForge\LintContext;
use App\Services\RouteForge\LintIssue;

/**
 * L11 — Per-row airport timezone missing (warning).
 *
 * Fires when the row's origin OR destination airport has `timezone = NULL`.
 * The presence of this warning signals that the arrival time was computed
 * with a "no shift" naïve fallback (origin-local + block time, no DST or
 * timezone-offset adjustment) — the arrival_time on the row is informational,
 * not accurate.
 *
 * The row payload carries `dpt_timezone` / `arr_timezone` (populated by the
 * preview-airports endpoint) so this rule does not touch the database.
 */
final class L11AirportTimezoneMissing implements LintRule
{
    public const string ID = 'L11';

    public const LintSeverity SEVERITY = LintSeverity::Warning;

    public function check(LintContext $ctx): array
    {
        $issues = [];

        foreach ($ctx->rows as $row) {
            $dptTz = $row->dptTimezone;
            $arrTz = $row->arrTimezone;

            if ($dptTz !== null && $arrTz !== null) {
                continue;
            }

            $missing = [];
            if ($dptTz === null) {
                $missing[] = $row->dptAirportId ?? 'origin';
            }

            if ($arrTz === null) {
                $missing[] = $row->arrAirportId ?? 'destination';
            }

            $issues[] = new LintIssue(
                ruleId: self::ID,
                severity: self::SEVERITY,
                message: __('filament.routeforge.lint.l11_timezone_missing', [
                    'airports' => implode(', ', $missing),
                ]),
                rowIndex: $row->index,
                details: [
                    'missing_timezone_airports' => $missing,
                ],
            );
        }

        return $issues;
    }
}
