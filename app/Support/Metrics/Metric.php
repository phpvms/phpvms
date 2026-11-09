<?php

declare(strict_types=1);

namespace App\Support\Metrics;

use Closure;
use InvalidArgumentException;

/**
 * Typed builder for a {@see MetricRegistry::register()} definition. Defaults
 * match the ones the registry applies to an omitted key.
 *
 * `parameter()` type is one of `string|int|float|bool|enum`. `enum` is a
 * string parameter restricted to a non-empty `values` set (the registry has
 * no `enum` type of its own, so it is emitted as `string` + `values`).
 *
 * @phpstan-import-type MetricParameter from MetricRegistry
 */
final readonly class Metric
{
    private const array TYPES = ['string', 'int', 'float', 'bool', 'enum'];

    /**
     * @param array<string, MetricParameter> $parameters
     */
    private function __construct(
        public string $id,
        public Closure $resolve,
        public string $valueType,
        public string $label,
        public string $group,
        public string $format,
        public int $ttl,
        public array $parameters,
    ) {}

    /**
     * @param array<string, MetricParameter> $parameters
     */
    public static function integer(
        string $id,
        Closure $resolve,
        ?string $label = null,
        string $group = 'general',
        string $format = 'integer',
        int $ttl = 300,
        array $parameters = [],
    ): self {
        return new self($id, $resolve, 'integer', $label ?? $id, $group, $format, $ttl, $parameters);
    }

    /**
     * @param array<string, MetricParameter> $parameters
     */
    public static function float(
        string $id,
        Closure $resolve,
        ?string $label = null,
        string $group = 'general',
        string $format = 'integer',
        int $ttl = 300,
        array $parameters = [],
    ): self {
        return new self($id, $resolve, 'float', $label ?? $id, $group, $format, $ttl, $parameters);
    }

    /**
     * A copy with one declared parameter appended.
     *
     * @param array<int, mixed>|null $values
     *
     * @throws InvalidArgumentException
     */
    public function parameter(
        string $name,
        string $type,
        ?array $values = null,
        ?int $min = null,
        ?int $max = null,
    ): self {
        if (!in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException(sprintf('Metric "%s" parameter "%s" has unknown type "%s".', $this->id, $name, $type));
        }

        if ($type === 'enum' && ($values === null || $values === [])) {
            throw new InvalidArgumentException(sprintf('Metric "%s" enum parameter "%s" requires non-empty values.', $this->id, $name));
        }

        if (($min !== null || $max !== null) && $type !== 'int') {
            throw new InvalidArgumentException(sprintf('Metric "%s" parameter "%s": min/max are only allowed for int.', $this->id, $name));
        }

        $spec = ['type' => $type === 'enum' ? 'string' : $type];

        if ($values !== null && $values !== []) {
            $spec['values'] = array_values($values);
        }

        if ($min !== null) {
            $spec['min'] = $min;
        }

        if ($max !== null) {
            $spec['max'] = $max;
        }

        return new self(
            $this->id,
            $this->resolve,
            $this->valueType,
            $this->label,
            $this->group,
            $this->format,
            $this->ttl,
            [...$this->parameters, $name => $spec],
        );
    }

    /**
     * @return array<string, mixed> accepted by {@see MetricRegistry::register()}
     */
    public function toDefinition(): array
    {
        return [
            'id'         => $this->id,
            'label'      => $this->label,
            'group'      => $this->group,
            'value_type' => $this->valueType,
            'format'     => $this->format,
            'ttl'        => $this->ttl,
            'parameters' => $this->parameters,
            'resolve'    => $this->resolve,
        ];
    }
}
