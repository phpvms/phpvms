<?php

declare(strict_types=1);

namespace App\Support\Extensions;

use App\Enums\Slot;
use InvalidArgumentException;

/**
 * Server-side registry of SLOT entries — components injected into named
 * extension points ("slots") on first-party theme pages (e.g. the flight-detail
 * page's `flights.detail.actions` slot). Core slots are the {@see Slot} cases;
 * addons may also define their own slots by plain string.
 *
 * This is the page-level counterpart to the widget board: a widget is placed by
 * the PILOT, a slot entry is injected by an ADDON and always renders wherever the
 * page exposes a matching `<PvSlot name="…">`. Same disable-safety as widgets —
 * a disabled addon's provider never boots, so its slot entries never register.
 *
 * Entries are merged into the SPA's built-in slot registry (lib/registry.ts) via
 * the Inertia shared props. Vue components are resolved exactly like widgets:
 * bundled `component` name first, else a pre-built ESM `module` URL imported at
 * runtime.
 */
final class SlotRegistry
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private array $entries = [];

    /**
     * Register a slot entry.
     *
     * Recognised keys:
     *   slot      Slot|string  required. Named outlet to fill, e.g. Slot::FlightsDetailActions.
     *                          A Slot case is stored as its string value.
     *   component string  bundled resolver-map name (first-party) OR the export
     *                     name of a third-party ESM module.
     *   module    string  URL of a pre-built ESM module for a third-party entry
     *                     (e.g. '/ext/samplevueslot/widgets/slot.js'). If set,
     *                     imported at runtime; REQUIRES a string `component`
     *                     (the resolver key it is imported under).
     *   order     int     ascending render order within the slot (default 100).
     *   props     array   props to pass; a string value starting with '@' is a
     *                     ref resolved against the page DTO (e.g. '@flight').
     *
     * @param array<string, mixed> $entry
     */
    public function register(array $entry): self
    {
        if (($entry['slot'] ?? null) instanceof Slot) {
            $entry['slot'] = $entry['slot']->value;
        }

        if (empty($entry['slot']) || !is_string($entry['slot'])) {
            throw new InvalidArgumentException('Slot entry requires a string "slot".');
        }

        // A `module` entry is imported at runtime under its `component` name (the
        // resolver key). Without a string component the SPA would key the resolver
        // with `undefined` and the slot could never resolve — fail loudly here.
        if (!empty($entry['module']) && (empty($entry['component']) || !is_string($entry['component']))) {
            throw new InvalidArgumentException('A slot entry with a "module" requires a string "component" (the resolver key it is imported under).');
        }

        $entry['order'] ??= 100;

        $this->entries[] = $entry;

        return $this;
    }

    /**
     * All registered slot entries as a flat, JSON-serializable list — the exact
     * shape merged into the SPA registry.
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->entries;
    }
}
