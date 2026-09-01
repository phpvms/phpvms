<?php

declare(strict_types=1);

use App\Enums\NavigationTarget;
use App\Enums\PageStatus;
use App\Enums\PageType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Data migration: give every existing page a navigation item, and mark existing
 * content as published.
 *
 * Until now the menu *was* the pages table, so the upgrade has to reproduce that
 * menu exactly or every site's navigation changes on deploy. Two rules do it:
 *
 *  - Every row becomes one navigation item. `PageLinksComposer` ordered by
 *    nothing, which in practice meant primary-key order, so `sort` is assigned
 *    in `id` order to reproduce the same sequence.
 *  - `visible` is seeded from `enabled`, because only enabled rows appeared in
 *    the menu. `public` carries over directly — it is the same guest filter.
 *
 * `PageType::LINK` rows are **not** deleted. Everything they carry is copied
 * into the navigation item, so deleting them would lose nothing, but a data
 * migration that destroys rows has no undo if the upgrade is wrong. They stay,
 * and page resolution ignores them instead. Removing them is a later, separate,
 * reversible decision.
 *
 * Existing bodies are marked `PUBLISHED` and mirrored into `draft_body`. Without
 * this every existing page would be a draft the moment the `live()` scope starts
 * being enforced, and every site would go blank on upgrade.
 *
 * Idempotent: a page that already has a navigation item is skipped, so a re-run
 * cannot double the menu.
 */
return new class() extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('pages') || !Schema::hasTable('navigation_items')) {
            return;
        }

        $this->publishExistingContent();
        $this->buildNavigation();
    }

    /**
     * Existing content is live today. Say so, or the new enforcement hides it.
     */
    private function publishExistingContent(): void
    {
        DB::table('pages')
            ->where('type', PageType::PAGE->value)
            ->where('status', PageStatus::DRAFT->value)
            ->update([
                'status'       => PageStatus::PUBLISHED->value,
                'draft_body'   => DB::raw('body'),
                'published_at' => now(),
            ]);
    }

    private function buildNavigation(): void
    {
        $sort = (int) DB::table('navigation_items')->max('sort');

        DB::table('pages')
            ->orderBy('id')
            ->select(['id', 'name', 'slug', 'icon', 'type', 'public', 'enabled', 'link', 'new_window'])
            ->chunkById(200, function ($pages) use (&$sort): void {
                foreach ($pages as $page) {
                    $isLink = (int) $page->type === PageType::LINK->value;

                    $target = $isLink ? NavigationTarget::URL : NavigationTarget::PAGE;
                    $value = $isLink ? (string) $page->link : (string) $page->id;

                    $alreadyMigrated = DB::table('navigation_items')
                        ->where('target_type', $target->value)
                        ->where('target_value', $value)
                        ->exists();

                    if ($alreadyMigrated) {
                        continue;
                    }

                    DB::table('navigation_items')->insert([
                        'label'        => $page->name,
                        'icon'         => $page->icon,
                        'parent_id'    => null,
                        'sort'         => ++$sort,
                        'target_type'  => $target->value,
                        'target_value' => $value,
                        'new_window'   => (bool) $page->new_window,
                        'public'       => (bool) $page->public,
                        'visible'      => (bool) $page->enabled,
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ]);
                }
            });
    }
};
