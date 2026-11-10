<?php

declare(strict_types=1);

use App\Addons\Support\AddonSettingsRegistry;
use App\Addons\Support\ManifestParser;
use App\Contracts\Addons\AddonSettings;
use App\Models\Addon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Resolve the installed sample addon's directory, or null when there is none.
 *
 * The bundled modules/Sample was removed from the repo and the module that
 * replaced it is not tracked (modules/.gitignore ignores every addon
 * directory), so a fresh clone or a CI checkout has no sample addon at all.
 * The coverage that drives one is therefore only available to a working copy
 * that happens to have it installed — callers guard those tests with
 * ->skip(sampleAddonMissing(...), ...) rather than failing the suite.
 *
 * Matched on the manifest, never on the directory name: the addon engine reads
 * each addon's own addon.json/composer.json and is directory-name agnostic, so
 * the sample may sit in "Sample", "phpvms-sample", or anything else.
 */
function sampleAddonPath(): ?string
{
    $base = config('addons.paths.base', base_path('modules'));

    if (!is_string($base) || !File::isDirectory($base)) {
        return null;
    }

    $parser = new ManifestParser();

    foreach (File::directories($base) as $directory) {
        if ($parser->parse($directory)?->registryId === 'phpvms/sample') {
            return $directory;
        }
    }

    return null;
}

/**
 * Whether no sample addon is installed on disk. See sampleAddonPath().
 */
function sampleAddonMissing(): bool
{
    return sampleAddonPath() === null;
}

/**
 * Create an enabled addon row and register settings classes for it, as its
 * provider's registerSettings() would at boot.
 *
 * @param list<class-string<AddonSettings>> $classes
 * @param array<string, mixed>              $attributes Addon factory attributes
 */
function registerAddonSettings(string $registryId, array $classes, array $attributes = []): Addon
{
    $addon = Addon::query()->where('registry_id', $registryId)->first()
        ?? Addon::factory()->create(['registry_id' => $registryId, 'enabled' => true] + $attributes);

    AddonSettingsRegistry::register(app(), $registryId, $classes);

    return $addon;
}

/**
 * Write an addon setting row directly, as a save through the addon's settings
 * class would have stored it.
 */
function storeAddonSettingRow(string $registryId, string $name, mixed $payload, string $section = 'general'): void
{
    DB::table('addon_settings')->insert([
        'group'   => $registryId.':'.$section,
        'name'    => $name,
        'payload' => json_encode($payload),
        'locked'  => false,
    ]);
}

/**
 * Whether any setting of the addon is stored.
 */
function addonSettingRowsExist(string $registryId): bool
{
    return DB::table('addon_settings')->where('group', 'like', $registryId.':%')->exists();
}

/**
 * An addon.json payload that passes the registry's manifest schema.
 *
 * The caller supplies at least `registry_id`; any key can be overridden.
 *
 * @param  array<string, mixed> $overrides
 * @return array<string, mixed>
 */
function addonManifest(array $overrides = []): array
{
    return [
        'schema_version' => 1,
        'type'           => 'module',
        'description'    => 'Test addon.',
        ...$overrides,
    ];
}
