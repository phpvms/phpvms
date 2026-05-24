<?php

declare(strict_types=1);

namespace App\Services\RouteForge;

/**
 * Outcome of a successful RouteForge commit.
 *
 * Wire shape mirrors the JSON envelope returned by
 * /admin/route-forge/api/commit and consumed by the client's redirect-on-
 * success path. New optional fields go through additions here; renaming or
 * removing existing ones is a breaking change for the TS client.
 *
 * `skipped` is reserved for the v1 `on_conflict: 'skip'` path; the current
 * service implementation always inserts, so it is always an empty array. Kept
 * in the shape so the endpoint contract stabilizes ahead of skip-mode work.
 */
final readonly class CommitResult
{
    /**
     * @param int                              $bundleId     Persisted flight_bundles row id.
     * @param string                           $batchId      ULID stamped into the single bundle-level activity log entry.
     * @param int                              $createdCount Number of flights actually inserted (always equals count($flightIds) in v1).
     * @param list<string>                     $flightIds    Hash ids of created flights in submitted-row order.
     * @param array<int, array<string, mixed>> $skipped      Rows skipped due to conflict (reserved; always [] in v1).
     */
    public function __construct(
        public int $bundleId,
        public string $batchId,
        public int $createdCount,
        public array $flightIds,
        public array $skipped = [],
    ) {}

    /**
     * @return array{bundle_id: int, batch_id: string, created_count: int, flight_ids: list<string>, skipped: array<int, array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'bundle_id'     => $this->bundleId,
            'batch_id'      => $this->batchId,
            'created_count' => $this->createdCount,
            'flight_ids'    => $this->flightIds,
            'skipped'       => $this->skipped,
        ];
    }
}
