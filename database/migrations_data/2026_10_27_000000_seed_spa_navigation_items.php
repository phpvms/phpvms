<?php

declare(strict_types=1);

use App\Enums\NavigationTarget;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Data migration: make the SPA shell's built-in links navigation items.
 *
 * The skylight sidebar used to hard-code these seven destinations, so an
 * admin could neither reorder nor hide them, and a menu item added in the
 * admin never appeared beside them. They become ordinary rows here and the
 * sidebar renders the menu alone.
 *
 *  - Labels are translation keys, which `NavigationService` checks first, so
 *    the menu still follows the site language.
 *  - They lead the menu, in their old order; items that already exist keep
 *    their relative order after them.
 *  - `public` is false: every one of them is a pilot page behind login.
 *
 * Idempotent: a route that already has an item is skipped, so a re-run (or
 * an admin who already added one) cannot double it.
 */
return new class() extends Migration
{
    /**
     * @var list<array{name: string, label: string, route: string, icon: string}>
     */
    private const array BUILT_IN = [
        ['name' => 'Dashboard', 'label' => 'common.dashboard', 'route' => 'frontend.dashboard.index', 'icon' => 'layout-dashboard'],
        ['name' => 'Flights', 'label' => 'ui.nav_flights', 'route' => 'frontend.flights.index', 'icon' => 'plane'],
        ['name' => 'My Bids', 'label' => 'ui.nav_bids', 'route' => 'frontend.flights.bids', 'icon' => 'checks'],
        ['name' => 'Tours', 'label' => 'ui.nav_tours', 'route' => 'frontend.tours.index', 'icon' => 'route'],
        ['name' => 'Live Map', 'label' => 'common.live_map', 'route' => 'frontend.livemap.index', 'icon' => 'map'],
        ['name' => 'Logbook', 'label' => 'ui.nav_logbook', 'route' => 'frontend.pireps.index', 'icon' => 'notebook'],
        ['name' => 'Profile', 'label' => 'common.profile', 'route' => 'frontend.profile.index', 'icon' => 'user'],
    ];

    public function up(): void
    {
        if (!Schema::hasTable('navigation_items')) {
            return;
        }

        $existing = DB::table('navigation_items')
            ->where('target_type', NavigationTarget::ROUTE->value)
            ->pluck('target_value')
            ->all();

        $missing = array_values(array_filter(
            self::BUILT_IN,
            fn (array $item): bool => !in_array($item['route'], $existing, true),
        ));

        if ($missing === []) {
            return;
        }

        DB::transaction(function () use ($missing): void {
            // Make room at the front for the built-ins.
            DB::table('navigation_items')
                ->whereNull('parent_id')
                ->increment('sort', count($missing));

            $now = now();

            DB::table('navigation_items')->insert(array_map(fn (array $item, int $sort): array => [
                'name'         => $item['name'],
                'label'        => $item['label'],
                'icon'         => $item['icon'],
                'parent_id'    => null,
                'sort'         => $sort,
                'target_type'  => NavigationTarget::ROUTE->value,
                'target_value' => $item['route'],
                'new_window'   => false,
                'public'       => false,
                'visible'      => true,
                'created_at'   => $now,
                'updated_at'   => $now,
            ], $missing, array_keys($missing)));
        });
    }

    public function down(): void
    {
        // Not reversed: once seeded these are ordinary rows an admin may have
        // edited, and removing them would take those edits with them.
    }
};
