<?php

declare(strict_types=1);

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Data migration: `general.theme` moves off the Settings page onto its own
 * Content > Theme page, so the row becomes `hidden` -- the same mechanism the
 * branding rows use. The value is left alone.
 *
 * `SettingsSeeder` ships the row as hidden for fresh installs; this exists for
 * installs that upgrade via `migrate` + `migrate-data`, whose pending-seed
 * check only looks for missing rows, not a changed type.
 */
return new class() extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('settings')) {
            return;
        }

        DB::table('settings')
            ->where('id', Setting::formatKey('general.theme'))
            ->update(['type' => 'hidden']);
    }

    public function down(): void
    {
        if (!Schema::hasTable('settings')) {
            return;
        }

        DB::table('settings')
            ->where('id', Setting::formatKey('general.theme'))
            ->update(['type' => 'select']);
    }
};
