<?php

declare(strict_types=1);

namespace App\Services\RouteForge\Rules;

use App\Services\RouteForge\Contracts\LintRule;
use App\Services\RouteForge\LintContext;
use App\Services\RouteForge\LintIssue;
use App\Services\RouteForge\Support\StrictDuplicateKey;

/**
 * L4 — Duplicate flight numbers within the submitted batch (ERROR).
 *
 * Strict duplicate key is the 5-tuple
 * `(bundle_id, airline_id, flight_number, route_code, route_leg)` provided by
 * `StrictDuplicateKey`. Two rows in the same batch that share this tuple
 * cannot both be persisted — the DB UNIQUE index on `flights._dup_key` (and
 * the legacy strict-duplicate semantics on `(airline_id, flight_number,
 * route_code, route_leg)` for org-level flights) would reject the second.
 * This is a commit-blocking error.
 *
 * `bundle_id` is included in the key for correctness-by-construction. The
 * batch always commits to a single bundle today, so within one batch every
 * row shares the same `bundle_id` and the field is effectively constant;
 * including it in the key future-proofs against multi-bundle batches.
 *
 * Per the spec, the issue identifies BOTH row indices involved; the UI
 * highlights the conflicting pair. When N rows collide on the same key, we
 * emit one issue per collision pair beyond the first (so a 3-way collision
 * surfaces as two issues: rows 0+1, rows 0+2).
 */
final class L4DuplicateFlightNumbersInBatch implements LintRule
{
    public function id(): string
    {
        return 'L4';
    }

    public function severity(): string
    {
        return LintIssue::SEVERITY_ERROR;
    }

    public function check(LintContext $ctx): array
    {
        /** @var array<string, int> $seen Key → first-row-index encountered. */
        $seen = [];
        $issues = [];

        $bundleId = $ctx->bundle->id;

        foreach ($ctx->rows as $index => $row) {
            $key = (string) StrictDuplicateKey::forRow($row, $bundleId);

            if (!isset($seen[$key])) {
                $seen[$key] = $index;

                continue;
            }

            $firstIndex = $seen[$key];

            $issues[] = new LintIssue(
                ruleId: $this->id(),
                severity: $this->severity(),
                message: __('filament.routeforge.lint.l4_duplicate_in_batch', [
                    'flight_number' => $row['flight_number'] ?? '',
                    'first'         => $firstIndex,
                    'second'        => $index,
                ]),
                rowIndex: $index,
                details: [
                    'flight_number'       => $row['flight_number'] ?? null,
                    'airline_id'          => $row['airline_id'] ?? null,
                    'route_code'          => $row['route_code'] ?? null,
                    'route_leg'           => $row['route_leg'] ?? null,
                    'first_row_index'     => $firstIndex,
                    'duplicate_row_index' => $index,
                ],
            );
        }

        return $issues;
    }
}
