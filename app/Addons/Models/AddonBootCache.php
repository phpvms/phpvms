<?php

declare(strict_types=1);

namespace App\Addons\Models;

/**
 * Typed representation of a single row in the addon boot cache (bootstrap/cache/addons.php).
 *
 * The on-disk format remains a plain PHP array (var_export). Use fromArray() to
 * hydrate when reading and toArray() to serialize back before writing.
 */
final readonly class AddonBootCache
{
    /**
     * @param string                                                                                             $name         Human-readable module name.
     * @param string|null                                                                                        $alias        Short machine alias; null when absent.
     * @param string                                                                                             $type         Addon type ('module', 'theme', etc.).
     * @param string                                                                                             $registryId   Registry canonical identity; required (MAN-03).
     * @param string|null                                                                                        $version      Version string; null when absent.
     * @param string                                                                                             $namespace    PSR-4 root namespace.
     * @param list<string>                                                                                       $providers    Service-provider class names.
     * @param string                                                                                             $path         Absolute path to the addon directory.
     * @param string                                                                                             $autoloadPath Absolute path the PSR-4 namespace resolves to.
     * @param string                                                                                             $layout       Layout hint: 'root' or 'app'.
     * @param string|null                                                                                        $description  Human-readable description; null when absent.
     * @param bool                                                                                               $enabled      Whether the addon is active.
     * @param list<string>                                                                                       $files        Absolute paths declared under composer.json
     *                                                                                                                         `autoload.files`, required once per boot cycle.
     * @param array{listeners: list<class-string>, hooks: list<class-string>, policies: list<class-string>}|null $discovered
     *                                                                                                                         Classes found in `app/Listeners`, `app/Hooks` and `app/Policies`
     *                                                                                                                         when the cache was built; null for a cache written before they were saved.
     * @param string|null                                                                                        $icon         The manifest `icon`; null when absent.
     */
    public function __construct(
        public string $name,
        public ?string $alias,
        public string $type,
        public string $registryId,
        public ?string $version,
        public string $namespace,
        public array $providers,
        public string $path,
        public string $autoloadPath,
        public string $layout,
        public ?string $description,
        public bool $enabled,
        public array $files = [],
        public ?array $discovered = null,
        public ?string $icon = null,
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
            registryId: (string) ($data['registry_id'] ?? ''),
            version: isset($data['version']) ? (string) $data['version'] : null,
            namespace: (string) ($data['namespace'] ?? ''),
            providers: array_values(array_filter((array) ($data['providers'] ?? []), is_string(...))),
            path: (string) ($data['path'] ?? ''),
            autoloadPath: (string) ($data['autoload_path'] ?? ''),
            layout: (string) ($data['layout'] ?? 'app'),
            description: isset($data['description']) ? (string) $data['description'] : null,
            enabled: (bool) ($data['enabled'] ?? false),
            files: array_values(array_filter((array) ($data['files'] ?? []), is_string(...))),
            discovered: self::discoveredFrom($data['discovered'] ?? null),
            icon: isset($data['icon']) ? (string) $data['icon'] : null,
        );
    }

    /**
     * @return array{listeners: list<class-string>, hooks: list<class-string>, policies: list<class-string>}|null
     */
    private static function discoveredFrom(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }

        return [
            'listeners' => array_values(array_filter((array) ($value['listeners'] ?? []), is_string(...))),
            'hooks'     => array_values(array_filter((array) ($value['hooks'] ?? []), is_string(...))),
            'policies'  => array_values(array_filter((array) ($value['policies'] ?? []), is_string(...))),
        ];
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
            'files'         => $this->files,
            'discovered'    => $this->discovered,
            'icon'          => $this->icon,
        ];
    }
}
