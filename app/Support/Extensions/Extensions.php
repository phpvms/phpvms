<?php

declare(strict_types=1);

namespace App\Support\Extensions;

/**
 * The extension hub, shared by the SPA and Blade themes.
 *
 * It is a singleton, so it holds no per-request state: the slot list lives in
 * the request-scoped {@see SlotRegistry}, resolved from the container on each
 * call. The SPA gets it as the shared `skylight` Inertia prop.
 */
final readonly class Extensions
{
    /**
     * The slot entries of the current request.
     */
    public function slots(): SlotRegistry
    {
        return app(SlotRegistry::class);
    }

    /**
     * The extension surface, JSON-serializable — the shape shared to SPA
     * themes under the `skylight` Inertia prop. `widgets` stays as an empty
     * list for the SPA's sake.
     *
     * @return array{widgets: list<never>, slots: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'widgets' => [],
            'slots'   => $this->slots()->all(),
        ];
    }
}
