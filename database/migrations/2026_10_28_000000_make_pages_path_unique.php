<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * A path is held by at most one page, published or draft.
 *
 * The earlier uniqueness check only looked at live pages, so two drafts could
 * save the same path and two simultaneous publishes could both win. A unique
 * index closes that for good. NULL is allowed repeatedly on MySQL, MariaDB and
 * Postgres, which is what lets pages without a path coexist.
 *
 * Existing data is made safe first: an empty string becomes NULL, and where
 * several rows share a path the lowest id keeps it and the rest lose it (the
 * page still exists and is reachable at `page/{slug}`; its author can pick a
 * new path). Each cleared page is logged.
 */
return new class() extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('pages') || !Schema::hasColumn('pages', 'path')) {
            return;
        }

        $this->clearDuplicatePaths();

        // Separate statements: the index checks must see the previous change.
        if (Schema::hasIndex('pages', 'pages_path_index')) {
            Schema::table('pages', fn (Blueprint $table) => $table->dropIndex(['path']));
        }

        if (!Schema::hasIndex('pages', 'pages_path_unique')) {
            Schema::table('pages', fn (Blueprint $table) => $table->unique('path'));
        }
    }

    private function clearDuplicatePaths(): void
    {
        DB::table('pages')->where('path', '')->update(['path' => null]);

        $keep = [];

        foreach (DB::table('pages')->whereNotNull('path')->orderBy('id')->get(['id', 'path']) as $row) {
            if (!isset($keep[$row->path])) {
                $keep[$row->path] = $row->id;

                continue;
            }

            Log::warning('Cleared duplicate page path before adding unique index', [
                'page_id'  => $row->id,
                'path'     => $row->path,
                'kept_for' => $keep[$row->path],
            ]);

            DB::table('pages')->where('id', $row->id)->update(['path' => null]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('pages') || !Schema::hasColumn('pages', 'path')) {
            return;
        }

        if (Schema::hasIndex('pages', 'pages_path_unique')) {
            Schema::table('pages', fn (Blueprint $table) => $table->dropUnique(['path']));
        }

        if (!Schema::hasIndex('pages', 'pages_path_index')) {
            Schema::table('pages', fn (Blueprint $table) => $table->index('path'));
        }
    }
};
