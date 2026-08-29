<?php

declare(strict_types=1);

use App\Features\Assets\AssetService;
use App\Features\Assets\AssetTypes;
use App\Models\Asset;
use App\Models\User;
use App\Traits\HasAssets;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * A model using the trait. User is the stand-in because `user` is a slot core
 * already declares and a user owns its own key — the two things the trait
 * needs. Ranks get the trait in a later step.
 */
class UserWithAsset extends User
{
    use HasAssets;

    public function assetSlot(): string
    {
        return Asset::SLOT_USER;
    }
}

function linkAsset(string $slot, string $key, string $url): Asset
{
    return Asset::create([
        'key'         => $key,
        'slot'        => $slot,
        'type'        => AssetTypes::IMAGE,
        'path'        => $url,
        'storage'     => Asset::STORAGE_URL,
        'last_update' => 'x',
        'size'        => 0,
    ]);
}

/** A user of the trait-using subclass; the factory only knows the parent. */
function userWithAsset(): UserWithAsset
{
    return UserWithAsset::query()->findOrFail(User::factory()->create()->id);
}

it('resolves the URL of the asset keyed by the model', function (): void {
    $user = userWithAsset();
    $asset = linkAsset(Asset::SLOT_USER, (string) $user->id, 'https://cdn.example.com/avatar.png');

    // Guard: another user's asset in the same slot must not answer for this one.
    linkAsset(Asset::SLOT_USER, (string) ($user->id + 1), 'https://cdn.example.com/someone-else.png');

    expect($user->assetUrl())->toBe($asset->url())
        ->and($user->assetUrl())->toBe('https://cdn.example.com/avatar.png');
});

it('returns null from Asset::getUrl for an unknown slot and key', function (): void {
    linkAsset(Asset::SLOT_BRANDING, 'logo', 'https://cdn.example.com/logo.png');

    expect(Asset::getUrl(Asset::SLOT_BRANDING, 'nope'))->toBeNull()
        ->and(Asset::getUrl('no-such-slot', 'logo'))->toBeNull()
        // Guard: the pair that does exist resolves, so the nulls above are the
        // lookup missing and not the lookup being broken.
        ->and(Asset::getUrl(Asset::SLOT_BRANDING, 'logo'))->toBe('https://cdn.example.com/logo.png');
});

/*
 * bootHasAssets() -- cleanup on real delete, preserved across a soft delete
 */

it('deletes the asset and its file when the model is force-deleted', function (): void {
    fakeAssetDisks();
    $user = userWithAsset();
    $asset = app(AssetService::class)->storeContents(ASSET_TEST_PNG, Asset::SLOT_USER, (string) $user->id, storage: (string) config('filesystems.public_files'));
    Storage::disk($asset->diskName())->assertExists($asset->path);

    $user->forceDelete();

    expect(Asset::query()->whereKey($asset->id)->exists())->toBeFalse();
    Storage::disk($asset->diskName())->assertMissing($asset->path);
});

it('keeps the asset and its file when the model is only soft-deleted, and it still resolves after restore', function (): void {
    fakeAssetDisks();
    $user = userWithAsset();
    $asset = app(AssetService::class)->storeContents(ASSET_TEST_PNG, Asset::SLOT_USER, (string) $user->id, storage: (string) config('filesystems.public_files'));

    $user->delete();

    expect(Asset::query()->whereKey($asset->id)->exists())->toBeTrue();
    Storage::disk($asset->diskName())->assertExists($asset->path);

    $user->restore();

    expect($user->assetUrl())->toBe($asset->fresh()->url());
});

it('does nothing for a model with no asset at that slot and key', function (): void {
    $user = userWithAsset();

    // No asset stored -- deleting must not throw for lack of a row to clean up.
    $user->forceDelete();

    expect($user->assetUrl())->toBeNull();
});

/*
 * preloadAssetUrls() -- batched lookup that short-circuits assetUrl()
 */

it('preloads urls for a batch of models in one query and stashes them on assetUrl()', function (): void {
    $one = userWithAsset();
    $two = userWithAsset();
    linkAsset(Asset::SLOT_USER, (string) $one->id, 'https://cdn.example.com/one.png');
    // $two has no asset -- proves the map's "absent" case resolves to null
    // rather than $one's url bleeding across.

    DB::enableQueryLog();
    UserWithAsset::preloadAssetUrls(collect([$one, $two]));
    $assetQueries = collect(DB::getQueryLog())->filter(fn (array $q): bool => str_contains($q['query'], 'assets'));
    DB::flushQueryLog();

    expect($assetQueries)->toHaveCount(1)
        ->and($one->assetUrl())->toBe('https://cdn.example.com/one.png')
        ->and($two->assetUrl())->toBeNull();

    // Both reads above came from the stash, not a fresh query each.
    expect(DB::getQueryLog())->toHaveCount(0);
    DB::disableQueryLog();
});

it('does nothing for an empty collection', function (): void {
    DB::enableQueryLog();
    UserWithAsset::preloadAssetUrls(collect());
    $assetQueries = collect(DB::getQueryLog())->filter(fn (array $q): bool => str_contains($q['query'], 'assets'));
    DB::disableQueryLog();

    expect($assetQueries)->toHaveCount(0);
});
