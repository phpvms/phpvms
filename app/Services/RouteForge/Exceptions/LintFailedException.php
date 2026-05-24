<?php

declare(strict_types=1);

namespace App\Services\RouteForge\Exceptions;

use App\Services\RouteForge\LintReport;
use RuntimeException;

/**
 * Thrown by RouteForgeService::commit() when the in-transaction lint re-run
 * reports any error-severity issues.
 *
 * Carries the full LintReport so the /admin/route-forge/api/commit controller
 * can render it as the 422 response body (using LintReport::toArray() to keep
 * the wire shape identical to the /lint endpoint). Warnings alone do NOT
 * raise this — only the canProceed() gate (errors empty) does.
 */
final class LintFailedException extends RuntimeException
{
    public function __construct(public readonly LintReport $report)
    {
        parent::__construct('RouteForge commit blocked by lint errors.');
    }
}
