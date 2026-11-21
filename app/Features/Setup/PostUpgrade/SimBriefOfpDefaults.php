<?php

declare(strict_types=1);

namespace App\Features\Setup\PostUpgrade;

use App\Support\Dto\SimBriefOfp\SimBriefOfp;
use DateTimeInterface;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;
use Spatie\LaravelData\Dto;

/**
 * SimBrief has no schema and older OFPs lack fields the 8.x DTO requires.
 * Fills each gap with an empty value of the declared type, walking the DTO's constructors.
 */
final class SimBriefOfpDefaults
{
    /** @var array<class-string, array<string, array{class-string, string}>> */
    private static array $listTypes = [];

    /**
     * @param  array<string, mixed> $ofp
     * @return array<string, mixed>
     */
    public function fill(array $ofp): array
    {
        $generated = $ofp['params']['time_generated'] ?? null;

        return $this->object(SimBriefOfp::class, $ofp, is_string($generated) && $generated !== '' ? $generated : '1970-01-01T00:00:00Z');
    }

    /**
     * @param  class-string         $class
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function object(string $class, array $data, string $date): array
    {
        $constructor = new ReflectionClass($class)->getConstructor();

        foreach ($constructor?->getParameters() ?? [] as $param) {
            $name = $param->getName();

            if (!array_key_exists($name, $data)) {
                // laravel-data fills a missing nullable with null itself.
                if (!$param->isDefaultValueAvailable() && !$param->allowsNull()) {
                    $data[$name] = $this->empty($param, $date);
                }

                continue;
            }

            $data[$name] = $this->present($class, $param, $data[$name], $date);
        }

        return $data;
    }

    private function present(string $class, ReflectionParameter $param, mixed $value, string $date): mixed
    {
        // An empty element, or a word like `auto`, where the DTO wants a number or an object.
        if (is_string($value) && !$this->allows($param, 'string') && ($value === '' || (!is_numeric($value) && $this->numericOnly($param)))) {
            return $this->empty($param, $date);
        }

        $dto = $this->dtoClass($param);

        if ($dto !== null) {
            return is_array($value) && $value !== [] && !array_is_list($value) ? $this->object($dto, $value, $date) : $value;
        }

        [$item, $kind] = $this->listTypes($class)[$param->getName()] ?? [null, null];

        if ($item === null || !is_array($value) || $value === []) {
            return $value;
        }

        return match ($kind) {
            'map'    => array_map(fn (mixed $v): mixed => $this->item($item, $v, $date), $value),
            'nested' => array_map(fn (mixed $list): mixed => is_array($list) ? array_map(fn (mixed $v): mixed => $this->item($item, $v, $date), $list) : $list, $value),
            // SimBrief sends a lone item as the item itself, not a list of one.
            default => array_map(fn (mixed $v): mixed => $this->item($item, $v, $date), array_is_list($value) ? $value : [$value]),
        };
    }

    /**
     * @param class-string $class
     */
    private function item(string $class, mixed $value, string $date): mixed
    {
        return is_array($value) && $value !== [] && !array_is_list($value) ? $this->object($class, $value, $date) : $value;
    }

    private function empty(ReflectionParameter $param, string $date): mixed
    {
        if ($param->allowsNull()) {
            return null;
        }

        $names = $this->typeNames($param);

        foreach (['false', 'array', 'string', 'int', 'float', 'bool'] as $builtin) {
            if (in_array($builtin, $names, true)) {
                return match ($builtin) {
                    'false', 'bool' => false,
                    'array'         => [],
                    'string'        => '',
                    'int'           => 0,
                    'float'         => 0.0,
                };
            }
        }

        $dto = $this->dtoClass($param);

        if ($dto !== null) {
            return $this->object($dto, [], $date);
        }

        foreach ($names as $name) {
            if (is_a($name, DateTimeInterface::class, true)) {
                return $date;
            }
        }

        return '';
    }

    /**
     * @return class-string<Dto>|null
     */
    private function dtoClass(ReflectionParameter $param): ?string
    {
        foreach ($this->typeNames($param) as $name) {
            if (is_a($name, Dto::class, true)) {
                return $name;
            }
        }

        return null;
    }

    private function numericOnly(ReflectionParameter $param): bool
    {
        return array_diff($this->typeNames($param), ['int', 'float']) === [];
    }

    private function allows(ReflectionParameter $param, string $builtin): bool
    {
        return in_array($builtin, $this->typeNames($param), true);
    }

    /**
     * @return list<string>
     */
    private function typeNames(ReflectionParameter $param): array
    {
        $type = $param->getType();
        $types = $type instanceof ReflectionUnionType ? $type->getTypes() : [$type];

        return array_values(array_map(
            static fn (ReflectionNamedType $t): string => $t->getName(),
            array_filter($types, static fn (?ReflectionType $t): bool => $t instanceof ReflectionNamedType),
        ));
    }

    /**
     * The DTO class of each array parameter, read from `@param Foo[]` or `@param list<Foo[]>`.
     *
     * @param  class-string                               $class
     * @return array<string, array{class-string, string}>
     */
    private function listTypes(string $class): array
    {
        if (isset(self::$listTypes[$class])) {
            return self::$listTypes[$class];
        }

        $reflection = new ReflectionClass($class);
        $doc = (string) $reflection->getConstructor()?->getDocComment();
        $types = [];

        preg_match_all('/@param\s+(\S+)\s+\$(\w+)/', $doc, $matches, PREG_SET_ORDER);

        foreach ($matches as [, $type, $name]) {
            $kind = match (true) {
                preg_match('/^list<(\w+)\[\]>$/', $type, $m) === 1      => 'nested',
                preg_match('/^array<string,\s*(\w+)/', $type, $m) === 1 => 'map',
                preg_match('/^(\w+)\[\]$/', $type, $m) === 1            => 'list',
                default                                                 => null,
            };
            $fqcn = $kind === null ? null : $reflection->getNamespaceName().'\\'.$m[1];

            if ($fqcn !== null && is_a($fqcn, Dto::class, true)) {
                $types[$name] = [$fqcn, $kind];
            }
        }

        return self::$listTypes[$class] = $types;
    }
}
