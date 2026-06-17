<?php

declare(strict_types=1);

namespace App\Addons\Support;

use App\Addons\Models\AddonManifest;

/**
 * Lenient parser for addon module.json + composer.json manifests.
 *
 * Returns a typed ManifestData on success, or null when the module.json is
 * missing or unparseable (D-15 — caller is responsible for logging + skipping).
 *
 * Stateless and Octane-safe: no mutable instance properties.
 */
class ManifestParser
{
    /**
     * Parse a module directory's manifest files into a typed ManifestData.
     *
     * Returns null when module.json is absent or contains invalid JSON so the
     * caller can skip-and-log rather than crashing boot (D-15).
     */
    public function parse(string $addonPath): ?AddonManifest
    {
        $manifestPath = $addonPath.'/module.json';

        if (!file_exists($manifestPath)) {
            return null;
        }

        $contents = file_get_contents($manifestPath);
        $data = json_decode((string) $contents, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            return null;
        }

        // Decode composer.json once; passed to helpers to avoid triple re-read.
        $composerPath = $addonPath.'/composer.json';
        $composerData = file_exists($composerPath)
            ? (json_decode((string) file_get_contents($composerPath), true) ?? [])
            : [];

        if (!is_array($composerData)) {
            $composerData = [];
        }

        $schema_version = $data['schema_version'] ?? 1;
        $name = $data['name'] ?? basename($addonPath);
        $alias = $data['alias'] ?? null;
        $providers = (array) ($data['providers'] ?? []);

        // --- phpVMS keys (all optional) ---
        $type = $data['type'] ?? 'module'; // D-02: default 'module'
        $compat = $data['compat'] ?? null; // D-05: stored only
        $registryId = $this->resolveRegistryId($data['registry_id'] ?? null); // D-03

        // --- Derived: namespace + version (D-07) ---
        $namespace = $this->resolveNamespace($addonPath, $composerData);
        $version = $this->resolveVersion($data, $composerData);

        // --- Derived: autoloadPath + layout ---
        [$autoloadPath, $layout] = $this->resolveAutoloadPathAndLayout($addonPath, $composerData);

        // --- description: null when absent or blank ---
        $description = $this->resolveDescription($data['description'] ?? null);

        // --- database.tables: addon-owned tables for uninstall (D-16) ---
        $tables = $this->resolveTables($data);

        // --- autoload.files: module global helper files loaded at runtime ---
        $files = $this->resolveFiles($addonPath, $composerData);

        return new AddonManifest(
            schema_version: $schema_version,
            name: (string) $name,
            alias: $alias !== null ? (string) $alias : null,
            type: (string) $type,
            compat: $compat !== null ? (string) $compat : null,
            registryId: $registryId,
            version: $version,
            namespace: $namespace,
            providers: $providers,
            path: $addonPath,
            raw: $data,
            autoloadPath: $autoloadPath,
            layout: $layout,
            description: $description,
            tables: $tables,
            files: $files,
        );
    }

    /**
     * Resolve the addon's composer.json `autoload.files` into absolute paths.
     *
     * Each non-blank string entry is joined onto the addon directory. Entries
     * that escape the addon directory (e.g. "../../app/helpers.php") are
     * rejected so an addon manifest can never point the loader at core code.
     * Returns an empty list when the key is absent or malformed.
     *
     * @param  array<string, mixed> $composerData Pre-decoded composer.json data.
     * @return list<string>
     */
    private function resolveFiles(string $addonPath, array $composerData): array
    {
        $files = $composerData['autoload']['files'] ?? null;

        if (!is_array($files)) {
            return [];
        }

        $base = $this->normalizePath($addonPath);
        $resolved = [];

        foreach ($files as $file) {
            if (!is_string($file)) {
                continue;
            }

            $trimmed = trim($file);

            if ($trimmed === '') {
                continue;
            }

            $absolute = $addonPath.'/'.ltrim($trimmed, '/');

            if (!$this->isWithin($base, $absolute)) {
                continue;
            }

            if (!in_array($absolute, $resolved, true)) {
                $resolved[] = $absolute;
            }
        }

        return $resolved;
    }

    /**
     * Lexically normalise a path, resolving "." and ".." segments without
     * touching the filesystem.
     *
     * Unlike realpath(), this works on paths whose target does not yet exist,
     * so a "../" traversal can never slip through the boundary check just
     * because the file it points at is currently missing. Both "/" and "\"
     * separators are accepted; the result always uses "/".
     */
    private function normalizePath(string $path): string
    {
        $isAbsolute = str_starts_with($path, '/') || str_starts_with($path, '\\');
        $segments = preg_split('#[\\\\/]+#', $path) ?: [];
        $out = [];

        foreach ($segments as $segment) {
            if ($segment === '') {
                continue;
            }

            if ($segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($out);

                continue;
            }

            $out[] = $segment;
        }

        return ($isAbsolute ? '/' : '').implode('/', $out);
    }

