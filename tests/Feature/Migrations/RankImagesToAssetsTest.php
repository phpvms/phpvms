<?php

declare(strict_types=1);

use App\Features\Assets\AssetService;
use App\Models\Asset;
use App\Models\Rank;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

function rankImagesMigration(): object
{
    return require base_path('database/migrations_data/2026_08_19_000000_rank_images_to_assets.php');
}

function rankPublicDisk()
{
    return Storage::disk(config('filesystems.public_files'));
}

function seedLegacyRankImage(Rank $rank, string $image): void
{
    DB::table('ranks')->where('id', $rank->id)->update(['image_url' => $image]);
}

function rankAsset(Rank $rank): ?Asset
{
    return app(AssetService::class)->find(Asset::SLOT_RANK, (string) $rank->id);
}

beforeEach(function (): void {
    fakeAssetDisks();
});

it('adopts a hosted rank image and clears the column', function (): void {
    $rank = Rank::factory()->create(['image_url' => null]);
    rankPublicDisk()->put('ranks/1.png', ASSET_TEST_PNG);
    $originalUrl = rankPublicDisk()->url('ranks/1.png');
    seedLegacyRankImage($rank, 'ranks/1.png');

    rankImagesMigration()->up();

    $asset = rankAsset($rank);

    expect($asset)->not->toBeNull()
        ->and($asset->storage)->toBe(config('filesystems.public_files'))
        // Adopted, not copied: the URL an install has already published works.
        ->and($asset->path)->toBe('ranks/1.png')
        ->and($asset->url())->toBe($originalUrl)
        ->and(DB::table('ranks')->where('id', $rank->id)->value('image_url'))->toBeNull();

    // One copy of the bytes, where it always was.
    expect(rankPublicDisk()->allFiles())->toBe(['ranks/1.png']);

    // And the accessor still resolves, so no consumer notices the move.
    expect($rank->refresh()->image_url)->toBe($originalUrl);
});

it('moves an external image URL into a link asset', function (): void {
    $rank = Rank::factory()->create(['image_url' => null]);
    seedLegacyRankImage($rank, 'https://cdn.example.com/badge.png');

    rankImagesMigration()->up();

    $asset = rankAsset($rank);

    expect($asset)->not->toBeNull()
        ->and($asset->storage)->toBe(Asset::STORAGE_URL)
        ->and($asset->isLink())->toBeTrue()
        ->and($asset->path)->toBe('https://cdn.example.com/badge.png')
        ->and(DB::table('ranks')->where('id', $rank->id)->value('image_url'))->toBeNull()
        ->and($rank->refresh()->image_url)->toBe('https://cdn.example.com/badge.png');

    // A link owns no bytes.
    expect(rankPublicDisk()->allFiles())->toBeEmpty();
});

/**
 * A file we host but cannot read as an image has nothing to adopt. The upgrade
 * must survive it, and the column has to keep working as the fallback.
 */
it('skips a rank whose file is not a usable image', function (): void {
    $rank = Rank::factory()->create(['image_url' => null]);
    rankPublicDisk()->put('ranks/broken.png', 'not an image at all');
    seedLegacyRankImage($rank, 'ranks/broken.png');

    rankImagesMigration()->up();

    expect(rankAsset($rank))->toBeNull()
        ->and(DB::table('ranks')->where('id', $rank->id)->value('image_url'))->toBe('ranks/broken.png');
});

/**
 * Neither a file on our disk nor an absolute URL — a site-relative path from a
 * legacy import. storeLink() refuses it, which is a skip, not a failure.
 */
it('skips a relative path that is not on the disk', function (): void {
    $rank = Rank::factory()->create(['image_url' => null]);
    seedLegacyRankImage($rank, '/img/ranks/captain.png');

    rankImagesMigration()->up();

    expect(rankAsset($rank))->toBeNull()
        ->and(DB::table('ranks')->where('id', $rank->id)->value('image_url'))->toBe('/img/ranks/captain.png');
});

it('keys each rank to its own image', function (): void {
    $ranks = collect(['a', 'b', 'c'])->map(function (string $marker): Rank {
        $rank = Rank::factory()->create(['image_url' => null]);
        // Distinct bytes, so a mis-keyed adopt cannot pass.
        rankPublicDisk()->put("ranks/{$marker}.png", ASSET_TEST_PNG."\x00".$marker);
        seedLegacyRankImage($rank, "ranks/{$marker}.png");

        return $rank;
    });

    rankImagesMigration()->up();

    $ranks->zip(['a', 'b', 'c'])->each(function ($pair): void {
        [$rank, $marker] = $pair;
        $asset = rankAsset($rank);

        expect($asset)->not->toBeNull()
            ->and(Storage::disk($asset->diskName())->get($asset->path))->toBe(ASSET_TEST_PNG."\x00".$marker);
    });
});

it('is safe to run twice', function (): void {
    $rank = Rank::factory()->create(['image_url' => null]);
    rankPublicDisk()->put('ranks/1.png', ASSET_TEST_PNG);
    seedLegacyRankImage($rank, 'ranks/1.png');

    rankImagesMigration()->up();
    rankImagesMigration()->up();

    expect(Asset::query()->count())->toBe(1);
    rankPublicDisk()->assertExists('ranks/1.png');
});

/**
 * down() points the column back at what the asset held and drops the row. The
 * adopted file never moved, so it must still be there afterwards — deleting the
 * asset through the model would have taken it.
 */
it('restores the column and keeps the file on down', function (): void {
    $hosted = Rank::factory()->create(['image_url' => null]);
    rankPublicDisk()->put('ranks/1.png', ASSET_TEST_PNG);
    seedLegacyRankImage($hosted, 'ranks/1.png');

    $linked = Rank::factory()->create(['image_url' => null]);
    seedLegacyRankImage($linked, 'https://cdn.example.com/badge.png');

    rankImagesMigration()->up();
    rankImagesMigration()->down();

    expect(DB::table('ranks')->where('id', $hosted->id)->value('image_url'))->toBe('ranks/1.png')
        ->and(DB::table('ranks')->where('id', $linked->id)->value('image_url'))->toBe('https://cdn.example.com/badge.png')
        ->and(Asset::query()->count())->toBe(0);

    rankPublicDisk()->assertExists('ranks/1.png');
});
