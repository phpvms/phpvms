<?php

use App\Features\Assets\AssetService;
use App\Models\Asset;
use App\Models\Setting;
use App\Support\Branding;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Data migration: move the branding images out of settings and into `assets`.
 *
 * The six `branding.*_url` keys held plain URL strings pointing at files on the
 * public disk (`database/migrations_data/2026_08_13_000000_branding_settings.php`,
 * written by `App\Filament\Pages\Branding` and `App\Jobs\GenerateBrandingSizes`).
 * `App\Support\Branding` now resolves those images from the `branding` slot
 * instead, so the settings rows are dead once their values have moved. Dead,
 * not gone: they are left in place as `hidden`, which is how they were seeded
 * and what keeps them off the Settings page.
 *
 * `branding.brand_color` and `general.site_name` stay: a colour and a name are
 * not files and have nothing to do with assets.
 *
 * **The URL does not change, because the URL is what moves.** Each value goes
 * in as a link asset — `storage = 'url'`, the URL itself in `path` — and
 * `Asset::url()` hands a link row that string straight back. No file is read,
 * moved or copied, so nothing an install has already published can break.
 *
 * That is why there is no attempt to work out whether the URL points at a file
 * we host. It does not matter: a logo on the public disk and a logo on someone
 * else's CDN are the same thing here, a URL that renders. An earlier version of
 * this migration subtracted the disk's URL to recover a path and adopt the file
 * in place, and dropped anything that did not match — which silently cost an
 * install its branding for a CDN link, or for a URL written before the install
 * moved domain. Link assets did not exist when it was written; they do now.
 *
 * Still best effort at the edges. A value that is not an absolute http(s) URL
 * is refused, and that install's branding falls back to the bundled phpVMS
 * assets until an admin re-uploads — which is exactly what `Branding` does for
 * a fresh install.
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

        $assets = app(AssetService::class);

        foreach ($this->map as $settingKey => $assetKey) {
            $id = Setting::formatKey($settingKey);
            $url = DB::table('settings')->where('id', $id)->value('value');

            if (filled($url)) {
                // The setting holds a URL, and `storage = 'url'` is what an
                // asset that is a URL looks like. Nothing here needs to know
                // whether the bytes are ours: the file is not touched, moved or
                // read, and every consumer already goes through Asset::url(),
                // which hands a link row its `path` back unchanged. That is the
                // same string the setting served, so nothing an install has
                // published changes.
                //
                // Never fail the upgrade over one image. A value that is not an
                // absolute http(s) URL is refused by storeLink() and costs that
                // install a re-upload; aborting here would cost it the upgrade.
                //
                // The SAVEPOINT is load-bearing, not decoration. On Postgres a
                // failed statement aborts the WHOLE transaction, and catching
                // the PHP exception does nothing about that — every later
                // statement then dies with 25P02 ("current transaction is
                // aborted"), including the update below. DB::transaction()
                // inside an open transaction issues SAVEPOINT / ROLLBACK TO
                // SAVEPOINT, which is what actually lets the loop continue.
                // MySQL and SQLite do not need this, which is exactly why the
                // suite (SQLite, see phpunit.xml) cannot catch its absence.
                try {
                    DB::transaction(
                        fn () => $assets->storeLink((string) $url, Asset::SLOT_BRANDING, $assetKey)
                    );
                } catch (Throwable $e) {
                    // Falls back to the bundled asset; admin re-uploads. Logged
                    // rather than swallowed silently — a bare `catch (Throwable)`
                    // here is why the first failure of this migration reported
                    // only its downstream 25P02 and not its actual cause.
                    Log::warning('branding_images_to_assets: could not move branding image', [
                        'setting'   => $settingKey,
                        'asset_key' => $assetKey,
                        'url'       => $url,
                        'error'     => $e->getMessage(),
                    ]);
                }
            }

            // Hidden, not deleted. These keys are already `hidden` as seeded
            // (2026_08_13_000000_branding_settings.php:51-81) and nothing reads
            // them any more — Branding::url() goes through the asset slot — so
            // the row is inert either way, and keeping it costs one dead row.
            DB::table('settings')->where('id', $id)->update(['type' => 'hidden']);
        }
    }

    /**
     * Nothing to undo for an install that ran this migration: up() only flips
     * `type` on rows it leaves in place, and `hidden` is what they were seeded
     * as, so their values are still whatever they held going in.
     *
     * The insert below is for the older shape of this migration, which deleted
     * the rows outright. Those URLs are gone — the files they pointed at are
     * still on the public disk, but which row pointed where is not recorded
     * anywhere — so it can only put the keys back empty.
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
