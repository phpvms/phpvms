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
 * `slot` and `type` are closed vocabularies enforced in the model rather than
 * by DB constraints. `slot` in particular becomes a URL segment and a directory
 * name downstream, so an unvalidated value is a path-traversal vector.
 *
 * `content_type` and the stored path are decided server-side on upload, never
 * supplied by the uploader: content_type drives both the extension a file is
 * stored under and the Content-Type replayed when serving it. Serve a component
 * as application/octet-stream and the browser silently refuses to execute it.
 *
 * Files live on a private disk and are served through a core route rather than
 * a public storage URL, which is what the old `branding.*` URLs and the
 * public-disk uploads were. `is_public` decides whether that route demands a
 * session; it defaults closed.
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

            // Owning module: 'core' for phpVMS itself, otherwise a module slug.
            // A plain string, not an enum — modules are extensible and cannot
            // add cases to a PHP enum shipped by core.
            $table->string('source', 32)->default('core')->index();

            // Human label for pickers. Not an identifier; null for assets whose
            // key is already the name a user sees.
            $table->string('name')->nullable();

            $table->string('content_type');

            // Location on the private disk. Not a URL: consumers are handed a
            // route addressed by id, so files can move without invalidating
            // anything already stored downstream.
            $table->string('path');

            // Opaque change stamp, compared for EQUALITY ONLY by consumers —
            // never ordered or parsed, so it can become a checksum, a revision
            // or a timestamp without breaking anyone.
            $table->string('last_update');

            // Whether the bytes are served without authentication. Files all
            // live on the same private disk either way; this only decides
            // whether the serving route demands a session.
            //
            // It has to be a per-asset flag rather than a slot rule: site
            // branding is rendered on pages a logged-out visitor sees — the
            // login screen's banner (resources/views/filament/auth/login-hero.blade.php:7),
            // the panel favicon (app/Providers/Filament/BasePanelProvider.php:94)
            // and the public nav logo (resources/views/layouts/seven/nav.blade.php:12)
            // — while a paintkit or an uploaded sound in the same install
            // should not be hotlinkable. Defaults closed.
            $table->boolean('is_public')->default(false)->index();

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
