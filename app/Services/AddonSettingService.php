<?php

declare(strict_types=1);

namespace App\Services;

use App\Addons\Support\AddonSettingsRegistry;
use App\Addons\Support\BootCache;
use App\Contracts\Addons\AddonSettings;
use App\Contracts\Service;
use App\Exceptions\SettingNotFound;
use App\Models\Addon;
use App\Support\Settings\SettingDefinition;
use App\Support\Settings\SettingsWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Spatie\LaravelSettings\Settings;

/**
 * Handle-addressed access to the settings classes addons register (see
 * {@see AddonSettings}).
 *
 * An addon is addressed by a handle that resolves as its manifest `alias`, its
 * `registry_id`, or its name; the setting by its property name. Values come from
 * the addon's settings classes, which the package loads once per request and
 * caches across requests, so this service keeps only the handle resolution.
 *
 * Resolved registry_ids are memoized: the service is a singleton registered in
 * config/octane.php 'flush', so Octane discards the memo before every request,
 * and the queue worker clears it before every job.
 */
class AddonSettingService extends Service
{
    /** @var array<string, string|null> Per-request memo of resolved registry_ids, keyed by handle. */
    private array $registryIds = [];

    public function __construct(
        private readonly BootCache $bootCache,
        private readonly SettingsWriter $writer,
    ) {}

    /**
     * The value of an addon setting: typed, with an enum as its backing value.
     *
     * @throws SettingNotFound when the addon or key cannot be resolved
     */
    public function retrieve(string $handle, string $key): mixed
    {
        $registryId = $this->resolveRegistryId($handle) ?? throw new SettingNotFound($handle.' addon not found');
        $definition = AddonSettingsRegistry::find($registryId, $key) ?? throw new SettingNotFound($key.' not found');

        return $definition->present($this->instance($definition)->{$definition->name});
    }

    /**
     * Forget the memoized registry_ids. Called before each queued job, which
     * Octane's flush does not cover.
     */
    public function clearMemo(): void
    {
        $this->registryIds = [];
    }

    /**
     * Save a value for the addon identified by $handle. Returns the value on
     * success, or null when the addon/key is unknown or the value is null.
     *
     * @throws ValidationException when the value cannot represent the setting's type
     */
    public function store(string $handle, string $key, mixed $value): mixed
    {
        $registryId = $this->resolveRegistryId($handle);

        return $registryId === null ? null : $this->storeFor($registryId, $key, $value);
    }

    /**
     * Save a value for a known addon registry_id. Returns the value on
     * success, or null when the key is unknown or the value is null.
     *
     * @throws ValidationException when the value cannot represent the setting's type
     */
    public function storeFor(string $registryId, string $key, mixed $value): mixed
    {
        if ($value === null || !AddonSettingsRegistry::find($registryId, $key) instanceof SettingDefinition) {
            return null;
        }

        $this->storeManyFor($registryId, [$key => $value]);

        return is_bool($value) ? (int) $value : $value;
    }

    /**
     * Save several settings of one addon, writing each affected section once,
     * in one transaction. Unknown keys are skipped, and so are null values unless
     * `$skipNull` is false (a cleared form field then clears a string or date).
     *
     * @param array<string, mixed> $values keyed by setting name
     *
     * @throws ValidationException when a value cannot represent its setting's type
     */
    public function storeManyFor(string $registryId, array $values, bool $skipNull = true): void
    {
        /** @var array<class-string<Settings>, array<string, mixed>> $byClass */
        $byClass = [];

        foreach ($values as $key => $value) {
            $definition = AddonSettingsRegistry::find($registryId, (string) $key);

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
     * Alias for store().
     */
    public function save(string $handle, string $key, mixed $value): mixed
    {
        return $this->store($handle, $key, $value);
    }

    /**
     * Delete every stored setting of an addon (uninstall with data removal).
     * Groups are matched exactly in PHP rather than with LIKE, whose `_`
     * wildcard a registry_id may contain.
     */
    public function deleteFor(string $registryId): void
    {
        $groups = DB::table('addon_settings')
            ->distinct()
            ->pluck('group')
            ->filter(fn (string $group): bool => str_starts_with($group, $registryId.':'))
            ->values();

        if ($groups->isNotEmpty()) {
            DB::table('addon_settings')->whereIn('group', $groups)->delete();
        }
    }

    /**
     * Every setting an addon declares, in section then declaration order.
     *
     * @return list<SettingDefinition>
     */
    public function all(string $registryId): array
    {
        return AddonSettingsRegistry::definitionsFor($registryId);
    }

    /**
     * Resolve a handle (alias or registry_id) to its Addon model, or null.
     */
    public function resolveAddon(?string $handle): ?Addon
    {
        if ($handle === null || $handle === '') {
            return null;
        }

        $registryId = $this->resolveRegistryId($handle);

        return $registryId === null ? null : Addon::query()->where('registry_id', $registryId)->first();
    }

    /**
     * Resolve a handle (registry_id, name or manifest alias) to a registry_id.
     *
     * The addons table carries registry_id and name; `alias` lives only in the
     * manifest, so an alias handle is matched against the boot cache.
     */
    public function resolveRegistryId(string $handle): ?string
    {
        if ($handle === '') {
            return null;
        }

        if (array_key_exists($handle, $this->registryIds)) {
            return $this->registryIds[$handle];
        }

        return $this->registryIds[$handle] = $this->findRegistryId($handle);
    }

    private function findRegistryId(string $handle): ?string
    {
        $registryId = Addon::query()->where('registry_id', $handle)->value('registry_id')
            ?? Addon::query()->where('name', $handle)->value('registry_id');

        if ($registryId !== null) {
            return (string) $registryId;
        }

        $entry = $this->bootCache->all()->first(
            fn ($e): bool => $e->alias === $handle
                || $e->registryId === $handle
                || $e->name === $handle
        );

        return $entry?->registryId;
    }

    private function instance(SettingDefinition $definition): Settings
    {
        return app($definition->class);
    }
}
