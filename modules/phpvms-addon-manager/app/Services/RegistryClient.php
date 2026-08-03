<?php

declare(strict_types=1);

namespace Modules\AddonManager\Services;

use App\Services\VersionService;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Read access to the phpVMS addon registry.
 *
 * Fetches the whole catalog once (`GET /v1/packages`) and caches it; search,
 * filter, and sort run in-memory in the Livewire page. The cached payload is
 * stored without an expiry so a failed refresh can still serve the last-known
 * catalog (stale) — freshness is tracked by the stored `synced_at` against the
 * configured TTL rather than by cache eviction.
 *
 * Tolerant of a registry that omits `icon`, `screenshots`, or `stats` (those
 * fields arrive with the companion `registry-client-support` change).
 */
class RegistryClient
{
    private const string CATALOG_KEY = 'addon-manager:catalog';

    private const string REFRESH_ATTEMPT_KEY = 'addon-manager:catalog:attempted_at';

    private const int REFRESH_THROTTLE_SECONDS = 60;

    private const string RELEASE_KEY_PREFIX = 'addon-manager:release:';

    public function __construct(private readonly VersionService $versions) {}

    /**
     * Mint a signed, time-limited download for a specific version. Sends the
     * install's identity, domain, and phpVMS version. Returns the raw response
     * body and `Registry-Signature` header UNVERIFIED — the caller must verify
     * the signature over the raw body before trusting the `url`/`sha256` it
     * carries. Non-2xx statuses (404 yanked, 503 not-ready) are returned, not
     * thrown, so the caller can map them to user-facing messages.
     *
     * @return array{status: int, body: string, signature: ?string}
     */
    public function mintDownload(string $registryId, string $version): array
    {
        [$author, $package] = $this->splitRegistryId($registryId);

        if ($author === null || $package === null) {
            throw new InvalidArgumentException('Invalid registry id: '.$registryId);
        }

        $response = Http::timeout($this->timeout())
            ->connectTimeout(3)
            ->acceptJson()
            ->post($this->url(sprintf('/v1/releases/%s/%s/%s/download', $author, $package, $version)), [
                'va_global_id'   => (string) setting('va_global_id'),
                'domain'         => $this->appDomain(),
                'phpvms_version' => $this->versions->getCurrentVersion(false),
            ]);

        return [
            'status'    => $response->status(),
            'body'      => $response->body(),
            'signature' => $response->header('Registry-Signature'),
        ];
    }

