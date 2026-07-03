<?php

declare(strict_types=1);

namespace App\Support\Skylight;

/**
 * The skylight SPA extension hub.
 *
 * A single container-bound instance holds every extension registry the SPA
 * exposes. Addons reach it through the {@see \App\Support\Skylight\Facades\Skylight}
 * facade from their ServiceProvider::boot():
 *
 *   Skylight::widgets()->register([...]);   // dashboard widget
 *   Skylight::slots()->register([...]);     // inject into a page slot
 *
 * The registries are read once per request by HandleInertiaRequests and shared
 * to the SPA as serialized props. Because addon providers boot before the
 * request pipeline runs, everything an enabled addon registers is present when
 * share() serializes it — and anything a disabled addon would have registered is
 * absent, because its provider never booted.
 */
final class Skylight
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
     * the SPA under the `skylight` prop.
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
