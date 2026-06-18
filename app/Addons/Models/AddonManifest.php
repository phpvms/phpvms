<?php

declare(strict_types=1);

namespace App\Addons\Models;

/**
 * Immutable parse-result contract returned by ManifestParser.
 *
 * All fields mirror the addon's module.json (nwidart keys + phpVMS keys) plus
 * derived values (namespace, version) resolved from composer.json per D-07.
 *
 * Identity rules (D-03 / D-04):
 *   - registryId is null for bundled/unmanaged addons (registry_id absent or blank).
 *   - For bundled addons, name/alias are the human identity.
 *   - For managed addons, registryId is the canonical identity (MAN-03).
 */
final readonly class AddonManifest
{
    /**
     * @param int|null             $schema_version Schema version of the module.json
     * @param string               $name           Namespace name of the module
     * @param string|null          $alias          Short machine alias, used for views
     * @param string               $type           Addon type: 'module', 'theme', etc. (D-02:
     *                                             defaults to 'module').
     * @param string|null          $registryId     Registry canonical identity; null for bundled
     *                                             addons (D-03).
     * @param string|null          $compat         phpVMS version constraint (stored only in Phase
     *                                             1, D-05).
     * @param string|null          $version        Version string; null when absent from both
     *                                             manifests (D-07).
     * @param string               $namespace      PSR-4 root namespace resolved from
     *                                             composer.json, else fallback (D-07).
     * @param list<string>         $providers      List of Laravel service-provider class names.
     * @param string               $path           Absolute filesystem path to the addon directory.
     * @param array<string, mixed> $raw            The decoded module.json array, kept for
     *                                             forward-compat.
     * @param string               $autoloadPath   Absolute filesystem path the PSR-4 namespace
     *                                             resolves to.
     * @param string               $layout         Addon layout: always lowercase 'root' or 'app'.
     * @param string|null          $description    Human-readable description from module.json;
     *                                             null when absent or blank.
     * @param list<string>         $tables         Database tables owned by the addon, declared
     *                                             under module.json `database.tables`. Used to
     *                                             drop the addon's tables on uninstall; empty when
     *                                             undeclared (D-16).
     * @param list<string>         $files          Absolute filesystem paths declared under
     *                                             composer.json `autoload.files`. Loaded once per
     *                                             boot cycle so module global helpers are available;
     *                                             empty when undeclared.
     *
     * @mago-ignore lint:excessive-parameter-list
     */
    public function __construct(
        public ?int $schema_version,
        public string $name,
        public ?string $alias,
        public string $type,
        public ?string $compat,
        public ?string $registryId,
        public ?string $version,
        public string $namespace,
        public array $providers,
        public string $path,
        public array $raw,
        public string $autoloadPath,
        public string $layout,
        public ?string $description,
        public array $tables = [],
        public array $files = [],
    ) {}

    public function slug(): string
    {
        return trim(strtolower(str_replace('/', '-', $this->registryId)));
    }

    public function dirName(): string
    {
        return $this->slug();
    }
}
