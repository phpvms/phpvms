<?php

declare(strict_types=1);

namespace App\Support\Metrics;

use App\Support\Skylight\Facades\Skylight;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The page-metrics extension hub.
 *
 * A single container-bound instance holds the metric registry and resolves
 * metrics through one cached, failure-contained path. Addons reach the
 * registry through the {@see Facades\Metrics} facade from their
 * ServiceProvider::boot(), mirroring
 * {@see Skylight}:
 *
 *   Metrics::registry()->register([...]);
 *   Metrics::resolve('pilots.total');
 *
 * Because addon providers boot before the request pipeline runs, everything
 * an enabled addon registers is present when a consumer resolves it -- and
 * anything a disabled addon would have registered is absent, because its
 * provider never booted (design.md D1).
 *
 * SCALAR ONLY, on purpose (design.md Non-Goals). A metric resolves to one
 * number, string, or bool -- never a list or a time series. Reaching for this
 * registry to render a list is the wrong tool: `app/Widgets/*` (e.g.
 * `LatestPireps`, `LatestNews`) already own that job, each with its own view
 * and `$config`. A time series belongs to `flowframe/laravel-trend`, already
 * used by the Filament chart widgets (`app/Filament/Widgets/HoursFlownChart`
 * and siblings). Naming all three "metrics" would produce one interface that
 * is bad at three jobs; keep this one to counts, sums, and other single
 * values.
 *
 * @phpstan-import-type MetricDefinition from MetricRegistry
 */
