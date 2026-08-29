<?php

use App\Features\Assets\AssetService;
use App\Models\Asset;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Data migration: move award badges into the `award` slot, keyed on the
 * award id.
 *
 * `awards.image_url` was a free-text field, so it holds either of two
 * things: a path to a file on the public disk, or an absolute URL somewhere
 * else. As with the rank badge migration (`rank_images_to_assets`), both
 * become assets — an external URL becomes a link row
 * (`AssetService::storeLink()`), which is exactly what the admin form now
 * writes when someone picks the URL source.
 *
 * **The URL does not change for a hosted badge.** The asset adopts the file
 * where it already sits (see `AssetService::adopt()`), so a badge an install
 * has already published keeps its address.
 *
 * Anything that is neither — a site-relative path we do not host on the
 * assets disk, or a file that has since been deleted — is left on the
 * column, which `Award::imageUrl()` still falls back to.
 */
return new class() extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('awards') || !Schema::hasTable('assets')) {
            return;
        }

        $assets = app(AssetService::class);
        $storage = (string) config('filesystems.public_files');
        $disk = Storage::disk($storage);

        DB::table('awards')
            ->select(['id', 'name', 'image_url'])
            ->whereNotNull('image_url')
            ->where('image_url', '!=', '')
            ->orderBy('id')
            ->chunkById(200, function ($awards) use ($assets, $disk, $storage): void {
                foreach ($awards as $award) {
                    $image = (string) $award->image_url;

                    // Never fail the upgrade over one badge. An unsupported
                    // file, or a value that is neither a hosted file nor an
                    // http(s) URL, leaves that award's column untouched, so
                    // it still renders through the legacy fallback.
                    //
                    // The SAVEPOINT is load-bearing: on Postgres a failed
                    // statement aborts the whole transaction, and catching
                    // the PHP exception does not clear that — every later
                    // statement dies with 25P02 until a rollback.
                    // DB::transaction() nested inside an open transaction
                    // issues SAVEPOINT / ROLLBACK TO SAVEPOINT, which is what
                    // lets `continue` actually continue.
                    try {
                        DB::transaction(fn () => $disk->exists($image)
                            ? $assets->adopt(
                                $image,
                                Asset::SLOT_AWARD,
                                (string) $award->id,
                                name: (string) $award->name,
                                storage: $storage,
                            )
                            : $assets->storeLink(
                                $image,
                                Asset::SLOT_AWARD,
                                (string) $award->id,
                                name: (string) $award->name,
                            ));
                    } catch (Throwable $e) {
                        Log::warning('award_images_to_assets: could not move award image', [
                            'award' => $award->id,
                            'image' => $image,
                            'error' => $e->getMessage(),
                        ]);

                        continue;
                    }

                    // The asset owns it now. Clearing the column is what
                    // makes the asset the single source of truth rather than
                    // leaving two records of the same image that can drift.
                    DB::table('awards')->where('id', $award->id)->update(['image_url' => null]);
                }
            });
    }

    /**
     * Points the column back at what the asset holds — a path for an
     * adopted file, the URL itself for a link — and drops the rows it
     * restored. No file ever moved, so an award this migration touched ends
     * up where it started.
     *
     * Only awards whose `image_url` is still null are restored, because
     * that is the state {@see up()} left behind. An admin who set the
     * column again afterwards keeps their value, and the asset backing it is
     * left alone rather than deleted out from under them.
     *
     * Not exact in one case: an award whose badge was re-uploaded through
     * the asset picker after the upgrade also has a null column, so the
     * rollback writes that asset's internal path into it. That path is not
     * what the column's accessor serves, so the badge is lost — but so is
     * the asset row either way, and a data migration's `down()` is an escape
     * hatch rather than a supported round trip.
     */
    public function down(): void
    {
        if (!Schema::hasTable('awards') || !Schema::hasTable('assets')) {
            return;
        }

        $images = DB::table('assets')->where('slot', Asset::SLOT_AWARD)->get();

        $restored = [];

        foreach ($images as $asset) {
            $wrote = DB::table('awards')
                ->where('id', $asset->key)
                ->whereNull('image_url')
                ->update(['image_url' => $asset->path]);

            if ($wrote > 0) {
                $restored[] = $asset->id;
            }
        }

        if ($restored === []) {
            return;
        }

        // Deleted through the query builder ON PURPOSE, so Asset's
        // `deleted` hook does not fire and delete the very files the column
        // now points at again. The rows go; the files stay exactly where
        // they were.
        DB::table('assets')->whereIn('id', $restored)->delete();
    }
};
