<?php

use App\Features\Assets\AssetService;
use App\Models\Asset;
use App\Models\Setting;
use App\Support\Branding;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Data migration: move the branding images out of settings and into `assets`.
 *
 * The six `branding.*_url` keys held plain URL strings pointing at files on the
 * public disk (`database/migrations_data/2026_08_13_000000_branding_settings.php`,
 * written by `App\Filament\Pages\Branding` and `App\Jobs\GenerateBrandingSizes`).
 * `App\Support\Branding` now resolves those images from the `branding` slot
 * instead, so the settings rows are dead once their files have moved.
 *
 * `branding.brand_color` and `general.site_name` stay: a colour and a name are
 * not files and have nothing to do with assets.
 *
 * **The URL does not change.** The asset adopts the file exactly where it sits
 * on the public disk rather than copying it to a fresh asset path, because a
 * public asset's URL is derived from its path — copying would silently break
 * every logo URL an install has already published.
 *
 * Best effort otherwise, by design. A value pointing anywhere but the public
 * disk — an admin who pasted an external CDN URL — is not a file we host and
 * cannot become an asset, and a value whose file has since been deleted has
 * nothing to adopt. Both are dropped, and that install's branding falls back to
 * the bundled phpVMS assets until an admin re-uploads, which is exactly what
 * `Branding` does for a fresh install.
 */
return new class() extends Migration
{
    /** Setting key => asset key in the `branding` slot. */
    private array $map = [
        'branding.logo_url'      => Branding::KEY_LOGO,
        'branding.logo_32_url'   => Branding::KEY_LOGO.'-32',
        'branding.logo_64_url'   => Branding::KEY_LOGO.'-64',
        'branding.logo_180_url'  => Branding::KEY_LOGO.'-180',
        'branding.logo_dark_url' => Branding::KEY_LOGO_DARK,
        'branding.banner_url'    => Branding::KEY_BANNER,
    ];

    public function up(): void
    {
        if (!Schema::hasTable('settings') || !Schema::hasTable('assets')) {
            return;
        }

        $disk = Storage::disk(config('filesystems.public_files'));
        $prefix = rtrim($disk->url(''), '/');
        $assets = app(AssetService::class);

        foreach ($this->map as $settingKey => $assetKey) {
            $id = Setting::formatKey($settingKey);
            $url = DB::table('settings')->where('id', $id)->value('value');

            if (filled($url)) {
                $path = str_starts_with((string) $url, $prefix)
                    ? ltrim(substr((string) $url, strlen($prefix)), '/')
                    : null;

                if ($path !== null) {
                    // adopt(), not a copy: the file stays exactly where it is,
                    // so the URL every install has already published keeps
                    // working. Copying to a fresh asset path would change it.
                    //
                    // Never fail the migration over one image either. A missing
                    // or unsupported file costs that install a re-upload;
                    // aborting here would cost it the whole upgrade.
                    //
                    // The SAVEPOINT is load-bearing, not decoration. On Postgres
                    // a failed statement aborts the WHOLE transaction, and
                    // catching the PHP exception does nothing about that — every
                    // later statement then dies with 25P02 ("current transaction
                    // is aborted"), including the delete below. DB::transaction()
                    // inside an open transaction issues SAVEPOINT / ROLLBACK TO
                    // SAVEPOINT, which is what actually lets the loop continue.
                    // MySQL and SQLite do not need this, which is exactly why the
                    // suite (SQLite, see phpunit.xml) cannot catch its absence.
                    try {
                        DB::transaction(
                            fn () => $assets->adopt(
                                $path,
                                Asset::SLOT_BRANDING,
                                $assetKey,
                                storage: (string) config('filesystems.public_files'),
                            )
                        );
                    } catch (Throwable $e) {
                        // Falls back to the bundled asset; admin re-uploads. Logged
                        // rather than swallowed silently — a bare `catch (Throwable)`
                        // here is why the first failure of this migration reported
                        // only its downstream 25P02 and not its actual cause.
                        Log::warning('branding_images_to_assets: could not adopt branding image', [
                            'setting'   => $settingKey,
                            'asset_key' => $assetKey,
                            'path'      => $path,
                            'error'     => $e->getMessage(),
                        ]);
                    }
                }
            }

            DB::table('settings')->where('id', $id)->delete();
        }
    }

    /**
     * Restores the setting rows empty. The URLs they held cannot come back —
     * the files they pointed at are still on the public disk, but which row
     * pointed where is not recorded anywhere after up() has run.
     */
    public function down(): void
    {
        if (!Schema::hasTable('settings')) {
            return;
        }

        $names = [
            'branding.logo_url'      => 'Logo URL',
            'branding.logo_32_url'   => 'Logo URL (32px)',
            'branding.logo_64_url'   => 'Logo URL (64px)',
            'branding.logo_180_url'  => 'Logo URL (180px)',
            'branding.logo_dark_url' => 'Dark Logo URL',
            'branding.banner_url'    => 'Banner URL',
        ];

        foreach ($names as $key => $name) {
            $id = Setting::formatKey($key);

            if (DB::table('settings')->where('id', $id)->exists()) {
                continue;
            }

            DB::table('settings')->insert([
                'id'          => $id,
                'key'         => strtolower($key),
                'name'        => $name,
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
    }
};
