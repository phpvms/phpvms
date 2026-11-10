<?php

declare(strict_types=1);

namespace App\Addons\Support;

use App\Contracts\Addons\AddonSettings;
use App\Support\Settings\SettingDefinition;
use App\Support\Settings\SettingsRegistry;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use LogicException;
use Spatie\LaravelSettings\Exceptions\CouldNotUnserializeSettings;
use Spatie\LaravelSettings\Support\SettingsCacheFactory;
use Throwable;

/**
 * The settings classes each enabled addon registered, keyed by registry_id.
 *
 * Filled while addon providers register (see
 * {@see AddonServiceProvider::registerSettings()}), from the boot cache, which
 * does not change during a worker's life: the map is per-process and the same
 * for every request, so keeping it static is Octane-safe.
 *
 * The classes are bound here rather than listed in `config('settings.settings')`
 * because the package ignores that list once `settings:discover` has written
 * its cache file, and addons come and go after that file is written.
 */
final class AddonSettingsRegistry
{
    /** @var array<class-string<AddonSettings>, string> */
    private static array $registryIds = [];

    /** @var array<string, list<class-string<AddonSettings>>> */
    private static array $classes = [];

    /**
     * Register an addon's settings classes and bind each in the container.
     * Rejects the whole list when any entry is not a usable settings class, so
     * an addon never runs with half of its settings.
     *
     * @param array<mixed> $classes
     *
     * @throws InvalidArgumentException
     */
    public static function register(Application $app, string $registryId, array $classes): void
    {
        if ($registryId === '') {
            throw new InvalidArgumentException('An addon without a registry_id cannot declare settings.');
        }

        foreach ($classes as $class) {
            if (!is_string($class) || !is_subclass_of($class, AddonSettings::class)) {
                throw new InvalidArgumentException('registerSettings() must return a list of '.AddonSettings::class.' class names.');
            }
        }

        /** @var list<class-string<AddonSettings>> $classes */
        $classes = array_values(array_unique($classes));

        foreach ($classes as $class) {
            self::$registryIds[$class] = $registryId;
        }

        try {
            self::assertUnique($registryId, $classes);
        } catch (Throwable $throwable) {
            foreach ($classes as $class) {
                unset(self::$registryIds[$class]);
            }

            throw $throwable;
        }

        self::$classes[$registryId] = $classes;

        foreach ($classes as $class) {
            self::bind($app, $class);
        }
    }

    /**
     * The registry_id of the addon that registered `$class`.
     *
     * @throws LogicException when no addon registered the class
     */
    public static function registryIdOf(string $class): string
    {
        return self::$registryIds[$class]
            ?? throw new LogicException($class.' was not registered by any addon; list it from registerSettings().');
    }

    /**
     * @return list<class-string<AddonSettings>>
     */
    public static function classesFor(string $registryId): array
    {
        return self::$classes[$registryId] ?? [];
    }

    /**
     * Every registered addon settings class.
     *
     * @return list<class-string<AddonSettings>>
     */
    public static function allClasses(): array
    {
        return array_keys(self::$registryIds);
    }

    /**
     * Every setting an addon declares, in section then declaration order.
     *
     * @return list<SettingDefinition>
     */
    public static function definitionsFor(string $registryId): array
    {
        $definitions = [];

        foreach (self::classesFor($registryId) as $class) {
            $definitions = [...$definitions, ...SettingsRegistry::definitionsFor($class)];
        }

        return $definitions;
    }

    /**
     * The definition of one addon setting, by property name or formatted key.
     */
    public static function find(string $registryId, string $key): ?SettingDefinition
    {
        $id = SettingDefinition::formatKey($key);

        foreach (self::definitionsFor($registryId) as $definition) {
            if ($definition->id() === $id) {
                return $definition;
            }
        }

        return null;
    }

    /**
     * Forget every registration. Tests only: in production an addon's
     * registration lives as long as the worker.
     */
    public static function flush(): void
    {
        self::$registryIds = [];
        self::$classes = [];
    }

    /**
     * Two sections of one addon must not declare the same setting name, or
     * `addon_setting()` could not tell them apart.
     *
     * @param list<class-string<AddonSettings>> $classes
     */
    private static function assertUnique(string $registryId, array $classes): void
    {
        $seen = [];

        foreach ($classes as $class) {
            foreach (SettingsRegistry::definitionsFor($class) as $definition) {
                if (isset($seen[$definition->id()])) {
                    throw new InvalidArgumentException(sprintf('Addon %s declares the setting "%s" in more than one class.', $registryId, $definition->name));
                }

                $seen[$definition->id()] = true;
            }
        }
    }

    /**
     * Bind the class the way the package binds the classes it knows about
     * (`SettingsContainer::registerBindings()`): scoped, so each request and
     * queued job gets a fresh instance, loaded from the cache when it is on.
     *
     * @param class-string<AddonSettings> $class
     */
    private static function bind(Application $app, string $class): void
    {
        $app->scoped($class, static function () use ($app, $class): AddonSettings {
            $cache = $app->make(SettingsCacheFactory::class)->build($class::repository());

            if ($cache->isEnabled()) {
                try {
                    $settings = $cache->get($class);

                    if ($settings instanceof $class) {
                        return $settings;
                    }
                } catch (CouldNotUnserializeSettings) {
                    Log::error("Could not unserialize settings class: `{$class}` from cache");
                }
            }

            return new $class();
        });
    }
}
