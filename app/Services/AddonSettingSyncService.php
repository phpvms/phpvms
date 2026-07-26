<?php

declare(strict_types=1);

namespace App\Services;

use App\Addons\Models\AddonBootCache;
use App\Addons\Support\BootCache;
use App\Contracts\Addons\HasSettings;
use App\Contracts\Service;
use App\Models\Addon;
use App\Models\AddonSetting;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Synchronizes each enabled addon's declared settings schema into the
 * `addon_settings` table.
 *
 * For every enabled boot-cache entry, resolves its addon row, finds any
 * service provider implementing {@see HasSettings}, and upserts the declared
 * schema: new keys are inserted with their `default` as the initial value,
 * metadata (name/group/type/options/description/order/alias) is reconciled,
 * and existing user-edited `value`s are preserved. Idempotent.
 *
 * Orphan rows — keys an addon no longer declares — are logged, not deleted, so
 * a value is never lost on a transient schema change (D-open-question default).
 *
 * Stateless and Octane-safe: no mutable instance state.
 */
class AddonSettingSyncService extends Service
{
    public function __construct(
        private readonly Application $app,
        private readonly BootCache $bootCache,
    ) {}

    /**
     * Sync every enabled addon's declared settings.
     */
    public function sync(): void
    {
        foreach ($this->bootCache->enabled() as $entry) {
            $this->syncEntry($entry);
        }
    }

    /**
     * Sync a single boot-cache entry.
     */
    private function syncEntry(AddonBootCache $entry): void
    {
        $addonId = $this->resolveAddonId($entry);

        if ($addonId === null) {
            return;
        }

        // Key by normalized key so duplicate declarations (multiple providers,
        // or repeated entries) collapse to one row — last declaration wins —
        // which keeps the (addon_id, key) upsert from failing on duplicates.
        $rowsByKey = [];

        foreach ($this->collectSchema($entry) as $order => $setting) {
            if (!isset($setting['key'])) {
                continue;
            }

            $key = AddonSetting::formatKey((string) $setting['key']);
            $default = $this->stringify($setting['default'] ?? '');

            $rowsByKey[$key] = [
                'addon_id'    => $addonId,
                'alias'       => $entry->alias,
                'key'         => $key,
                'name'        => (string) ($setting['name'] ?? $setting['key']),
                'value'       => $default,
                'default'     => $default,
                'group'       => (string) ($setting['group'] ?? 'general'),
                'order'       => (int) ($setting['order'] ?? $order),
                'type'        => (string) ($setting['type'] ?? 'text'),
                'options'     => (string) ($setting['options'] ?? ''),
                'description' => $this->stringify($setting['description'] ?? ''),
            ];
        }

        if ($rowsByKey !== []) {
            // Preserve existing `value`; reconcile everything else (mirrors SettingsSeeder).
            AddonSetting::upsert(
                array_values($rowsByKey),
                uniqueBy: ['addon_id', 'key'],
                update: ['alias', 'name', 'default', 'group', 'order', 'type', 'options', 'description'],
            );
        }

        // Always run, even when the addon now declares nothing, so keys it
        // previously persisted are reported as orphans (kept, not deleted).
        $this->logOrphans($addonId, array_keys($rowsByKey));
    }

    /**
     * Merge the settings declared by every HasSettings provider on this entry.
     *
     * @return array<int, array<string, mixed>>
     */
    private function collectSchema(AddonBootCache $entry): array
    {
        $schema = [];

        foreach ($entry->providers as $providerClass) {
            if ($providerClass === '') {
                continue;
            }

            if (!class_exists($providerClass)) {
                continue;
            }

            if (!is_a($providerClass, HasSettings::class, true)) {
                continue;
            }

            try {
                /** @var HasSettings $provider */
                $provider = $this->app->getProvider($providerClass) ?? new $providerClass($this->app);
                $schema = [...$schema, ...$provider->settings()];
            } catch (Throwable $throwable) {
                Log::warning('AddonSettingSync: failed to read settings from '.$providerClass, [
                    'exception' => $throwable->getMessage(),
                ]);
            }
        }

        return $schema;
    }

    /**
     * Resolve the addon row id for a boot-cache entry (registry_id when
     * declared, namespace when not).
     *
     * A declared registry_id that matches no row (e.g. an install predating the
     * registry_id backfill) falls through to the namespace lookup instead of
     * returning null, and logs a warning — a silent no-op would otherwise leave
     * every declared setting missing with no signal at all.
     */
    private function resolveAddonId(AddonBootCache $entry): ?int
    {
        if ($entry->registryId !== null) {
            $id = Addon::query()->where('registry_id', $entry->registryId)->value('id');

            if ($id !== null) {
                return (int) $id;
            }

            Log::warning('AddonSettingSync: no addon row for registry_id '.$entry->registryId.'; falling back to namespace');
        }

        $id = Addon::query()->where('namespace', $entry->namespace)->value('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * Log (do not delete) rows whose key is no longer declared.
     *
     * @param list<string> $declaredKeys
     */
    private function logOrphans(int $addonId, array $declaredKeys): void
    {
        $orphans = AddonSetting::query()
            ->where('addon_id', $addonId)
            ->whereNotIn('key', $declaredKeys)
            ->pluck('key');

        if ($orphans->isNotEmpty()) {
            Log::info('AddonSettingSync: addon '.$addonId.' has undeclared settings (kept): '.$orphans->implode(', '));
        }
    }

    /**
     * Normalize a scalar value to a string for storage.
     */
    private function stringify(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }
}
