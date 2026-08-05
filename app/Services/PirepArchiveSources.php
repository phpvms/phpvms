<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Aircraft;
use App\Models\Flight;
use App\Models\Subfleet;

/**
 * Preloaded Flight/Aircraft/Subfleet rows for a batch of PIREPs, keyed by
 * id, so PirepArchiveService::build() can skip its per-pirep lookups when
 * a caller (e.g. the backfill job) already fetched them for the whole chunk.
 */
final readonly class PirepArchiveSources
{
    /**
     * @param array<int|string, Flight> $flights
     * @param array<int, Aircraft>      $aircraft
     * @param array<int, Subfleet>      $subfleets
     */
    public function __construct(
        public array $flights = [],
        public array $aircraft = [],
        public array $subfleets = [],
    ) {}
}
