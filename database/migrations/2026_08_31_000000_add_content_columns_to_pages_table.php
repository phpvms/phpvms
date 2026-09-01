<?php

declare(strict_types=1);

use App\Enums\PageStatus;
use App\Enums\PageType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Turns `pages` into a content model.
 *
 * Three things are added and nothing is removed. `path` is the URL a page
 * claims, which is what lets a page be `/` instead of only `page/{slug}`.
 * `status` separates "being worked on" from "live", so editing stops being a
 * publish. `draft_body` is that working copy.
 *
 * `body` keeps its exact current meaning — the published content, rendered by
 * `resources/views/layouts/seven/pages/index.blade.php` — so the legacy theme is
 * untouched and existing rows are already in their final state. Publishing
 * copies `draft_body` onto `body`; it is not a second source of truth.
 *
 * Existing rows are backfilled to `PUBLISHED` **here**, not in the accompanying
 * data migration. Data migrations run from a separate `migrate-data` command, so
 * an operator who runs only `php artisan migrate` would otherwise leave every
 * existing page a draft — and the newly-enforced `live()` scope would 404 the
 * entire site. The backfill has to travel with the column that causes the
 * problem. The data migration repeats it idempotently as a backstop.
 *
 * `path` is indexed but not unique at the database level. Uniqueness is only
 * required *among published pages* (D2, D12: unpublishing releases the claim),
 * and a partial unique index is not portable across the three drivers this
 * project supports. The constraint is enforced on save instead.
 */
return new class() extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('pages')) {
            return;
        }

        Schema::table('pages', function (Blueprint $table): void {
            if (!Schema::hasColumn('pages', 'path')) {
                $table->string('path')->nullable()->after('slug')->index();
            }

            if (!Schema::hasColumn('pages', 'status')) {
                $table->unsignedSmallInteger('status')->default(0)->after('enabled')->index();
            }

            if (!Schema::hasColumn('pages', 'draft_body')) {
                $table->mediumText('draft_body')->nullable()->after('body');
            }

            if (!Schema::hasColumn('pages', 'published_at')) {
                $table->timestamp('published_at')->nullable()->after('draft_body');
            }
        });

        $this->publishExistingPages();
    }

    /**
     * Everything already in the table was live before this migration ran.
     */
    private function publishExistingPages(): void
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

    public function down(): void
    {
        if (!Schema::hasTable('pages')) {
            return;
        }

        Schema::table('pages', function (Blueprint $table): void {
            foreach (['path', 'status', 'draft_body', 'published_at'] as $column) {
                if (Schema::hasColumn('pages', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
