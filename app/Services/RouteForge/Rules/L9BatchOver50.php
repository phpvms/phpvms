<?php

declare(strict_types=1);

namespace App\Services\RouteForge\Rules;

use App\Services\RouteForge\Contracts\LintRule;
use App\Services\RouteForge\LintContext;
use App\Services\RouteForge\LintIssue;

/**
 * L9 — Batch size over soft threshold (warning).
 *
 * Threshold sourced from `config('phpvms.routeforge.mesh_warn_count')`
 * (default 50). Fires once batch-wide when the submitted row count strictly
 * exceeds the threshold. The companion L10 rule handles the hard cap.
 */
final class L9BatchOver50 implements LintRule
{
    public function id(): string
    {
        return 'L9';
    }

    public function severity(): string
    {
        return LintIssue::SEVERITY_WARNING;
    }

    public function check(LintContext $ctx): array
    {
        $threshold = (int) config('phpvms.routeforge.mesh_warn_count', 50);
        $count = $ctx->rowCount();

        if ($count <= $threshold) {
            return [];
        }

        return [
            new LintIssue(
                ruleId: $this->id(),
                severity: $this->severity(),
                message: __('filament.routeforge.lint.l9_batch_over_soft_cap', [
                    'count'     => $count,
                    'threshold' => $threshold,
                ]),
                rowIndex: null,
                details: [
                    'row_count' => $count,
                    'threshold' => $threshold,
                ],
            ),
        ];
    }
}
