<?php

declare(strict_types=1);

namespace App\Services\RouteForge\Rules;

use App\Services\RouteForge\Contracts\LintRule;
use App\Services\RouteForge\Enums\LintSeverity;
use App\Services\RouteForge\LintContext;
use App\Services\RouteForge\LintIssue;

/**
 * L10 — Batch size over hard cap (ERROR).
 *
 * Threshold sourced from `config('phpvms.routeforge.mesh_max_count')`
 * (default 100). Fires once batch-wide when the submitted row count strictly
 * exceeds the cap. Commit-blocking — both client (Create button disabled) and
 * server (commit 422) enforce.
 */
final class L10BatchOver100 implements LintRule
{
    public const string ID = 'L10';

    public const LintSeverity SEVERITY = LintSeverity::Error;

    public function check(LintContext $ctx): array
    {
        $cap = (int) config('phpvms.routeforge.mesh_max_count', 100);
        $count = $ctx->rowCount();

        if ($count <= $cap) {
            return [];
        }

        return [
            new LintIssue(
                ruleId: self::ID,
                severity: self::SEVERITY,
                message: __('filament.routeforge.lint.l10_batch_over_hard_cap', [
                    'count' => $count,
                    'cap'   => $cap,
                ]),
                rowIndex: null,
                details: [
                    'row_count' => $count,
                    'cap'       => $cap,
                ],
            ),
        ];
    }
}