    /**
     * Whether $candidate resolves to a location inside the already-normalised
     * $base directory. Comparison is lexical (see normalizePath).
     */
    private function isWithin(string $base, string $candidate): bool
    {
        $normalized = $this->normalizePath($candidate);

        return $normalized === $base || str_starts_with($normalized.'/', $base.'/');
    }

    /**
     * Resolve the addon-owned database tables from module.json `database.tables`.
     *
     * Returns a de-duplicated list of non-blank table names, or an empty list
     * when the key is absent or malformed (D-16). Drives table removal on
     * uninstall; an empty list means the addon declares no contract and the
     * caller falls back to rolling back its migrations.
     *
     * @param  array<string, mixed> $data Decoded module.json data.
     * @return list<string>
     */
    private function resolveTables(array $data): array
    {
        $tables = $data['database']['tables'] ?? null;

        if (!is_array($tables)) {
            return [];
        }

        $resolved = [];

        foreach ($tables as $table) {
            if (!is_string($table)) {
                continue;
            }

            $trimmed = trim($table);

            if ($trimmed !== '' && !in_array($trimmed, $resolved, true)) {
                $resolved[] = $trimmed;
            }
        }

        return $resolved;
    }

    /**
     * Normalise registry_id: treat blank/whitespace-only strings as null (D-03).
     */
    private function resolveRegistryId(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : null;
    }

    /**
     * Resolve the PSR-4 root namespace (D-07).
     *
     * Uses the pre-decoded $composerData array; takes the first autoload.psr-4
     * key (rtrimmed of trailing backslash). Falls back to 'Modules\{Name}'.
     *
     * @param array<string, mixed> $composerData Pre-decoded composer.json data.
     */
    private function resolveNamespace(string $addonPath, array $composerData): string
    {
        $psr4 = $composerData['autoload']['psr-4'] ?? [];

        if (is_array($psr4) && $psr4 !== []) {
            $firstKey = (string) array_key_first($psr4);
            $resolved = rtrim($firstKey, '\\');

            if ($resolved !== '') {
                return $resolved;
            }
        }

        return 'Modules\\'.basename($addonPath);
    }

    /**
     * Resolve the addon version (D-07).
     *
     * Preference order: module.json 'version' → composer.json 'version' → null.
     *
     * @param array<string, mixed> $data         Decoded module.json data.
     * @param array<string, mixed> $composerData Pre-decoded composer.json data.
     */
    private function resolveVersion(array $data, array $composerData): ?string
    {
        if (isset($data['version']) && trim((string) $data['version']) !== '') {
            return (string) $data['version'];
        }

        if (isset($composerData['version']) && trim((string) $composerData['version']) !== '') {
            return (string) $composerData['version'];
        }

        return null;
    }

    /**
     * Resolve the PSR-4 autoload path and layout from pre-decoded composer.json data.
     *
     * The first psr-4 entry value (e.g. "." or "app/") determines the layout:
     *  - 'app' layout: normalised value equals 'app' (case-insensitive) or begins with 'app/'.
     *  - 'root' layout: everything else (including empty string / ".").
     *
     * Falls back to autoloadPath = $addonPath, layout = 'root' when no psr-4
     * entry is present.
     *
     * @param  array<string, mixed>        $composerData Pre-decoded composer.json data.
     * @return array{0: string, 1: string} [autoloadPath, layout]
     */
    private function resolveAutoloadPathAndLayout(string $addonPath, array $composerData): array
    {
        $psr4 = $composerData['autoload']['psr-4'] ?? [];

        if (is_array($psr4) && $psr4 !== []) {
            $firstValue = (string) array_values($psr4)[0];
            $normalised = rtrim(trim($firstValue), '/');

            // Treat "" or "." as the addon root.
            if ($normalised === '' || $normalised === '.') {
                return [$addonPath, 'root'];
            }

            $autoloadPath = $addonPath.'/'.$normalised;

            // Reject psr-4 values that escape the addon directory (e.g.
            // "../../app"), which would point the PSR-4 loader at core code.
            // Lexical check so a non-existent path can't slip through.
            if (!$this->isWithin($this->normalizePath($addonPath), $autoloadPath)) {
                return [$addonPath, 'root'];
            }

            $layout = (strtolower($normalised) === 'app' || str_starts_with(strtolower($normalised), 'app/'))
                ? 'app'
                : 'root';

            return [$autoloadPath, $layout];
        }

        return [$addonPath, 'root'];
    }

    /**
     * Normalise description: treat null, blank, or whitespace-only strings as null.
     */
    private function resolveDescription(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
