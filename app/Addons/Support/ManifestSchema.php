<?php

declare(strict_types=1);

namespace App\Addons\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use stdClass;
use Throwable;

/**
 * The registry's addon manifest schema, kept as a local file.
 *
 * The file is re-downloaded once it is older than the configured TTL. A failed
 * refresh keeps the old file, so a registry outage never blocks addon discovery.
 *
 * Bound scoped (Octane): the decoded schema is memoised per instance.
 */
class ManifestSchema
{
    private ?object $schema = null;

    private bool $loaded = false;

    /**
     * The decoded schema, or null when none is available.
     */
    public function schema(): ?object
    {
        if ($this->loaded) {
            return $this->schema;
        }

        $path = (string) config('addons.schema.path');

        if (!is_file($path) || $this->isStale($path)) {
            $this->download($path);
        }

        $this->schema = $this->read($path);
        $this->loaded = true;

        return $this->schema;
    }

    private function isStale(string $path): bool
    {
        $mtime = filemtime($path);

        return $mtime === false || (time() - $mtime) > (int) config('addons.schema.ttl');
    }

    private function download(string $path): void
    {
        $url = (string) config('addons.schema.url');

        try {
            $body = Http::timeout(5)->connectTimeout(3)->get($url)->throw()->body();
        } catch (Throwable $throwable) {
            Log::warning(sprintf('ManifestSchema: could not download %s: %s', $url, $throwable->getMessage()));

            return;
        }

        // Never replace a good file with an error page or truncated body.
        if (!json_decode($body) instanceof stdClass) {
            Log::warning(sprintf('ManifestSchema: %s did not return a JSON object', $url));

            return;
        }

        // Write beside the target and rename, so a concurrent reader never sees a
        // partial file and a failed write leaves the old schema in place. This runs
        // during boot, so a filesystem failure must not escape.
        try {
            File::ensureDirectoryExists(dirname($path));
            $temporary = $path.'.'.bin2hex(random_bytes(4)).'.tmp';
            File::put($temporary, $body);

            if (!rename($temporary, $path)) {
                @unlink($temporary);
                Log::warning(sprintf('ManifestSchema: could not replace %s', $path));
            }
        } catch (Throwable $throwable) {
            Log::warning(sprintf('ManifestSchema: could not write %s: %s', $path, $throwable->getMessage()));
        }
    }

    private function read(string $path): ?object
    {
        if (!is_file($path)) {
            Log::warning('ManifestSchema: no addon manifest schema available, skipping schema validation');

            return null;
        }

        $schema = json_decode((string) file_get_contents($path));

        if (!$schema instanceof stdClass) {
            Log::warning(sprintf('ManifestSchema: %s is not a JSON object, skipping schema validation', $path));

            return null;
        }

        return $schema;
    }
}