    /**
     * The normalized host of the configured app URL — the `domain` the registry
     * attributes the install to (the job has no request context).
     */
    private function appDomain(): string
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        return is_string($host) ? strtolower($host) : '';
    }

    /**
     * Return the cached catalog, refreshing when stale (or when forced).
     *
     * @return array{entries: array<string, array<string, mixed>>, synced_at: ?string, stale: bool, error: ?string}
     */
    public function catalog(bool $forceRefresh = false): array
    {
        $cached = $this->cache()->get(self::CATALOG_KEY);
        $hasCache = is_array($cached);

        $syncedAt = ($hasCache && isset($cached['synced_at']) && is_string($cached['synced_at'])) ? $cached['synced_at'] : null;

        if (!$forceRefresh && $syncedAt !== null && $this->isFresh($syncedAt)) {
            return $this->result($cached, stale: false);
        }

        // Throttle refresh attempts so a stale or unreachable registry can't make
        // every admin render block on a live (timing-out) fetch — at most one
        // attempt per window. A manual refresh / cron (forceRefresh) bypasses it.
        // This must short-circuit even with no cache yet, otherwise a down
        // registry is re-fetched on every single call.
        if (!$forceRefresh && $this->cache()->has(self::REFRESH_ATTEMPT_KEY)) {
            return $hasCache
                ? $this->result($cached, stale: true)
                : ['entries' => [], 'synced_at' => null, 'stale' => true, 'error' => null];
        }

        if (!$forceRefresh) {
            $this->cache()->put(self::REFRESH_ATTEMPT_KEY, true, now()->addSeconds(self::REFRESH_THROTTLE_SECONDS));
        }

        try {
            $entries = $this->fetchCatalog();
        } catch (Throwable $throwable) {
            Log::warning('RegistryClient: catalog fetch failed: '.$throwable->getMessage());

            // Serve the last catalog stale when we have one; otherwise surface
            // an error state the Browse tab can render.
            if ($hasCache) {
                return $this->result($cached, stale: true);
            }

            return ['entries' => [], 'synced_at' => null, 'stale' => false, 'error' => $throwable->getMessage()];
        }

        $payload = ['synced_at' => now()->toIso8601String(), 'entries' => $entries];
        $this->cache()->forever(self::CATALOG_KEY, $payload);

        return $this->result($payload, stale: false);
    }

    /**
     * Read-only cached catalog for callers that must NEVER trigger a network
     * fetch — the nav badge renders on every admin page, so it reads the
     * last-known entries (empty until the Addons page or the scheduled
     * `addons:check-updates` command populates them) rather than risk blocking
     * an unrelated admin request on the registry.
     *
     * @return array{entries: array<string, array<string, mixed>>, synced_at: ?string, stale: bool, error: ?string}
     */
    public function cachedCatalog(): array
    {
        $cached = $this->cache()->get(self::CATALOG_KEY);

        if (!is_array($cached)) {
            return ['entries' => [], 'synced_at' => null, 'stale' => true, 'error' => null];
        }

        $syncedAt = (isset($cached['synced_at']) && is_string($cached['synced_at'])) ? $cached['synced_at'] : null;

        return $this->result($cached, stale: $syncedAt === null || !$this->isFresh($syncedAt));
    }

    /**
     * Force a re-fetch (manual refresh button / cron).
     *
     * @return array{entries: array<string, array<string, mixed>>, synced_at: ?string, stale: bool, error: ?string}
     */
    public function refresh(): array
    {
        return $this->catalog(forceRefresh: true);
    }

    /**
     * Lazily fetch release metadata for the detail pane (channel, release
     * history, package size). Returns null when the fetch fails so the pane can
     * render the catalog fields without it.
     *
     * @return array<string, mixed>|null
     */
    public function releaseMetadata(string $registryId): ?array
    {
        [$author, $package] = $this->splitRegistryId($registryId);

        if ($author === null || $package === null) {
            return null;
        }

        return $this->cache()->remember(
            self::RELEASE_KEY_PREFIX.$registryId,
            now()->addMinutes(10),
            function () use ($author, $package): ?array {
                try {
                    // Detail-pane fetch on row selection: fail fast (short connect
                    // timeout) so an unreachable registry never stalls the UI.
                    $response = Http::timeout($this->timeout())
                        ->connectTimeout(3)
                        ->acceptJson()
                        ->get($this->url(sprintf('/v1/releases/%s/%s', $author, $package)));

                    if (!$response->successful()) {
                        return null;
                    }

                    $data = $response->json();

                    return is_array($data) ? $data : null;
                } catch (Throwable $throwable) {
                    Log::warning('RegistryClient: release metadata fetch failed: '.$throwable->getMessage());

                    return null;
                }
            },
        );
    }

    /**
     * Fetch and normalize the full catalog, keyed by `registry_id`.
     *
     * @return array<string, array<string, mixed>>
     */
    private function fetchCatalog(): array
    {
        $response = Http::timeout($this->timeout())
            ->connectTimeout(3)
            ->acceptJson()
            ->get($this->url('/v1/packages'));

        if (!$response->successful()) {
            throw new RuntimeException(sprintf('Registry returned HTTP %d', $response->status()));
        }

        /** @var array<int, array<string, mixed>> $rows */
        $rows = $response->json('data', []);

        $entries = [];

        foreach ($rows as $row) {
            // Canonical identity is `registry_id` (author/package), matching the
            // installed addons' column. Tolerate camelCase and, as a last resort,
            // a flat `name` so packages still surface.
            $registryId = (string) ($row['registry_id'] ?? $row['registryId'] ?? $row['name'] ?? '');

            if ($registryId === '') {
                continue;
            }

            $entries[$registryId] = $this->normalizeEntry($registryId, $row);
        }

        return $entries;
    }

    /**
     * Normalize a catalog row into the shape the UI consumes, defaulting the
     * optional media/stats fields so callers never have to null-check them.
     *
     * @param  array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeEntry(string $registryId, array $row): array
    {
        $versions = is_array($row['versions'] ?? null) ? $row['versions'] : [];
        $stats = is_array($row['stats'] ?? null) ? $row['stats'] : [];
        $screenshots = is_array($row['screenshots'] ?? null) ? array_values($row['screenshots']) : [];

        return [
            'registry_id'   => $registryId,
            'name'          => (string) ($row['name'] ?? $registryId),
            'description'   => (string) ($row['description'] ?? ''),
            'category'      => (string) ($row['category'] ?? ''),
            'license'       => (string) ($row['license'] ?? ''),
            'keywords'      => is_array($row['keywords'] ?? null) ? array_values($row['keywords']) : [],
            'publisher'     => (string) ($row['publisher'] ?? ''),
            'publisher_url' => (string) ($row['publisherUrl'] ?? $row['publisher_url'] ?? ''),
            // Tolerate camelCase (contract) or snake_case (older registry builds).
            'repository_url' => (string) ($row['repositoryUrl'] ?? $row['repository_url'] ?? ''),
            // Store bare minimums: tolerate a registry that still sends composer
            // constraints (">=8.4") by stripping the leading operator. Version
            // handling lives in CompatibilityEvaluator, not hand-rolled here.
            'min_php'        => CompatibilityEvaluator::normalizeMin((string) ($versions['php'] ?? '')),
            'min_phpvms'     => CompatibilityEvaluator::normalizeMin((string) ($versions['phpvms'] ?? '')),
            'version'        => (string) ($row['version'] ?? ''),
            'icon'           => isset($row['icon']) ? (string) $row['icon'] : null,
            'screenshots'    => $screenshots,
            'installs_total' => (int) ($stats['installs_total'] ?? 0),
        ];
    }

    /**
     * @param  array{synced_at: string, entries: array<string, array<string, mixed>>}                               $payload
     * @return array{entries: array<string, array<string, mixed>>, synced_at: ?string, stale: bool, error: ?string}
     */
    private function result(array $payload, bool $stale): array
    {
        return [
            'entries'   => is_array($payload['entries'] ?? null) ? $payload['entries'] : [],
            'synced_at' => is_string($payload['synced_at'] ?? null) ? $payload['synced_at'] : null,
            'stale'     => $stale,
            'error'     => null,
        ];
    }

    private function isFresh(string $syncedAt): bool
    {
        return now()->diffInSeconds($syncedAt, absolute: true) < $this->ttl();
    }

    /**
     * Split a `author/package` registry id.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function splitRegistryId(string $registryId): array
    {
        $parts = explode('/', $registryId, 2);

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return [null, null];
        }

        return [$parts[0], $parts[1]];
    }

    private function url(string $path): string
    {
        return rtrim((string) config('addon-manager.registry_url'), '/').$path;
    }

    private function ttl(): int
    {
        return (int) config('addon-manager.catalog_ttl', 6 * 60 * 60);
    }

    private function timeout(): int
    {
        return (int) config('addon-manager.http_timeout', 20);
    }

    /**
     * The persistent cache store for registry data. The catalog and release
     * metadata are fetched on admin-panel load and kept on disk so page
     * interactions never re-hit the network. Honours an explicit config; else
     * the app default — unless that is the non-persistent `array` driver (dev),
     * in which case `file` (storage/framework/cache) is used so it still persists.
     */
    private function store(): ?string
    {
        $configured = config('addon-manager.cache_store');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return config('cache.default') === 'array' ? 'file' : null;
    }

    private function cache(): Repository
    {
        return Cache::store($this->store());
    }
}
