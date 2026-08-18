<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drops `airlines.logo_hash`.
 *
 * A hosted airline mark is an asset now, and an asset already carries a change
 * stamp — `assets.last_update`, a crc32b of the file contents, which is the
 * same algorithm this column held. `Airline::logoHash()` reads through to it,
 * so the `logo_hash` attribute still resolves for every consumer; only the
 * duplicated column goes.
 *
 * Leaving it would be worse than dropping it: nothing writes it any more, so it
 * would sit at whatever value it held when the logo moved and quietly disagree
 * with the asset it is supposed to describe.
 *
 * The column is short-lived — added 2026-07-24 alongside the drag-and-drop
 * upload, which `docs/upgrading-to-8.0.md:126` lists as new in 8.0 — so no
 * released version ever shipped it.
 */
return new class() extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('airlines', 'logo_hash')) {
            return;
        }

        Schema::table('airlines', function (Blueprint $table): void {
            $table->dropColumn('logo_hash');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('airlines', 'logo_hash')) {
            return;
        }

        Schema::table('airlines', function (Blueprint $table): void {
            $table->string('logo_hash', 8)->nullable()->after('logo');
        });
    }
};
