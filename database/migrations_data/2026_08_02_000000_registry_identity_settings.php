<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Data migration: establish the registry identity settings.
 *
 *  - `va_global_id`: the install's stable identity, previously created lazily
 *    in the `kvp` table by the ACARS plugin. Moved to a hidden setting so core
 *    (the registry client) can read it without the plugin present. When a `kvp`
 *    value already exists it is copied verbatim; otherwise a new ULID is minted
 *    and written to BOTH stores so the ACARS plugin's lazy `kvp` read resolves
 *    the same identity until it migrates to reading settings.
 *  - `registry.public_key`: pinned Ed25519 key used to verify signed registry
 *    responses. Left empty here; delivered by an addon-manager release or the
 *    `php artisan registry:set-key` command.
 *
 * Both rows are also seeded by SettingsSeeder for fresh installs; this migration
 * covers existing installs (which upgrade via `migrate` + `migrate-data`, not by
 * re-seeding) and is fully idempotent.
 */
return new class() extends Migration
{
    /**
     * Pinned registry Ed25519 public key (base64 of the raw 32-byte key) — the
     * registry's own response-signing key, which signs the download-mint JSON
     * that InstallAddonJob verifies. NOT the Keygen account key (registry.phpvms.net
     * fronts Keygen for the catalog but re-signs downloads with this key).
     */
    private const string REGISTRY_PUBLIC_KEY = '+SDNBf6LCATJElc5yi2mrhpJwGh5So/s1Hi3jCUETic=';

    public function up(): void
    {
        if (!Schema::hasTable('settings')) {
            return;
        }

        $this->ensureSetting('va_global_id', 'VA Global ID');
        $this->ensureSetting('registry.public_key', 'Registry Public Key', self::REGISTRY_PUBLIC_KEY);

        // Deliver the pinned key to installs whose row was seeded empty before
        // the key shipped. Never overwrites a non-empty value, so an operator
        // key set via `php artisan registry:set-key` survives.
        DB::table('settings')
            ->where('id', Setting::formatKey('registry.public_key'))
            ->where('value', '')
            ->update(['value' => self::REGISTRY_PUBLIC_KEY, 'updated_at' => now()]);

        // Resolve va_global_id, preferring an already-populated value.
        $current = (string) (DB::table('settings')
            ->where('id', Setting::formatKey('va_global_id'))
            ->value('value') ?? '');

        if ($current !== '') {
            return;
        }

        $kvpValue = Schema::hasTable('kvp')
            ? (string) (DB::table('kvp')->where('key', 'va_global_id')->value('value') ?? '')
            : '';

        if ($kvpValue !== '') {
            $this->setSettingValue('va_global_id', $kvpValue);

            return;
        }

        $ulid = (string) Str::ulid();
        $this->setSettingValue('va_global_id', $ulid);

        // Write the same value to kvp so the ACARS plugin's lazy read agrees.
        if (Schema::hasTable('kvp') && DB::table('kvp')->where('key', 'va_global_id')->doesntExist()) {
            DB::table('kvp')->insert(['key' => 'va_global_id', 'value' => $ulid]);
        }
    }

    /**
     * Insert a hidden setting row when it does not already exist. Never touches
     * an existing row (preserves an operator- or seeder-set value).
     */
    private function ensureSetting(string $key, string $name, string $value = ''): void
    {
        $id = Setting::formatKey($key);

        if (DB::table('settings')->where('id', $id)->exists()) {
            return;
        }

        DB::table('settings')->insert([
            'id'          => $id,
            'key'         => strtolower($key),
            'name'        => $name,
            'value'       => $value,
            'default'     => $value,
            'group'       => 'general',
            'type'        => 'hidden',
            'options'     => '',
            'description' => '',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    private function setSettingValue(string $key, string $value): void
    {
        DB::table('settings')
            ->where('id', Setting::formatKey($key))
            ->update(['value' => $value, 'updated_at' => now()]);
    }
};
