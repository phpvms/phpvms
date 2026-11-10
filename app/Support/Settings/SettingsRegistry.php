<?php

declare(strict_types=1);

namespace App\Support\Settings;

use App\Contracts\Addons\AddonSettings;
use App\Support\RenamedSettings;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Support\Collection;
use LogicException;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use Spatie\LaravelSettings\Attributes\ShouldBeEncrypted;
use Spatie\LaravelSettings\Settings;

/**
 * The settings defined by the settings classes, by key.
 *
 * Core classes are the ones listed in `config('settings.settings')`; addon
 * classes are reflected on demand through {@see definitionsFor()}. Reflection
 * runs once per class per worker: the result depends only on the class, so it
 * is safe to keep across Octane requests.
 */
final class SettingsRegistry
{
    /** @var array<class-string<Settings>, list<SettingDefinition>> */
    private static array $definitions = [];

    /** @var Collection<string, SettingDefinition>|null Core definitions by formatted id. */
    private static ?Collection $core = null;

    /**
     * Every core setting, keyed by its formatted id (see
     * {@see SettingDefinition::formatKey()}), in class then declaration order.
     *
     * @return Collection<string, SettingDefinition>
     */
    public function all(): Collection
    {
        return self::$core ??= collect($this->coreClasses())
            ->flatMap(fn (string $class): array => self::definitionsFor($class))
            ->keyBy(fn (SettingDefinition $definition): string => $definition->id());
    }

    /**
     * The core setting addressed by `$key` (`group.name`, or the formatted id),
     * or null when no core class declares it. Renamed keys are not resolved
     * here; see {@see RenamedSettings}.
     */
    public function find(string $key): ?SettingDefinition
    {
        return $this->all()->get(SettingDefinition::formatKey($key));
    }

    /**
     * Core definitions grouped by settings class.
     *
     * @return Collection<class-string<Settings>, Collection<int, SettingDefinition>>
     */
    public function byClass(): Collection
    {
        return $this->all()->values()->groupBy('class');
    }

    /**
     * @return list<class-string<Settings>>
     */
    public function coreClasses(): array
    {
        return array_values(config('settings.settings', []));
    }

    /**
     * The definitions of one settings class, in property declaration order.
     *
     * @param  class-string<Settings>  $class
     * @return list<SettingDefinition>
     *
     * @throws LogicException when a property has no type, no default or an unsupported type
     */
    public static function definitionsFor(string $class): array
    {
        if (array_key_exists($class, self::$definitions)) {
            return self::$definitions[$class];
        }

        $reflection = new ReflectionClass($class);
        $isAddon = is_subclass_of($class, AddonSettings::class);
        $group = $class::group();
        $section = $isAddon ? $class::section() : $group;
        $encrypted = $class::encrypted();

        $definitions = [];

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $order => $property) {
            if ($property->isStatic() || $property->getDeclaringClass()->getName() === Settings::class) {
                continue;
            }

            $name = $property->getName();
            [$type, $enum, $nullable] = self::typeOf($class, $property);

            if (!$property->hasDefaultValue()) {
                throw new LogicException(sprintf('Setting %s::$%s must declare a default value.', $class, $name));
            }

            $field = ($property->getAttributes(SettingField::class)[0] ?? null)?->newInstance();

            $definitions[] = new SettingDefinition(
                class: $class,
                group: $group,
                section: $section,
                key: $isAddon ? $name : $group.'.'.$name,
                name: $name,
                type: $type,
                nullable: $nullable,
                default: $property->getDefaultValue(),
                enum: $enum,
                hidden: $field instanceof SettingField && $field->hidden,
                encrypted: in_array($name, $encrypted, true) || $property->getAttributes(ShouldBeEncrypted::class) !== [],
                order: $order,
                labelKey: $field?->label,
                descriptionKey: $field?->description,
                translationPrefix: $isAddon ? null : 'settings.'.$group.'.'.$name,
            );
        }

        return self::$definitions[$class] = $definitions;
    }

    /**
     * Forget every reflected class. Only for tests that declare classes on
     * the fly; definitions never change at run time.
     */
    public static function flush(): void
    {
        self::$definitions = [];
        self::$core = null;
    }

    /**
     * @return array{SettingType, class-string<BackedEnum>|null, bool}
     */
    private static function typeOf(string $class, ReflectionProperty $property): array
    {
        $type = $property->getType();

        if (!$type instanceof ReflectionNamedType) {
            throw new LogicException(sprintf('Setting %s::$%s must have a single, named type.', $class, $property->getName()));
        }

        $name = $type->getName();

        $settingType = match (true) {
            $name === 'bool'                            => SettingType::Bool,
            $name === 'int'                             => SettingType::Int,
            $name === 'float'                           => SettingType::Float,
            $name === 'string'                          => SettingType::String,
            is_a($name, DateTimeInterface::class, true) => SettingType::Date,
            is_a($name, BackedEnum::class, true)        => SettingType::Enum,
            default                                     => throw new LogicException(sprintf('Setting %s::$%s has the unsupported type %s.', $class, $property->getName(), $name)),
        };

        return [$settingType, $settingType === SettingType::Enum ? $name : null, $type->allowsNull()];
    }
}
