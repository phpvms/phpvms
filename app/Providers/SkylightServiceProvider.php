<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\Skylight\Facades\Skylight as SkylightFacade;
use App\Support\Skylight\Skylight;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the skylight SPA extension hub into the container.
 *
 * The hub is a singleton so that addon providers (which boot after this one
 * registers) accumulate their widget/slot registrations onto the SAME instance
 * that HandleInertiaRequests later reads when serializing shared props.
 *
 * Registered ahead of the addon engine in bootstrap/providers.php so the
 * `skylight` binding exists before any addon ServiceProvider::boot() calls the
 * Skylight facade.
 */
final class SkylightServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('skylight', static fn (): Skylight => new Skylight());
    }

    /**
     * Register the first-party light-DOM custom elements (src/elements/*.ce.vue
     * in fe-vue) as `tag`-kind catalog entries. Their attributes mirror each
     * element's declared props.
     */
    public function boot(): void
    {
        $module = '/build/skylight/elements.js';

        SkylightFacade::widgets()
            ->register([
                'id'         => 'pv-hero',
                'kind'       => 'tag',
                'tag'        => 'pv-hero',
                'title'      => 'Hero',
                'icon'       => 'image',
                'module'     => $module,
                'attributes' => [
                    ['name' => 'heading', 'type' => 'string', 'description' => 'The hero heading. Defaults to "Untitled page".'],
                    ['name' => 'eyebrow', 'type' => 'string', 'description' => 'Small text shown above the heading.'],
                ],
            ])
            ->register([
                'id'         => 'pv-callout',
                'kind'       => 'tag',
                'tag'        => 'pv-callout',
                'title'      => 'Callout',
                'icon'       => 'info',
                'module'     => $module,
                'attributes' => [
                    ['name' => 'variant', 'type' => 'string', 'description' => 'One of info, success, warning, error. Defaults to info.'],
                ],
            ])
            ->register([
                'id'         => 'pv-stat-group',
                'kind'       => 'tag',
                'tag'        => 'pv-stat-group',
                'title'      => 'Stat group',
                'icon'       => 'bar-chart-3',
                'module'     => $module,
                'attributes' => [
                    ['name' => 'items', 'type' => 'json', 'description' => 'JSON array of {"label": string, "value": string} stats.'],
                ],
            ]);
    }
}
