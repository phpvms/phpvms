<?php

use App\Features\Assets\AssetService;
use App\Features\Assets\Enums\AssetSlot;
use App\Models\Airline;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Data migration: move hosted airline logos into the `airline-logo` slot.
 *
 * `airlines.logo` held two different things: a path under `airlines/` for a
 * mark we host, or an absolute URL someone typed in or imported. Only the first
 * is a file, so only the first becomes an asset. The external URLs stay on the
 * column, which is now what that column is for — `Airline::logoUrl()` reads the
 * asset first and falls back to it.
 *
 * **The URL does not change.** The asset adopts the file where it already sits
 * on the public disk (see `AssetService::adopt()`); a public asset's URL is
 * derived from its path, so copying to a fresh asset path would break the logo
 * URL of every airline an install has already published.
 *
 * Keyed on the ICAO exactly as stored (uppercase). `airlines.icao` is unique
 * (`database/migrations/2025_01_13_003704_create_phpvms_table.php:173`), so
 * that is unique within the slot, and matching the stored casing is what lets
 * `Airline::logoAsset()` be a plain eager-loadable relation instead of an N+1.
 *
 * Scope note: hosted uploads are new in 8.0 — the mechanism landed in one
 * unreleased commit and `docs/upgrading-to-8.0.md:126` lists it as a new
 * feature — so on a real upgrade this loop almost always finds nothing. It
 * exists for installs that have been running a pre-release 8.0.
 */
return new class() extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('airlines') || !Schema::hasTable('assets')) {
            return;
        }

        $assets = app(AssetService::class);
        $disk = Storage::disk(config('filesystems.public_files'));

        DB::table('airlines')
            ->select(['id', 'icao', 'logo'])
            ->whereNotNull('logo')
            ->where('logo', '!=', '')
            ->orderBy('id')
            ->chunkById(200, function ($airlines) use ($assets, $disk): void {
                foreach ($airlines as $airline) {
                    // An external URL is not a file we host; leave it alone.
                    if (!Airline::isUploadedLogo((string) $airline->logo)) {
                        continue;
                    }

                    if (!$disk->exists((string) $airline->logo)) {
                        continue;
                    }

                    // Never fail the upgrade over one logo. An unsupported file
                    // leaves that airline's column untouched, so it still
                    // renders through the legacy fallback.
                    try {
                        $assets->adopt(
                            (string) $airline->logo,
                            AssetSlot::AIRLINE_LOGO,
                            (string) $airline->icao,
                            name: (string) $airline->icao,
                            isPublic: true,
                        );
                    } catch (Throwable) {
                        continue;
                    }

                    // The asset owns it now. Clearing the column is what makes
                    // the asset the single source of truth rather than leaving
                    // two records of the same file that can drift.
                    DB::table('airlines')->where('id', $airline->id)->update(['logo' => null]);
                }
            });
    }

    /**
     * Points the column back at the adopted files and drops the assets. The
     * files never moved, so this restores the previous state exactly.
     */
    public function down(): void
    {
        if (!Schema::hasTable('airlines') || !Schema::hasTable('assets')) {
            return;
        }

        $logos = DB::table('assets')->where('slot', AssetSlot::AIRLINE_LOGO->value)->get();

        foreach ($logos as $asset) {
            DB::table('airlines')->where('icao', $asset->key)->update(['logo' => $asset->path]);
        }

        // Deleted through the query builder ON PURPOSE, so Asset's `deleted`
        // hook does not fire and delete the very files the column now points
        // at again. The rows go; the files stay exactly where they always were.
        DB::table('assets')->where('slot', AssetSlot::AIRLINE_LOGO->value)->delete();
    }
};
