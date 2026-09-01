<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\Metrics\MetricFailureLog;
use App\Support\Metrics\Metrics;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the page-metrics hub into the container.
 *
 * The hub is a singleton so that addon providers (which boot after this one
 * registers) accumulate their metric registrations onto the SAME instance
 * that a later request resolves metrics from -- the same reasoning as
 * {@see SkylightServiceProvider}.
 *
 * Registered ahead of the addon engine in bootstrap/providers.php so the
 * `metrics` binding exists before any addon ServiceProvider::boot() calls the
 * Metrics facade.
 *
 * MetricFailureLog is `scoped`, not `singleton`: it tracks which metric ids
 * have already logged a failure THIS request. A singleton (or a static
 * property inside it) would survive across every request an Octane worker
 * serves, permanently suppressing the log for a metric that failed once
 * (tasks.md 2.3). Octane's scoped-binding flush (config/octane.php) gives
 * every request a fresh, empty log -- see MetricFailureLog's docblock.
 */
final class MetricsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(MetricFailureLog::class);

        $this->app->singleton('metrics', static fn (Application $app): Metrics => new Metrics($app->make(MetricFailureLog::class)));
    }
}
