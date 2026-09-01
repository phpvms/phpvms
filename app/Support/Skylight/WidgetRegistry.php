<?php

declare(strict_types=1);

namespace App\Support\Skylight;

use InvalidArgumentException;

/**
 * Server-side registry of dashboard widgets contributed to the skylight SPA —
 * and, via the `tag` kind, the component catalog other consumers (e.g. a page
 * editor's insert menu) read from.
 *
 * This is the SINGLE source of truth for "what widgets can the pilot place on
 * the dashboard board" and "what custom elements exist to be authored into
 * markup". First-party widgets and addon widgets register the same way. Because
 * an addon only registers from its ServiceProvider::boot(), and a DISABLED
 * addon's provider never boots, a disabled addon's widgets simply never appear
 * here — no catalog edit, no client change, no dead entry. Disable-safety is a
 * property of the registration mechanism, not a runtime `enabled` check.
 *
 * The registry is serialized into the Inertia shared props (see
 * HandleInertiaRequests) so the Vue catalog is built from it at runtime.
 *
 * Three widget KINDS are supported:
 *
 *  - kind 'vue'   → a Vue component. First-party/bundled widgets resolve by
 *                   `component` NAME against the SPA's bundled resolver map.
 *                   Third-party widgets ship a pre-built ESM `module` URL
 *                   (under /ext/<addon>/…) which the SPA imports at runtime.
 *  - kind 'blade' → a server-rendered Blade fragment fetched from `endpoint`.
 *                   The generic BladeWidget.vue shell hosts it, in `island`
 *                   mode (fetch + inject + intercept form posts) or `iframe`
 *                   mode (same-origin iframe, native forms/scripts). The addon's
 *                   logic stays on the server; only rendered HTML ships.
 *  - kind 'tag'   → a light-DOM custom element (see the `web-component-widget-
 *                   floor` change), consumable as markup by any frontend, not
 *                   just the skylight SPA. `module` points at the custom-
 *                   elements bundle that defines the tag; `attributes`
 *                   describes each authorable attribute for a catalog consumer
 *                   (e.g. an insert menu) to offer or validate.
 */
final class WidgetRegistry
{
    /**
     * Registered widget definitions, keyed by id (last registration wins so a
     * first-party widget can be intentionally overridden by id).
     *
     * @var array<string, array<string, mixed>>
     */
    private array $widgets = [];

    /**
     * Register (or override) a widget definition.
     *
     * Recognised keys:
     *   id          string   required, unique. Also the instance id (one per type).
     *   kind        string   'vue' (default) | 'blade'.
     *   title       string   label shown in the Add-widget menu + frame header.
     *   icon        string   lucide icon name (e.g. 'cloud-sun') OR raw inline
     *                        SVG path markup. The SPA prefers a lucide name.
     *   defaultZone string   'grid' | 'sidebar' (default 'grid').
     *   span        int      1|2 grid column span (grid zone only).
     *   removable   bool     may the pilot remove it (default true).
     *   defaultOn   bool     shown in the default layout on first load.
     *
     *   -- kind 'vue' --
     *   component   string   resolver-map name for a BUNDLED (first-party) widget.
     *                        Takes precedence: the resolver checks this bundled
     *                        name FIRST, so a matching bundled component wins.
     *   module      string   URL of a pre-built ESM module for a THIRD-PARTY
     *                        widget (e.g. '/ext/samplevuewidget/widgets/sample.js').
     *                        The FALLBACK: imported at runtime only when no
     *                        bundled `component` of that name is registered.
     *   props       array    static props merged into the component.
     *
     *   -- kind 'blade' --
     *   endpoint    string   URL returning the rendered Blade fragment.
     *   mode        string   'island' (default) | 'iframe'.
     *
     *   -- kind 'tag' --
     *   tag         string   required. The custom element's tag name
     *                        (e.g. 'pv-hero'), registered via
     *                        `customElements.define()` in the bundle at `module`.
     *   attributes  array    list of {name, type, description} entries
     *                        describing each authorable attribute, for a
     *                        catalog consumer building an insert menu or
     *                        validating stored markup.
     *   module      string   URL of the custom-elements bundle that defines
     *                        this tag. Loaded once per consuming document —
     *                        the custom element registry is per-`Window`.
     *
     * @param array<string, mixed> $definition
     */
    public function register(array $definition): self
    {
        if (empty($definition['id']) || !is_string($definition['id'])) {
            throw new InvalidArgumentException('Skylight widget definition requires a string "id".');
        }

        $definition['kind'] ??= 'vue';
        $definition['defaultZone'] ??= 'grid';

        // A 'tag' entry is consumed as markup (a bare custom element tag) by
        // catalog readers outside this SPA, so — unlike 'vue'/'blade', which the
        // SPA's own resolver fails visibly for when malformed — a missing tag
        // name would silently produce an unusable catalog entry. Fail loudly here.
        if ($definition['kind'] === 'tag' && (empty($definition['tag']) || !is_string($definition['tag']))) {
            throw new InvalidArgumentException('A Skylight widget definition with kind "tag" requires a string "tag".');
        }

        $this->widgets[$definition['id']] = $definition;

        return $this;
    }

    /**
     * Remove a widget by id (e.g. to hide a first-party widget from an addon).
     */
    public function remove(string $id): self
    {
        unset($this->widgets[$id]);

        return $this;
    }

    /**
     * All registered widget definitions as a flat, JSON-serializable list —
     * the exact shape shared to the SPA.
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return array_values($this->widgets);
    }

    /**
     * Whether a widget id is registered (i.e. its owning addon is enabled).
     */
    public function has(string $id): bool
    {
        return isset($this->widgets[$id]);
    }
}
