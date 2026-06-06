<?php

declare(strict_types=1);

namespace App\Addons\Models;

/**
 * Typed representation of a single row in the addon boot cache (bootstrap/cache/addons.php).
 *
 * The on-disk format remains a plain PHP array (var_export). Use fromArray() to
 * hydrate when reading and toArray() to serialize back before writing.
 */
final readonly class AddonRuntime
{
    /**
     * @param string                               $name         Human-readable module name.
     * @param string|null                          $alias        Short machine alias; null when absent.
     * @param string                               $type         Addon type ('module', 'theme', etc.).
     * @param string|null                          $registryId   Registry canonical identity; null for bundled addons.
     * @param string|null                          $version      Version string; null when absent.
     * @param string                               $namespace    PSR-4 root namespace.
     * @param list<string>                         $providers    Service-provider class names.
     * @param string                               $path         Absolute path to the addon directory.
     * @param string                               $autoloadPath Absolute path the PSR-4 namespace resolves to.
     * @param string                               $layout       Layout hint: 'root' or 'app'.
     * @param string|null                          $description  Human-readable description; null when absent.
     * @param bool                                 $enabled      Whether the addon is active.
     * @param array<string, array<string, string>> $filament     Panel → component → directory map.
     */
    public function __construct(
        public string $name,
        public ?string $alias,
        public string $type,
        public ?string $registryId,
        public ?string $version,
        public string $namespace,
        public array $providers,
        public string $path,
        public string $autoloadPath,
        public string $layout,
        public ?string $description,
        public bool $enabled,
        public array $filament,
    ) {}

    /**
     * Hydrate from a plain array row (as stored in the boot cache file).
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) ($data['name'] ?? ''),
            alias: isset($data['alias']) ? (string) $data['alias'] : null,
            type: (string) ($data['type'] ?? 'module'),
            registryId: isset($data['registry_id']) ? (string) $data['registry_id'] : null,
            version: isset($data['version']) ? (string) $data['version'] : null,
            namespace: (string) ($data['namespace'] ?? ''),
            providers: array_values(array_filter((array) ($data['providers'] ?? []), 'is_string')),
            path: (string) ($data['path'] ?? ''),
            autoloadPath: (string) ($data['autoload_path'] ?? ''),
            layout: (string) ($data['layout'] ?? 'app'),
            description: isset($data['description']) ? (string) $data['description'] : null,
            enabled: (bool) ($data['enabled'] ?? false),
            filament: (array) ($data['filament'] ?? []),
        );
    }

    /**
     * Serialize to a plain array for var_export() into the boot cache file.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name'          => $this->name,
            'alias'         => $this->alias,
            'type'          => $this->type,
            'registry_id'   => $this->registryId,
            'version'       => $this->version,
            'namespace'     => $this->namespace,
            'providers'     => $this->providers,
            'path'          => $this->path,
            'autoload_path' => $this->autoloadPath,
            'layout'        => $this->layout,
            'description'   => $this->description,
            'enabled'       => $this->enabled,
            'filament'      => $this->filament,
        ];
    }
}
