<?php

declare(strict_types=1);

namespace App\Support\PirepView;

use Closure;
use InvalidArgumentException;

/**
 * Registry of addon-contributed tabs on the ADMIN PIREP view page.
 *
 * `App\Support\PirepView\PirepViewTabRegistry` — the class name and namespace
 * are the addon-facing contract. An addon resolves it from the container in its
 * ServiceProvider::boot() (guarded with `class_exists` so the addon still
 * installs against an older core) and calls `register()`. A DISABLED addon's
 * provider never boots, so its tab is simply never registered — disable-safety
 * is a property of the mechanism, not a runtime `enabled` check. Same idea as
 * App\Support\Skylight\SlotRegistry, but the admin page is not the SPA, so this
 * deliberately lives outside the Skylight hub.
 *
 * Entries carry CLOSURES (label/badge/visible are evaluated per record at
 * render time). The registry is therefore an in-memory, boot-time singleton and
 * must never be cached or serialized.
 */
final class PirepViewTabRegistry
{
    /**
     * Registered tabs keyed by id — last registration wins, so a tab can be
     * deliberately replaced. Replacing keeps the original insertion position,
     * which is what makes `ordered()` deterministic for equal `order` values.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $tabs = [];

    /**
     * Register (or, on a duplicate id, replace) a tab.
     *
     * Recognised keys:
     *   id      string   required, namespaced ('vendor.name'). Doubles as the
     *                    Alpine `activeTab` value and the source of the panel's
     *                    DOM id.
     *   label   string|Closure(Pirep): string             required, escaped as text.
     *   badge   string|int|Closure(Pirep): string|int|null  optional, escaped as text.
     *   visible Closure(Pirep): bool                      optional, default true.
     *                    False hides both the button and the panel for that record.
     *   order   int      optional, default 100 — after the built-in tabs. Ties
     *                    break by registration order.
     *   view    string   required. Rendered with ONLY `['record' => $pirep]`.
     *
     * @param array<string, mixed> $tab
     */
    public function register(array $tab): self
    {
        if (empty($tab['id']) || !is_string($tab['id'])) {
            throw new InvalidArgumentException('A PIREP view tab requires a string "id".');
        }

        if (empty($tab['view']) || !is_string($tab['view'])) {
            throw new InvalidArgumentException('A PIREP view tab requires a string "view".');
        }

        if (!isset($tab['label']) || !is_string($tab['label']) && !$tab['label'] instanceof Closure) {
            throw new InvalidArgumentException('A PIREP view tab requires a string or Closure "label".');
        }

        if (isset($tab['badge']) && !(is_string($tab['badge']) || is_int($tab['badge']) || $tab['badge'] instanceof Closure)) {
            throw new InvalidArgumentException('A PIREP view tab "badge" must be a string, an int or a Closure.');
        }

        if (isset($tab['visible']) && !$tab['visible'] instanceof Closure) {
            throw new InvalidArgumentException('A PIREP view tab "visible" must be a Closure.');
        }

        if (isset($tab['order']) && !is_int($tab['order'])) {
            throw new InvalidArgumentException('A PIREP view tab "order" must be an int.');
        }

        $tab['order'] ??= 100;

        $this->tabs[$tab['id']] = $tab;

        return $this;
    }

    /**
     * Every registered tab, ascending by `order`. `usort` is stable as of PHP
     * 8.0, so equal orders keep registration order.
     *
     * @return array<int, array<string, mixed>>
     */
    public function ordered(): array
    {
        $tabs = array_values($this->tabs);
        usort($tabs, static fn (array $a, array $b): int => $a['order'] <=> $b['order']);

        return $tabs;
    }
}