final readonly class Metrics
{
    /**
     * Cache key prefix for resolved metric values and the namespace version.
     *
     * TTL policy (per-definition `ttl`, see MetricRegistry::register()) is
     * owned by THIS change, not by `cache-heavy-paths` (proposal.md Impact) --
     * as of this writing that change has not landed (no
     * openspec/changes/cache-heavy-paths/ directory in this worktree). If it
     * lands later, its TTL policy should take over from the per-definition
     * defaults set in FirstPartyMetricsServiceProvider.
     */
    private const string CACHE_PREFIX = 'page-metrics';

    /**
     * Cache key holding the current namespace version. Bumping it changes
     * every metric's cache key at once, invalidating the whole keyspace
     * without enumerating ids (design.md D3, tasks.md 2.4).
     */
    private const string NAMESPACE_VERSION_KEY = self::CACHE_PREFIX.'.namespace-version';

    private MetricRegistry $registry;

    /**
     * @param MetricFailureLog|Closure(): MetricFailureLog $failureLog A closure is resolved on every failure, so a
     *                                                                 long-lived registry always sees the current request's scoped log.
     */
    public function __construct(private MetricFailureLog|Closure $failureLog)
    {
        $this->registry = new MetricRegistry();
    }

    /**
     * The metric definition registry.
     */
    public function registry(): MetricRegistry
    {
        return $this->registry;
    }

    /**
     * The full catalog, with presentation metadata -- drives a picker
     * (design.md D6).
     *
     * @return array<int, MetricDefinition>
     */
    public function all(): array
    {
        return $this->registry->all();
    }

    /**
     * Whether a metric id is registered.
     */
    public function has(string $id): bool
    {
        return $this->registry->has($id);
    }

    /**
     * Resolve a metric by id, through cache, never throwing into the caller.
     *
     * Failure -- an unknown id, an invalid/undeclared parameter, or a
     * throwing resolver -- is a value: this returns null and logs once per id
     * per request (design.md D5). A resolved value is never null itself; the
     * first-party metrics in this catalog all resolve to a count or a sum,
     * which is 0 (not null) when nothing matches.
     *
     * @param array<string, mixed> $parameters
     */
    public function resolve(string $id, array $parameters = []): mixed
    {
        $definition = $this->registry->get($id);

        if ($definition === null) {
            $this->recordFailure($id, sprintf('Metric "%s" was requested but is not registered.', $id));

            return null;
        }

        $normalizedParameters = $this->validateParameters($definition, $parameters);

        if ($normalizedParameters === false) {
            $this->recordFailure($id, sprintf('Metric "%s" was resolved with an undeclared or out-of-range parameter.', $id));

            return null;
        }

        $key = $this->cacheKey($id, $normalizedParameters);

        try {
            return Cache::remember(
                $key,
                $definition['ttl'],
                static fn (): mixed => ($definition['resolve'])($normalizedParameters),
            );
        } catch (Throwable $throwable) {
            $this->recordFailure($id, $throwable);

            return null;
        }
    }

    /**
     * Apply the definition's declared display format to a resolved value, so
     * every consumer renders the same string for the same metric
     * (design.md D6). Returns null when the id is unknown or the value is
     * itself null (a failed resolution).
     */
    public function format(string $id, mixed $value): ?string
    {
        $definition = $this->registry->get($id);

        if ($definition === null || $value === null) {
            return null;
        }

        return match ($definition['format']) {
            'decimal_1' => number_format((float) $value, 1),
            default     => number_format((float) $value),
        };
    }

    /**
     * Bump the metric cache namespace, invalidating every metric's cached
     * value on next access without enumerating ids.
     */
    public function bumpNamespace(): int
    {
        $version = $this->namespaceVersion() + 1;

        Cache::forever(self::NAMESPACE_VERSION_KEY, $version);

        return $version;
    }

    /**
     * Validate supplied parameters against the definition's declarations.
     * Returns the normalized (declared-order-independent, ksort'd) parameter
     * set on success, or `false` when an undeclared name or an out-of-range
     * value was supplied -- the resolver is never invoked in that case
     * (design.md D4).
     *
     * @param  MetricDefinition           $definition
     * @param  array<string, mixed>       $parameters
     * @return array<string, mixed>|false
     */
    private function validateParameters(array $definition, array $parameters): array|false
    {
        $declared = $definition['parameters'];

        foreach (array_keys($parameters) as $name) {
            if (!array_key_exists($name, $declared)) {
                return false;
            }
        }

        $normalized = [];

        foreach ($parameters as $name => $value) {
            $spec = $declared[$name];

            if (!$this->parameterMatchesType($spec['type'], $value)) {
                return false;
            }

            if (isset($spec['values']) && !in_array($value, $spec['values'], true)) {
                return false;
            }

            if (isset($spec['min']) && $value < $spec['min']) {
                return false;
            }

            if (isset($spec['max']) && $value > $spec['max']) {
                return false;
            }

            $normalized[$name] = $value;
        }

        ksort($normalized);

        return $normalized;
    }

    private function parameterMatchesType(string $type, mixed $value): bool
    {
        return match ($type) {
            'string' => is_string($value),
            'int'    => is_int($value),
            'float'  => is_int($value) || is_float($value),
            'bool'   => is_bool($value),
            default  => false,
        };
    }

    /**
     * @param array<string, mixed> $normalizedParameters
     */
    private function cacheKey(string $id, array $normalizedParameters): string
    {
        $suffix = $normalizedParameters === []
            ? ''
            : '.'.md5((string) json_encode($normalizedParameters));

        return sprintf('%s.v%d.%s%s', self::CACHE_PREFIX, $this->namespaceVersion(), $id, $suffix);
    }

    private function namespaceVersion(): int
    {
        return (int) (Cache::get(self::NAMESPACE_VERSION_KEY) ?? 1);
    }

    private function recordFailure(string $id, Throwable|string $reason): void
    {
        $log = $this->failureLog instanceof Closure ? ($this->failureLog)() : $this->failureLog;

        $log->once($id, static function () use ($id, $reason): void {
            if ($reason instanceof Throwable) {
                Log::warning(sprintf('Metric "%s" resolver failed.', $id), [
                    'metric'    => $id,
                    'exception' => $reason,
                ]);

                return;
            }

            Log::warning($reason, ['metric' => $id]);
        });
    }
}
