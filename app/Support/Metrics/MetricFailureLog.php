<?php

declare(strict_types=1);

namespace App\Support\Metrics;

use Closure;

/**
 * Once-per-metric-id-per-request log de-duplication (design.md D5, tasks.md
 * 2.3).
 *
 * This MUST be request-scoped, not a singleton and not a static property.
 * The application runs under Octane, where a singleton's state and static
 * properties survive across every request served by a worker -- a `static`
 * array here would log a failing metric once for the process's entire
 * lifetime, not once per request. Bound via `$this->app->scoped()` in
 * `App\Providers\MetricsServiceProvider`, the same pattern
 * AppServiceProvider uses for AssetService's per-request memo, so Octane's
 * scoped-binding flush (config/octane.php) gives every request a fresh,
 * empty log.
 */
final class MetricFailureLog
{
    /**
     * Metric ids already logged during this request.
     *
     * @var array<string, true>
     */
    private array $logged = [];

    /**
     * Run `$log` for `$id` at most once per request.
     */
    public function once(string $id, Closure $log): void
    {
        if (isset($this->logged[$id])) {
            return;
        }

        $this->logged[$id] = true;

        $log();
    }
}
