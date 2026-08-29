<?php

declare(strict_types=1);

use App\Features\Assets\AssetService;
use App\Models\Asset;
use App\Models\Award;
use App\Models\FlightBundle;
use App\Models\Rank;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    fakeAssetDisks();
});

/**
 * The scenario this command exists for: an asset left behind by a delete that
 * predates HasAssets::bootHasAssets(). $award->forceDelete() would clean up
 * after itself through that hook -- which is exactly the case already
 * covered -- so the row here is removed by a raw query instead, bypassing
 * Eloquent events the same way an old, pre-cleanup-hook delete did.
 */
function orphanAward(): Award
{
    $award = Award::factory()->create(['image_url' => null]);
    app(AssetService::class)->storeContents(ASSET_TEST_PNG, Asset::SLOT_AWARD, (string) $award->id, storage: (string) config('filesystems.public_files'));
    DB::table('awards')->where('id', $award->id)->delete();

    return $award;
}

/**
 * The bug this command exists to fix: nothing cleaned up assets orphaned
 * before HasAssets::bootHasAssets() existed. A row with no owning award left
 * behind by a raw DB delete, predating the cleanup hook.
 */
it('deletes an asset and its file when the owner is really gone', function (): void {
    $award = orphanAward();
    $asset = Asset::query()->slot(Asset::SLOT_AWARD)->where('key', (string) $award->id)->sole();

    Artisan::call('assets:prune-orphans', ['--force' => true]);

    expect(Asset::query()->whereKey($asset->id)->exists())->toBeFalse();
    Storage::disk($asset->diskName())->assertMissing($asset->path);
});

/**
 * The worst possible bug in this command: a soft-deleted award is still
 * restorable, and a restored award must still have its badge. withTrashed()
 * is what tells that apart from a genuinely gone owner.
 */
it('does not touch an asset whose owner is soft-deleted', function (): void {
    $award = Award::factory()->create(['image_url' => null]);
    $asset = app(AssetService::class)->storeContents(ASSET_TEST_PNG, Asset::SLOT_AWARD, (string) $award->id, storage: (string) config('filesystems.public_files'));
    $award->delete();

    Artisan::call('assets:prune-orphans', ['--force' => true]);

    expect(Asset::query()->whereKey($asset->id)->exists())->toBeTrue();
    Storage::disk($asset->diskName())->assertExists($asset->path);
});

it('does not touch an asset whose owner exists', function (): void {
    $award = Award::factory()->create(['image_url' => null]);
    $asset = app(AssetService::class)->storeContents(ASSET_TEST_PNG, Asset::SLOT_AWARD, (string) $award->id, storage: (string) config('filesystems.public_files'));

    Artisan::call('assets:prune-orphans', ['--force' => true]);

    expect(Asset::query()->whereKey($asset->id)->exists())->toBeTrue();
    Storage::disk($asset->diskName())->assertExists($asset->path);
});

/**
 * Sweeps every HasAssets-owned slot, not just the award one.
 */
it('sweeps orphans across every owned slot', function (): void {
    $rank = Rank::factory()->create();
    $rankAsset = app(AssetService::class)->storeContents(ASSET_TEST_PNG, Asset::SLOT_RANK, (string) $rank->id, storage: (string) config('filesystems.public_files'));
    DB::table('ranks')->where('id', $rank->id)->delete();

    $bundle = FlightBundle::factory()->create();
    $bundleAsset = app(AssetService::class)->storeContents(ASSET_TEST_PNG."\x00b", Asset::SLOT_BUNDLE, (string) $bundle->id, storage: (string) config('filesystems.public_files'));
    DB::table('flight_bundles')->where('id', $bundle->id)->delete();

    Artisan::call('assets:prune-orphans', ['--force' => true]);

    expect(Asset::query()->whereKey($rankAsset->id)->exists())->toBeFalse()
        ->and(Asset::query()->whereKey($bundleAsset->id)->exists())->toBeFalse();
});

/**
 * `branding`, `user`, and anything a module registers are an open
 * vocabulary -- a key there is not necessarily a record id, so guessing at
 * ownership would risk deleting live data. These must be reported as
 * skipped, never swept.
 */
it('does not touch branding, user, or module slots even when nothing plausible owns the key', function (): void {
    $branding = app(AssetService::class)->storeContents(ASSET_TEST_PNG, Asset::SLOT_BRANDING, 'logo', storage: (string) config('filesystems.public_files'));
    $userAsset = app(AssetService::class)->storeContents(ASSET_TEST_PNG."\x00u", Asset::SLOT_USER, '999999', storage: (string) config('filesystems.public_files'));
    $moduleAsset = app(AssetService::class)->storeContents(ASSET_TEST_PNG."\x00m", 'paintkit', 'b738', storage: (string) config('filesystems.public_files'));

    Artisan::call('assets:prune-orphans', ['--force' => true]);
    $output = Artisan::output();

    expect(Asset::query()->whereKey($branding->id)->exists())->toBeTrue()
        ->and(Asset::query()->whereKey($userAsset->id)->exists())->toBeTrue()
        ->and(Asset::query()->whereKey($moduleAsset->id)->exists())->toBeTrue()
        ->and($output)->toContain(Asset::SLOT_BRANDING)
        ->and($output)->toContain(Asset::SLOT_USER)
        ->and($output)->toContain('paintkit');
});

/**
 * The safety default: a bare invocation must not destroy anything, only
 * report what it would do.
 */
it('deletes nothing on a bare run and reports what it would do', function (): void {
    $award = orphanAward();
    $asset = Asset::query()->slot(Asset::SLOT_AWARD)->where('key', (string) $award->id)->sole();

    Artisan::call('assets:prune-orphans');
    $output = Artisan::output();

    expect(Asset::query()->whereKey($asset->id)->exists())->toBeTrue();
    Storage::disk($asset->diskName())->assertExists($asset->path);
    expect($output)->toContain('Would delete')
        ->and($output)->toContain((string) $award->id);
});

it('reports zero and touches nothing when there is nothing to sweep', function (): void {
    Artisan::call('assets:prune-orphans', ['--force' => true]);

    expect(Artisan::output())->toContain('Deleted 0 asset(s)');
});
