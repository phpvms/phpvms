<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Service;
use App\Exceptions\SettingNotFound;
use App\Support\RenamedSettings;
use App\Support\Settings\SettingDefinition;
use App\Support\Settings\SettingsRegistry;
use App\Support\Settings\SettingsWriter;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Spatie\LaravelSettings\Settings;

/**
 * Key-addressed access to the core settings classes in App\Settings.
 *
 * The `setting()` / `setting_save()` helpers and code that only knows a
 * setting's `group.name` key go through here; code that knows the group can
 * resolve the settings class from the container instead and read the typed
 * property. Both see the same instance, which the package binds per request
 * and per queued job, and caches across requests.
 */
class SettingService extends Service
{
    public function __construct(
        private readonly SettingsRegistry $registry,
        private readonly SettingsWriter $writer,
    ) {}

    /**
     * The value of a setting, as the helpers hand it out: typed, with an enum
     * as its backing value.
     *
     * @throws SettingNotFound when no settings class declares the key
     */
    public function retrieve(string $key): mixed
    {
        $definition = $this->definition($key) ?? throw new SettingNotFound($key.' not found');

        return $definition->present($this->instance($definition)->{$definition->name});
    }

    /**
     * Save a setting. Returns the value on success, or null when the key is
     * unknown or the value is null (nothing is created or written then).
     *
     * @throws ValidationException when the value cannot represent the setting's type
     */
    public function store(string $key, mixed $value): mixed
    {
        if ($value === null || !$this->definition($key) instanceof SettingDefinition) {
            return null;
        }

        $this->storeMany([$key => $value]);

        return is_bool($value) ? (int) $value : $value;
    }

    /**
     * Alias for store().
     */
    public function save(string $key, mixed $value): mixed
    {
        return $this->store($key, $value);
    }

    /**
     * Save several settings, writing each affected group once, in one
     * transaction. Unknown keys are skipped, and so are null values unless
     * `$skipNull` is false (a cleared form field then clears a string or date).
     *
     * @param array<string, mixed> $values keyed by `group.name`
     *
     * @throws ValidationException when a value cannot represent its setting's type
     */
    public function storeMany(array $values, bool $skipNull = true): void
    {
        /** @var array<class-string<Settings>, array<string, mixed>> $byClass */
        $byClass = [];

        foreach ($values as $key => $value) {
            $definition = $this->definition((string) $key);

            if (!$definition instanceof SettingDefinition || ($value === null && $skipNull)) {
                continue;
            }

            try {
                $byClass[$definition->class][$definition->name] = $definition->cast($value);
            } catch (InvalidArgumentException $exception) {
                throw ValidationException::withMessages([$definition->key => $exception->getMessage()]);
            }
        }

        if ($byClass !== []) {
            $this->writer->write($byClass);
        }
    }

    /**
     * The definition of a core setting, with renamed keys resolved.
     */
    public function definition(string $key): ?SettingDefinition
    {
        return $this->registry->find(RenamedSettings::resolve($key));
    }

    private function instance(SettingDefinition $definition): Settings
    {
        return app($definition->class);
    }
}
