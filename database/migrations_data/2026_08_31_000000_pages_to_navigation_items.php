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
 * Idempotent without a schema change: `pages.link` is not unique, so the target
 * alone cannot say whether a row was migrated -- two link rows to one URL with
 * different names or visibility are two menu entries. A row is identified by its
 * target, `name` (copied verbatim and not touched by later label edits), `public`
 * and `new_window`. When several legacy rows share that whole identity, the Nth
 * is skipped only if at least N matching items already exist, so a re-run adds
 * nothing and a first run still gives every row its own item.
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
     *
     * Only legacy rows qualify: a draft with no `draft_body` and no
     * `published_at`. The schema migration already published every pre-upgrade
     * page and copied `body` into `draft_body`, so what is left here is a
     * backstop for rows that migration did not reach. The admin always writes a
     * `draft_body`, so a draft created after the schema migration but before
     * this data migration carries one and must stay a draft.
     */
    private function publishExistingContent(): void
    {
        DB::table('pages')
            ->where('type', PageType::PAGE->value)
            ->where('status', PageStatus::DRAFT->value)
            ->whereNull('draft_body')
            ->whereNull('published_at')
            ->update([
                'status'       => PageStatus::PUBLISHED->value,
                'draft_body'   => DB::raw('body'),
                'published_at' => now(),
            ]);
    }

    private function buildNavigation(): void
    {
        $sort = (int) DB::table('navigation_items')->max('sort');

        /** @var array<string, int> $seen */
        $seen = [];

        DB::table('pages')
            ->orderBy('id')
            ->select(['id', 'name', 'slug', 'icon', 'type', 'public', 'enabled', 'link', 'new_window'])
            ->chunkById(200, function ($pages) use (&$sort, &$seen): void {
                foreach ($pages as $page) {
                    $isLink = (int) $page->type === PageType::LINK->value;

                    $target = $isLink ? NavigationTarget::URL : NavigationTarget::PAGE;
                    $value = $isLink ? (string) $page->link : (string) $page->id;

                    $identity = [$target->value, $value, $page->name, (bool) $page->public, (bool) $page->new_window];
                    $key = serialize($identity);
                    $seen[$key] = ($seen[$key] ?? 0) + 1;

                    $existing = DB::table('navigation_items')
                        ->where('target_type', $target->value)
                        ->where('target_value', $value)
                        ->where('name', $page->name)
                        ->where('public', (bool) $page->public)
                        ->where('new_window', (bool) $page->new_window)
                        ->count();

                    if ($existing >= $seen[$key]) {
                        continue;
                    }

                    DB::table('navigation_items')->insert([
                        'name'         => $page->name,
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
