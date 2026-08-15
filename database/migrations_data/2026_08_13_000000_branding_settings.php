<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Data migration: seed the branding settings.
 *
 *  - `general.site_name`: airline display name, visible on the Settings page's
 *    General tab. `settings.order` is `unsignedInteger`
 *    (`database/migrations/2025_01_13_003704_create_phpvms_table.php:809`), so it
 *    cannot be seeded below the seeder's lowest `general` value (0, on
 *    `general.theme`) without a negative number. Instead every existing
 *    `general` row is shifted up by one and `general.site_name` takes order 0,
 *    guarded so a re-run does not shift twice.
 *  - `branding.brand_color`, `branding.logo_url`, `branding.logo_32_url`,
 *    `branding.logo_64_url`, `branding.logo_180_url`, `branding.banner_url`:
 *    hidden rows edited only via the Branding page, empty until an admin
 *    uploads or sets them. `order` is left at the column default (99) since
 *    hidden rows are excluded from the Settings page's ordered tabs.
 *    `App\Support\Branding` falls back to the hardcoded phpVMS assets while
 *    they are empty.
 *
 * Both are also seeded by `SettingsSeeder` for fresh installs — `general.site_name`
 * as the first `general` entry there (so its derived `order` is naturally 0, ahead
 * of every other `general` row) and the six `branding.*` keys in their own hidden
 * group block. This migration exists for existing installs, which upgrade via
 * `migrate` + `migrate-data`, not by re-seeding. Its `general` order-shift only
 * runs when `general.site_name` does not already exist (i.e. on an install that
 * predates the seeder change), so a re-seed after this migration has already run
 * cannot re-tie the order back to 0.
 */
return new class() extends Migration
{
    /**
     * @var list<array{key: string, name: string, group: string, type: string}>
     */
    private array $rows = [
        [
            'key'   => 'general.site_name',
            'name'  => 'Site Name',
            'group' => 'general',
            'type'  => 'text',
        ],
        [
            'key'   => 'branding.brand_color',
            'name'  => 'Brand Color',
            'group' => 'branding',
            'type'  => 'hidden',
        ],
        [
            'key'   => 'branding.logo_url',
            'name'  => 'Logo URL',
            'group' => 'branding',
            'type'  => 'hidden',
        ],
        [
            'key'   => 'branding.logo_32_url',
            'name'  => 'Logo URL (32px)',
            'group' => 'branding',
            'type'  => 'hidden',
        ],
        [
            'key'   => 'branding.logo_64_url',
            'name'  => 'Logo URL (64px)',
            'group' => 'branding',
            'type'  => 'hidden',
        ],
        [
            'key'   => 'branding.logo_180_url',
            'name'  => 'Logo URL (180px)',
            'group' => 'branding',
            'type'  => 'hidden',
        ],
        [
            'key'   => 'branding.banner_url',
            'name'  => 'Banner URL',
            'group' => 'branding',
            'type'  => 'hidden',
        ],
    ];

    public function up(): void
    {
        if (!Schema::hasTable('settings')) {
            return;
        }

        $siteNameId = Setting::formatKey('general.site_name');

        if (!DB::table('settings')->where('id', $siteNameId)->exists()) {
            DB::table('settings')->where('group', 'general')->increment('order');
        }

        foreach ($this->rows as $row) {
            $this->ensureSetting(
                key: $row['key'],
                name: $row['name'],
                group: $row['group'],
                type: $row['type'],
                order: $row['key'] === 'general.site_name' ? 0 : null,
            );
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('settings')) {
            return;
        }

        foreach ($this->rows as $row) {
            Setting::where('id', Setting::formatKey($row['key']))->delete();
        }
    }

    /**
     * Insert a setting row when it does not already exist. Never touches an
     * existing row (preserves an operator- or seeder-set value). `$order` of
     * `null` leaves the column at its schema default (99).
     */
    private function ensureSetting(string $key, string $name, string $group, string $type, ?int $order): void
    {
        $id = Setting::formatKey($key);

        if (DB::table('settings')->where('id', $id)->exists()) {
            return;
        }

        $values = [
            'id'          => $id,
            'key'         => strtolower($key),
            'name'        => $name,
            'value'       => '',
            'default'     => '',
            'group'       => $group,
            'type'        => $type,
            'options'     => '',
            'description' => '',
            'created_at'  => now(),
            'updated_at'  => now(),
        ];

        if ($order !== null) {
            $values['order'] = $order;
        }

        DB::table('settings')->insert($values);
    }
};
