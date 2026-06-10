<?php

declare(strict_types=1);

namespace App\Addons\Support;

use App\Addons\Models\AddonBootCache;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Low-level read/write API over the exported boot manifest
 * bootstrap/cache/addons.php (STATE-02, D-10, D-14).
 *
 * Merges the former BootCache (atomic file I/O) and AddonRegistry (cache-backed
 * read helpers) into a single surface. Operates exclusively on the boot cache
 * file — performs NO database reads.
 *
 * Stateless and Octane-safe: reads from disk on every call. Another Octane
 * worker may have rewritten the cache between requests; never cache contents
 * on the instance.
 */
class BootCache
{
    /**
     * Cache schema version. Increment when the on-disk shape changes
     * so stale-schema files are treated as absent (D2-09).
     */
    public const int SCHEMA = 2;

    /**
     * Return enabled addons from the boot cache (DB-free hot path, D-10).
     *
     * Returns an empty collection when the cache is absent.
     *
     * @return Collection<int, AddonBootCache>
     */
    public function enabled(): Collection
    {
        return collect($this->read())
            ->filter(fn (AddonBootCache $entry): bool => $entry->enabled)
            ->values();
    }

    /**
     * Return every addon entry from the boot cache (enabled and disabled).
     *
     * @return Collection<int, AddonBootCache>
     */
    public function all(): Collection
    {
        return collect($this->read());
    }

    /**
     * Absolute path to the boot cache file.
     */
    public function path(): string
    {
        return base_path('bootstrap/cache/addons.php');
    }

    /**
     * Return true if the boot cache file exists (raw file_exists check).
     */
    public function exists(): bool
    {
        return file_exists($this->path());
    }

    /**
     * Return true when the file exists AND its schema matches the current SCHEMA.
     *
     * A stale-schema cache (including Phase-1 bare-list files) returns false (D2-09).
     */
    public function isFresh(): bool
    {
        $data = $this->loadEnvelope();

        return $data !== null && ($data['schema'] ?? null) === self::SCHEMA;
    }

    /**
     * Read the boot cache and return hydrated AddonBootCache rows.
     *
     * Returns an empty array when:
     *  - the file is absent (D-10 absence-only trust), or
     *  - the file's schema !== self::SCHEMA (stale/old-shape cache, D2-09).
     *
     * @return list<AddonBootCache>
     */
    public function read(): array
    {
        $data = $this->loadEnvelope();

        if ($data === null) {
            return [];
        }

        // Phase-1 bare-list file has no 'schema' key — treat as stale.
        if (($data['schema'] ?? null) !== self::SCHEMA) {
            return [];
        }

        $addons = $data['addons'] ?? [];

        if (!is_array($addons)) {
            return [];
        }

        return array_values(
            array_map(
                AddonBootCache::fromArray(...),
                $addons,
            )
        );
    }

    /**
     * Atomically write addon entries to the boot cache (D-14).
     *
     * Wraps rows in a versioned envelope with schema and generated_at fields.
     *
     * Serializes exclusively via var_export() — never string-concatenates
     * addon-controlled values into the PHP body (T-03-01).
     *
     * Uses a per-process temp file in the same directory so rename() is
     * POSIX-atomic and never crosses filesystems (T-03-02, T-03-03).
     *
     * @param list<AddonBootCache> $addons
     */
    public function write(array $addons): void
    {
        $wrapper = [
            'schema'       => self::SCHEMA,
            'generated_at' => gmdate('c'),
            'addons'       => array_map(fn (AddonBootCache $e): array => $e->toArray(), $addons),
        ];

        $content = '<?php'.PHP_EOL.'return '.var_export($wrapper, true).';'.PHP_EOL;

        $tmp = $this->path().'.tmp.'.getmypid().'.'.uniqid();

        file_put_contents($tmp, $content, LOCK_EX);

        if (!rename($tmp, $this->path())) {
            @unlink($tmp);
            throw new RuntimeException('BootCache: failed to atomically rename cache file.');
        }

        // The cache is loaded via require(); under OPcache long-running workers
        // (e.g. Octane) would otherwise keep serving the pre-rename bytecode.
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($this->path(), true);
        }
    }

    /**
     * Delete the boot cache file if it exists.
     */
    public function delete(): void
    {
        if ($this->exists()) {
            unlink($this->path());
        }
    }

    /**
     * Load and return the top-level envelope array from the cache file.
     *
     * Returns null when the file is absent or its content is not an array.
     *
     * @return array<string, mixed>|null
     */
    private function loadEnvelope(): ?array
    {
        if (!$this->exists()) {
            return null;
        }

        $data = require $this->path();

        return is_array($data) ? $data : null;
    }
}
