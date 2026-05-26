<?php

declare(strict_types=1);

namespace App\Services\RouteForge\Enums;

/**
 * Severity classification for a single RouteForge lint finding.
 *
 * Backed by the wire string consumed by the SPA: `LintIssue::toArray()` emits
 * `$severity->value` so `/admin/route-forge/api/lint` keeps returning
 * `'error'` / `'warning'` / `'info'` exactly as before the enum migration.
 *
 * `LintReport::fromIssues()` matches exhaustively over the cases — adding a
 * new severity here without updating the match raises `UnhandledMatchError`
 * at runtime (compile-time-equivalent safety).
 */
enum LintSeverity: string
{
    case Error = 'error';

    case Warning = 'warning';

    case Info = 'info';
}
