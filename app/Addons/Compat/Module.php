<?php

declare(strict_types=1);

namespace App\Addons\Compat;

use App\Addons\ManifestParser;
use App\Addons\Models\ManifestData;
use App\Addons\PrimeService;
use App\Models\Addon;
use Illuminate\Support\Str;

/**
 * Compatibility shim wrapping an Addon model to satisfy the duck-typed
 * call surface previously provided by \Nwidart\Modules\Module.
 *
 * Lazy-parses module.json once per instance for name/description.
 * Octane-safe: instances are request-scoped value objects — no static
 * or global accumulators.
 */
class Module
{
    /** @var ManifestData|false|null false = parse attempted but failed; null = not yet parsed */
    private ManifestData|false|null $manifest = null;

    public function __construct(
        private readonly Addon $addon,
        private readonly ManifestParser $parser,
    ) {}

    /**
     * The manifest name, falling back to basename(path).
     */
    public function getName(): string
    {
        return $this->resolveManifest()?->name ?? basename($this->addon->path);
    }

    /**
     * Lowercase module name.
     */
    public function getLowerName(): string
    {
        return strtolower($this->getName());
    }

    /**
     * StudlyCase module name (mirrors nwidart Module::getStudlyName()).
     */
    public function getStudlyName(): string
    {
        return Str::studly($this->getName());
    }

    /**
     * Absolute filesystem path to the addon directory.
     */
    public function getPath(): string
    {
        return $this->addon->path;
    }

    /**
     * Absolute path to a sub-path within the addon directory.
     */
    public function getExtraPath(string $path): string
    {
        return $this->getPath().'/'.$path;
    }

    /**
     * Manifest description; null when absent or blank.
     */
    public function getDescription(): ?string
    {
        return $this->resolveManifest()?->description;
    }

    /**
     * Whether the addon is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->addon->enabled;
    }

    /**
     * Set the addon's enabled state, persist to DB, and regenerate the boot cache.
     */
    public function setActive(bool $active): void
    {
        $this->addon->enabled = $active;
        $this->addon->save();

        app(PrimeService::class)->run();
    }

    /**
     * Enable the addon.
     */
    public function enable(): void
    {
        $this->setActive(true);
    }

    /**
     * Remove the addon's DB row and regenerate the boot cache.
     *
     * Does NOT delete files on disk (full lifecycle handled in Phase 5).
     */
    public function delete(): void
    {
        $this->addon->delete();

        app(PrimeService::class)->run();
    }

    /**
     * Magic property access for $shim->name and $shim->description.
     *
     * Unknown properties return null intentionally for nwidart compatibility —
     * nwidart Module exposes many public properties and callers may access them
     * without checking; null is a safe no-op sentinel.
     */
    public function __get(string $key): mixed
    {
        return match ($key) {
            'name'        => $this->getName(),
            'description' => $this->getDescription(),
            default       => null,
        };
    }

    /**
     * Magic isset for property checks.
     */
    public function __isset(string $key): bool
    {
        return in_array($key, ['name', 'description'], true);
    }

    /**
     * Lazy-parse the manifest once per instance.
     *
     * Returns null when the manifest is missing or invalid.
     */
    private function resolveManifest(): ?ManifestData
    {
        if ($this->manifest === null) {
            $parsed = $this->parser->parse($this->addon->path);
            $this->manifest = $parsed instanceof ManifestData ? $parsed : false;
        }

        return $this->manifest instanceof ManifestData ? $this->manifest : null;
    }
}
