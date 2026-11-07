<?php

declare(strict_types=1);

namespace App\Support\Extensions;

/**
 * The extension hub.
 *
 * A single container-bound instance holds every extension registry that
 * themes (SPA and Blade) expose to addons. Addons reach it through the {@see Facades\Extensions}
 * facade from their ServiceProvider::boot():
 *
 *   Extensions::widgets()->register([...]);   // dashboard widget
 *   Extensions::slots()->register([...]);     // inject into a page slot
 *
 * The registries are read once per request by HandleInertiaRequests and shared
 * to the SPA as serialized props. Because addon providers boot before the
 * request pipeline runs, everything an enabled addon registers is present when
 * share() serializes it — and anything a disabled addon would have registered is
 * absent, because its provider never booted.
 */
final readonly class Extensions
{
    private WidgetRegistry $widgets;

    private SlotRegistry $slots;

    public function __construct()
    {
        $this->widgets = new WidgetRegistry();
        $this->slots = new SlotRegistry();
    }

    /**
     * The dashboard widget registry (the board's catalog).
     */
    public function widgets(): WidgetRegistry
    {
        return $this->widgets;
    }

    /**
     * The page slot registry (inject components into named page outlets).
     */
    public function slots(): SlotRegistry
    {
        return $this->slots;
    }

    /**
     * The full extension surface, JSON-serializable — the exact shape shared to
     * SPA themes under the `skylight` Inertia prop.
     *
     * @return array{widgets: array<int, array<string, mixed>>, slots: array<int, array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'widgets' => $this->widgets->all(),
            'slots'   => $this->slots->all(),
        ];
    }
}
