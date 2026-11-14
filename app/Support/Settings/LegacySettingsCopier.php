<?php

declare(strict_types=1);

namespace App\Support\Settings;

use App\Features\Addons\Support\AddonSettingsRegistry;
use App\Support\RenamedSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Spatie\LaravelSettings\Factories\SettingsRepositoryFactory;
use Spatie\LaravelSettings\Support\Crypto;
use Throwable;

/**
 * Copies the values of the settings tables as they were before typed settings
 * (`settings_backup`, `addon_settings_backup`) into the current `settings` and
 * `addon_settings` tables.
 *
 * Run by the schema migration that creates the new tables, and again by the
 * last data migration, after the historical data migrations have written the
 * backups. Each run upserts every copied value, so running it twice is the same
 * as running it once. The backups are only read.
 *
 * Core values are cast to the type their settings class declares; a value that
 * cannot be cast is left out (the setting then reads as its default) and
 * logged. Rows no core class declares, after renames, stay in the backup and
 * are logged. Addon values are cast by the type column of the backup, because
 * the addon's settings class may not exist yet when this runs.
 */
final readonly class LegacySettingsCopier
{
    public function __construct(private SettingsRegistry $registry) {}

    public function copy(): void
    {
        $this->copyCore();
        $this->copyAddons();
    }

    public function copyCore(): void
    {
        if (!Schema::hasTable('settings_backup')) {
            return;
        }

        /** @var array<string, string|null> $values by formatted id */
        $values = DB::table('settings_backup')->pluck('value', 'id')->map(fn (mixed $value): ?string => $value === null ? null : (string) $value)->all();
        $unclaimed = $values;

        // A renamed key's value moves to the new key, unless the new key
        // already has a row (a data migration moved it already).
        foreach (RenamedSettings::MAP as $old => $new) {
            $oldId = SettingDefinition::formatKey($old);
            $newId = SettingDefinition::formatKey($new);

            if (array_key_exists($oldId, $values) && !array_key_exists($newId, $values)) {
                $values[$newId] = $values[$oldId];
            }

            unset($unclaimed[$oldId]);
        }

        $payloads = [];

        foreach ($this->registry->all() as $id => $definition) {
            unset($unclaimed[$id]);

            if (!array_key_exists($id, $values)) {
                continue;
            }

            try {
                $payload = $definition->toPayload($definition->castLegacy($values[$id]));
            } catch (InvalidArgumentException) {
                Log::warning(sprintf('Settings copy: %s held %s, which is not a valid %s; it now reads as its default.', $definition->key, var_export($values[$id], true), $definition->type->value));

                continue;
            }

            $payloads[$definition->group][$definition->name] = $definition->encrypted ? Crypto::encrypt($payload) : $payload;
        }

        $this->write('database', $payloads);

        if ($unclaimed !== []) {
            Log::info('Settings copy: settings_backup rows no settings class declares were left in the backup: '.implode(', ', array_keys($unclaimed)));
        }
    }

    public function copyAddons(): void
    {
        if (!Schema::hasTable('addon_settings_backup') || !Schema::hasColumn('addon_settings_backup', 'registry_id')) {
            return;
        }

        $payloads = [];

        foreach (DB::table('addon_settings_backup')->whereNotNull('registry_id')->get(['registry_id', 'group', 'key', 'value', 'type']) as $row) {
            $group = self::addonGroup((string) $row->registry_id, (string) $row->group);

            $payloads[$group][SettingDefinition::formatKey((string) $row->key)] = $this->castAddonValue($row->type, $row->value);
        }

        $this->write('addons', $payloads);
    }

    /**
     * Log, per addon, the copied keys no registered settings class declares.
     * They are kept in `addon_settings`, so a later version of the addon that
     * declares them again reads them back.
     */
    public function logUndeclaredAddonKeys(): void
    {
        if (!Schema::hasTable('addon_settings_backup') || !Schema::hasColumn('addon_settings_backup', 'registry_id')) {
            return;
        }

        DB::table('addon_settings_backup')
            ->whereNotNull('registry_id')
            ->get(['registry_id', 'key'])
            ->groupBy('registry_id')
            ->each(function ($rows, string $registryId): void {
                $undeclared = $rows
                    ->pluck('key')
                    ->reject(fn (string $key): bool => AddonSettingsRegistry::find($registryId, $key) instanceof SettingDefinition)
                    ->values();

                if ($undeclared->isNotEmpty()) {
                    Log::info(sprintf('Settings copy: addon %s has stored settings it does not declare (kept): %s', $registryId, $undeclared->implode(', ')));
                }
            });
    }

    /**
     * The storage group of an addon setting from the old table, whose `group`
     * was the section heading: `{registry_id}:{slug}`, `general` when blank.
     * See \App\Contracts\Addons\AddonSettings::section().
     */
    public static function addonGroup(string $registryId, string $section): string
    {
        $slug = Str::slug($section);

        return $registryId.':'.($slug === '' ? 'general' : $slug);
    }

    /**
     * An addon value cast the way the old addon settings service read it.
     */
    private function castAddonValue(?string $type, mixed $value): mixed
    {
        $value = $value === null ? '' : (string) $value;

        try {
            return match ($type) {
                'bool', 'boolean'          => in_array($value, ['true', '1'], true),
                'date'                     => $value === '' ? null : CarbonImmutable::parse($value)->format(DATE_ATOM),
                'int', 'integer', 'number' => (int) $value,
                'float'                    => (float) $value,
                default                    => $value,
            };
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param array<string, array<string, mixed>> $payloads payload by name, by group
     */
    private function write(string $repository, array $payloads): void
    {
        $settings = SettingsRepositoryFactory::create($repository);

        foreach ($payloads as $group => $properties) {
            $settings->updatePropertiesPayload((string) $group, $properties);
        }
    }
}
