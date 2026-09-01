<?php

declare(strict_types=1);

namespace App\Support\Metrics;

use App\Support\Skylight\WidgetRegistry;
use Closure;
use InvalidArgumentException;

/**
 * Server-side registry of named, scalar metric definitions.
 *
 * This is the SINGLE source of truth for "what airline-wide facts can a
 * consumer ask for by id". First-party metrics and addon metrics register the
 * same way. Because an addon only registers from its ServiceProvider::boot(),
 * and a DISABLED addon's provider never boots, a disabled addon's metrics
 * simply never appear here -- no catalog edit, no runtime change, no dead
 * entry. Disable-safety is a property of the registration mechanism, not a
 * runtime `enabled` check. Mirrors {@see WidgetRegistry}
 * on purpose -- see design.md D1 in the page-metrics OpenSpec change.
 *
 * The resolver is a PHP closure, never caller-supplied query input. That is
 * the entire security model (design.md D2): a consumer supplies a string id
 * and, optionally, declared/validated parameter values (see {@see Metrics}
 * for validation and resolution); no consumer-supplied value ever reaches
 * Eloquent except through a declared parameter of a registered metric.
 *
 * @phpstan-type MetricParameter array{
 *     type: 'string'|'int'|'float'|'bool',
 *     values?: array<int, mixed>,
 *     min?: int|float,
 *     max?: int|float,
 * }
 * @phpstan-type MetricDefinition array{
 *     id: string,
 *     label: string,
 *     group: string,
 *     value_type: 'integer'|'float',
 *     format: string,
 *     ttl: int,
 *     parameters: array<string, MetricParameter>,
 *     resolve: Closure(array<string, mixed>): mixed,
 * }
 */
final class MetricRegistry
{
    /**
     * Registered metric definitions, keyed by id (last registration wins so a
     * first-party metric can be intentionally overridden by id).
     *
     * @var array<string, MetricDefinition>
     */
    private array $metrics = [];

    /**
     * Register (or override) a metric definition.
     *
     * Recognised keys:
     *   id         string   required, unique.
     *   label      string   human label for a picker/UI (default: id).
     *   group      string   grouping key for a picker (default: 'general').
     *   value_type string   'integer' | 'float' (default: 'integer').
     *   format     string   display-format key applied by {@see Metrics::format()}
     *                       (default: 'integer').
     *   ttl        int      cache TTL in seconds (default: 300).
     *   parameters array    declared parameters, keyed by name. Each declares
     *                       a `type` and, optionally, a `values` set or
     *                       `min`/`max` range. Undeclared or out-of-range
     *                       parameters fail resolution rather than reaching
     *                       the resolver (default: []).
     *   resolve    Closure  required. `fn (array $parameters): mixed`. PHP
     *                       only -- this is the whole security boundary.
     *
     * @param array<string, mixed> $definition
     *
     * @throws InvalidArgumentException when `id` or `resolve` is missing or
     *                                  malformed
     */
    public function register(array $definition): self
    {
        if (empty($definition['id']) || !is_string($definition['id'])) {
            throw new InvalidArgumentException('Metric definition requires a string "id".');
        }

        if (!($definition['resolve'] ?? null) instanceof Closure) {
            throw new InvalidArgumentException(sprintf('Metric definition "%s" requires a "resolve" closure.', $definition['id']));
        }

        $definition['label'] ??= $definition['id'];
        $definition['group'] ??= 'general';
        $definition['value_type'] ??= 'integer';
        $definition['format'] ??= 'integer';
        $definition['ttl'] ??= 300;
        $definition['parameters'] ??= [];

        /** @var MetricDefinition $definition */
        $this->metrics[$definition['id']] = $definition;

        return $this;
    }

    /**
     * Remove a metric by id (e.g. to hide a first-party metric from an addon).
     */
    public function remove(string $id): self
    {
        unset($this->metrics[$id]);

        return $this;
    }

    /**
     * A single metric definition, or null when the id is not registered.
     *
     * @return MetricDefinition|null
     */
    public function get(string $id): ?array
    {
        return $this->metrics[$id] ?? null;
    }

    /**
     * All registered metric definitions as a flat list -- the catalog, with
     * presentation metadata, that drives a picker (design.md D6).
     *
     * @return array<int, MetricDefinition>
     */
    public function all(): array
    {
        return array_values($this->metrics);
    }

    /**
     * Whether a metric id is registered (i.e. its owning addon is enabled).
     */
    public function has(string $id): bool
    {
        return isset($this->metrics[$id]);
    }
}
