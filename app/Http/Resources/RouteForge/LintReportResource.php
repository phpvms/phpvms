<?php

declare(strict_types=1);

namespace App\Http\Resources\RouteForge;

use App\Contracts\Resource;
use App\Services\RouteForge\LintReport;
use Illuminate\Http\Request;

/**
 * Wire shape returned by /admin/route-forge/api/lint AND by
 * /admin/route-forge/api/commit when LintFailedException is caught (422).
 *
 * Delegates to LintReport::toArray() so the envelope stays identical across
 * both endpoints; the client uses the same renderer for live-lint output and
 * blocked-commit error display.
 */
final class LintReportResource extends Resource
{
    #[\Override]
    public function toArray(Request $request): array
    {
        /** @var LintReport $report */
        $report = $this->resource;

        return $report->toArray();
    }
}
