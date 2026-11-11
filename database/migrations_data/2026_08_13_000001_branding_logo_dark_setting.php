<?php

use App\Models\LegacySetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Data migration: seed `branding.logo_dark_url`, added after
 * `2026_08_13_000000_branding_settings.php` had already run on existing
 * installs. That migration is not edited to add this row -- it has already
 * run on the dev database, so editing it would never re-apply. This one row
 * is also seeded by `SettingsSeeder` for fresh installs.
 */
return new class() extends Migration
{
    private const string KEY = 'branding.logo_dark_url';

    public function up(): void
    {
        if (!Schema::hasTable('settings_backup')) {
            return;
        }

        $id = LegacySetting::formatKey(self::KEY);

        if (DB::table('settings_backup')->where('id', $id)->exists()) {
            return;
        }

        DB::table('settings_backup')->insert([
            'id'          => $id,
            'key'         => strtolower(self::KEY),
            'name'        => 'Dark Logo URL',
            'value'       => '',
            'default'     => '',
            'group'       => 'branding',
            'type'        => 'hidden',
            'options'     => '',
            'description' => '',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('settings_backup')) {
            return;
        }

        LegacySetting::where('id', LegacySetting::formatKey(self::KEY))->delete();
    }
};
