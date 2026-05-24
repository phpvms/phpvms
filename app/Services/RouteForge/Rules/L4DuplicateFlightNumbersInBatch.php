<?php

declare(strict_types=1);

namespace App\Services\RouteForge\Rules;

use App\Services\RouteForge\Contracts\LintRule;
use App\Services\RouteForge\LintContext;
use App\Services\RouteForge\LintIssue;

/**
 * L4 — Duplicate flight numbers within the submitted batch (ERROR).
 *
 * Strict duplicate key is `(airline_id, flight_number, route_code, route_leg)`.
 * Two rows in the same batch that share this tuple cannot both be persisted —
 * the unique constraint on the flights table would reject the second. This is
 * a commit-blocking error.
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

        foreach ($ctx->rows as $index => $row) {
            $key = $this->dupKey($row);

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

    /**
     * @param array<string, mixed> $row
     */
    private function dupKey(array $row): string
    {
        return implode('|', [
            (string) ($row['airline_id'] ?? ''),
            (string) ($row['flight_number'] ?? ''),
            // Normalize null/empty/0 to a single canonical token so the strict
            // key matches the FlightService duplicate semantics (NULL ≡ '' ≡ 0).
            $this->normalize($row['route_code'] ?? null),
            $this->normalize($row['route_leg'] ?? null),
        ]);
    }

    private function normalize(mixed $value): string
    {
        if (in_array($value, [null, '', 0, '0'], true)) {
            return '∅';
        }

        return (string) $value;
    }
}
