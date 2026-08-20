<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the `assets` table — one row per downloadable blob any part of phpVMS
 * or its modules needs to hand out: site branding images, uploaded sounds,
 * stylesheets, web components, aircraft paintkits.
 *
 * Why this is core and not a module table. Branding images were `branding.*`
 * settings holding plain URL strings (`database/seeders/SettingsSeeder.php:919-973`,
 * read in `app/Support/Branding.php:41-98`), and every module that wanted to
 * ship its own kind of file grew its own settings keys plus its own delivery
 * shape. One table with a `source` column replaces all of that: a module stores
 * here and reads back through the same feature.
 *
 * Two identifiers on purpose. `id` is a nano ID that addresses the bytes and
 * never changes. `key` is the human name a consumer looks up by — `logo`,
 * `splash-banner`, `gear-warning`.
 *
 * UNIQUE is (slot, key) and deliberately does NOT include `source`. A consumer
 * that caches assets to disk lays them out as {slot}/{key}.{ext}, so two rows
 * sharing a slot and key would collide there no matter which module wrote them.
 * `source` records the owner for filtering and cleanup; it is not part of the
 * identity.
 *
 * `slot` and `type` are open vocabularies, for the same reason `source` is: a
 * module cannot add a case to a PHP enum core ships, and most slots (sounds,
 * gauges, paintkits) belong to whatever module serves them rather than to core.
 * Core declares only its own slots (`Asset::SLOT_*`) and seeds the one kind it
 * serves (`AssetTypes`); a module registers the rest during boot.
 *
 * Open does not mean unchecked. `slot` becomes a URL segment and a directory
 * name downstream, so `AssetService` enforces a format on it — that guard is
 * what replaces the closed list, not the absence of one. `type` is never taken
 * from a caller at all: it is derived from the sniffed content type via the
 * registry, and bytes nothing has registered are rejected.
 *
 * `content_type` and the stored path are decided server-side on upload, never
 * supplied by the uploader: content_type drives both the extension a file is
 * stored under and the Content-Type replayed when serving it. Serve a component
 * as application/octet-stream and the browser silently refuses to execute it.
 *
 * `storage` names the disk the bytes live on, so an install can put assets
 * anywhere it has configured — the public disk, s3, r2 — or reference an
 * external URL with the reserved value `url`. Whether an asset can be linked
 * directly is not stored: it is read back from that disk's `url` config entry.
 * Anything without one is served through the core route, which applies its own
 * authorization.
 *
 * Deliberately NOT soft-deleted. An asset owns a file on disk and a slice of
 * the (slot, key) namespace; soft-deleting one would hold its key hostage — so
 * re-uploading `splash-banner` after deleting it would hit the unique index —
 * and leave the file orphaned. Deleting an asset deletes its file.
 */
return new class() extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('assets')) {
            return;
        }

        Schema::create('assets', function (Blueprint $table): void {
            // Nano ID, matching App\Traits\HasNanoIds (Model::ID_MAX_LENGTH).
            $table->string('id', 16)->primary();

            $table->string('key');
            $table->string('slot')->index();
            $table->string('type')->index();

            // Owning module: 'phpvms' for phpVMS itself, otherwise a module slug
            // A plain string, not an enum — modules are extensible and cannot
            // add cases to a PHP enum shipped by core.
            $table->string('source', 32)->default('phpvms')->index();

            // Human label for pickers. Not an identifier; null for assets whose
            // key is already the name a user sees.
            $table->string('name')->nullable();

            // Nullable because a link asset (storage = 'url') is a URL we were
            // handed, not bytes we sniffed — there is nothing to read a type
            // from unless the caller declares one.
            $table->string('content_type')->nullable();

            // Where the bytes are. Either a disk name from
            // config('filesystems.disks') or the reserved value 'url'
            // (Asset::STORAGE_URL), which means this asset is an external link
            // and `path` holds that link rather than a location on a disk.
            //
            // Not a boolean: one flag cannot say "put this on r2". Whether the
            // bytes are directly reachable is read back from the disk's own
            // `url` config entry (Asset::url()), so it cannot drift out of sync
            // with how the install is actually configured.
            $table->string('storage')->index();

            // Location on the asset's disk — or the URL itself when `storage`
            // is 'url'. Either way it is the only locator, so it stays NOT
            // NULL. For a stored file this is not a URL: consumers are handed a
            // route addressed by id, so files can move without invalidating
            // anything already stored downstream.
            $table->string('path');

            // Opaque change stamp, compared for EQUALITY ONLY by consumers —
            // never ordered or parsed, so it can become a checksum, a revision
            // or a timestamp without breaking anyone.
            $table->string('last_update');

            $table->unsignedBigInteger('size')->default(0);
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['slot', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
