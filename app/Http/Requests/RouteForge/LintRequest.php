<?php

declare(strict_types=1);

namespace App\Http\Requests\RouteForge;

/**
 * JSON body validation for /admin/route-forge/api/lint.
 *
 * Uses the shared BaseRouteForgeBatchRequest rules unchanged — lint runs on
 * the same envelope as commit; the only commit-specific fields
 * (fare_multiplier, on_conflict) are not relevant to lint and are not
 * accepted here.
 */
final class LintRequest extends BaseRouteForgeBatchRequest {}
