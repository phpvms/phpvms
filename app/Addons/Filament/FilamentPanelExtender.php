<?php

declare(strict_types=1);

namespace App\Addons\Filament;

use App\Addons\AddonRegistry;
use App\Addons\Models\AddonBootCache;
use Filament\PanelRegistry;

/**
 * Applies cached Filament discovery paths from enabled addons to the
 * registered Filament panels (admin / system).
 *
 * Stateless and Octane-safe: no mutable instance state.
 * Wiring (beforeResolving hook) is handled by AddonServiceProvider — not here.
 */
class FilamentPanelExtender
{
    /** @var array<string, string> Maps component key → Panel discover method name */
    private const array COMPONENT_METHODS = [
        'Resources' => 'discoverResources',
        'Pages'     => 'discoverPages',
        'Widgets'   => 'discoverWidgets',
    ];

    /**
     * @var array<string, string> Maps panel id → namespace segment for the `for:` string.
     *
     * The segment is inserted between the addon namespace and the component
     * name when constructing the fully-qualified namespace prefix.
     */
    private const array PANEL_NAMESPACE_SEGMENT = [
        'admin'  => 'Filament',
        'system' => 'Filament\\System',
    ];

    public function __construct(
        private readonly AddonRegistry $registry,
    ) {}

    /**
     * Return a side-effect-free descriptor of what would be discovered for a
     * single addon entry, keyed by panel id.
     *
     * Only panel keys / component entries present in the entry's filament data
     * are included. Returns an empty array for addons with no Filament data.
     *
     * An entry with an empty namespace is skipped — it would produce a broken
     * leading-double-backslash `for:` string.
     *
     * @return array<string, list<array{method: string, in: string, for: string}>>
     */
    public function discoveriesFor(AddonBootCache $entry): array
    {
        if ($entry->filament === []) {
            return [];
        }

        $ns = rtrim($entry->namespace, '\\');

        if ($ns === '') {
            return [];
        }

        $result = [];

        foreach (self::PANEL_NAMESPACE_SEGMENT as $panelId => $nsSegment) {
            $panelData = $entry->filament[$panelId] ?? [];

            if (empty($panelData)) {
                continue;
            }

            $entries = [];

            foreach (self::COMPONENT_METHODS as $component => $method) {
                if (!isset($panelData[$component])) {
                    continue;
                }

                $entries[] = [
                    'method' => $method,
                    'in'     => $panelData[$component],
                    'for'    => $ns.'\\'.$nsSegment.'\\'.$component,
                ];
            }

            if ($entries !== []) {
                $result[$panelId] = $entries;
            }
        }

        return $result;
    }

    /**
     * Apply each enabled addon's Filament discovery paths to the matching
     * registered panels.
     *
     * Resolves panels via PanelRegistry directly (not the Filament facade) to
     * avoid triggering the beforeResolving('filament') hook recursively.
     *
     * Calling `discoverResources/Pages/Widgets` on a Panel accumulates entries
     * (each call appends). Safe to call when no addon has Filament dirs.
     */
    public function apply(): void
    {
        // Direct PanelRegistry access — DO NOT replace with Filament::getPanels()/app('filament');
        // that resolves the 'filament' binding and re-triggers this beforeResolving('filament') hook recursively.
        $panels = app(PanelRegistry::class)->panels;

        $allowedMethods = array_values(self::COMPONENT_METHODS);

        foreach ($this->registry->enabled() as $entry) {
            $discoveries = $this->discoveriesFor($entry);

            foreach ($discoveries as $panelId => $entries) {
                if (!isset($panels[$panelId])) {
                    continue;
                }

                $panel = $panels[$panelId];

                foreach ($entries as $entry) {
                    $method = $entry['method'];

                    if (!in_array($method, $allowedMethods, strict: true)) {
                        continue;
                    }

                    $panel->{$method}(in: $entry['in'], for: $entry['for']);
                }
            }
        }
    }
}
