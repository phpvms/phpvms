<?php

declare(strict_types=1);

use App\Enums\AwardTrigger;
use App\Features\Assets\AssetService;
use App\Filament\Forms\Components\AssetImagePicker;
use App\Filament\Resources\Awards\Pages\CreateAward;
use App\Filament\Resources\Awards\Pages\EditAward;
use App\Models\Asset;
use App\Models\Award;
use App\Services\Awards\AwardExport;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function awardImagesMigration(): object
{
    return require base_path('database/migrations_data/2026_08_28_000001_award_images_to_assets.php');
}

function awardPublicDisk(): FilesystemAdapter
{
    return Storage::disk(config('filesystems.public_files'));
}

function seedLegacyAwardImage(Award $award, string $image): void
{
    DB::table('awards')->where('id', $award->id)->update(['image_url' => $image]);
}

function awardAsset(Award $award): ?Asset
{
    return app(AssetService::class)->find(Asset::SLOT_AWARD, (string) $award->id);
}

beforeEach(function (): void {
    fakeAssetDisks();
});

/*
 * Model accessor — Award::imageUrl() / assetSlot()
 */

it('resolves image_url from the asset when one exists', function (): void {
    $award = Award::factory()->create(['image_url' => null]);
    $asset = app(AssetService::class)->storeContents(ASSET_TEST_PNG, Asset::SLOT_AWARD, (string) $award->id, storage: (string) config('filesystems.public_files'));

    expect($award->refresh()->image_url)->toBe($asset->url());
});

it('falls back to the legacy column when there is no asset', function (): void {
    $award = Award::factory()->create(['image_url' => 'https://cdn.example.com/badge.png']);

    expect(awardAsset($award))->toBeNull()
        ->and($award->image_url)->toBe('https://cdn.example.com/badge.png');
});

it('resolves a hosted legacy path through the public disk when there is no asset', function (): void {
    $award = Award::factory()->create(['image_url' => 'awards/legacy.webp']);

    expect($award->image_url)->toBe(awardPublicDisk()->url('awards/legacy.webp'));
});

it('is null with neither an asset nor a legacy value', function (): void {
    $award = Award::factory()->create(['image_url' => null]);

    expect(awardAsset($award))->toBeNull()
        ->and($award->image_url)->toBeNull();
});

it('prefers the asset over a legacy column value that is also set', function (): void {
    $award = Award::factory()->create(['image_url' => 'https://cdn.example.com/old.png']);
    $asset = app(AssetService::class)->storeLink('https://cdn.example.com/new.png', Asset::SLOT_AWARD, (string) $award->id);

    expect($award->refresh()->image_url)->toBe('https://cdn.example.com/new.png')
        ->and($award->image_url)->not->toBe('https://cdn.example.com/old.png');
});

it('aliases the image accessor to image_url', function (): void {
    $award = Award::factory()->create(['image_url' => null]);
    $asset = app(AssetService::class)->storeContents(ASSET_TEST_PNG, Asset::SLOT_AWARD, (string) $award->id, storage: (string) config('filesystems.public_files'));

    expect($award->refresh()->image)->toBe($asset->url());
});

/*
 * Data migration — award_images_to_assets
 */

it('adopts a hosted award image and clears the column', function (): void {
    $award = Award::factory()->create(['image_url' => null]);
    awardPublicDisk()->put('awards/1.webp', ASSET_TEST_PNG);
    $originalUrl = awardPublicDisk()->url('awards/1.webp');
    seedLegacyAwardImage($award, 'awards/1.webp');

    awardImagesMigration()->up();

    $asset = awardAsset($award);

    expect($asset)->not->toBeNull()
        ->and($asset->storage)->toBe(config('filesystems.public_files'))
        // Adopted, not copied: the URL an install has already published works.
        ->and($asset->path)->toBe('awards/1.webp')
        ->and($asset->url())->toBe($originalUrl)
        ->and(DB::table('awards')->where('id', $award->id)->value('image_url'))->toBeNull();

    // One copy of the bytes, where it always was.
    expect(awardPublicDisk()->allFiles())->toBe(['awards/1.webp']);

    // And the accessor still resolves, so no consumer notices the move.
    expect($award->refresh()->image_url)->toBe($originalUrl);
});

it('moves an external image URL into a link asset', function (): void {
    $award = Award::factory()->create(['image_url' => null]);
    seedLegacyAwardImage($award, 'https://cdn.example.com/badge.png');

    awardImagesMigration()->up();

    $asset = awardAsset($award);

    expect($asset)->not->toBeNull()
        ->and($asset->storage)->toBe(Asset::STORAGE_URL)
        ->and($asset->isLink())->toBeTrue()
        ->and($asset->path)->toBe('https://cdn.example.com/badge.png')
        ->and(DB::table('awards')->where('id', $award->id)->value('image_url'))->toBeNull()
        ->and($award->refresh()->image_url)->toBe('https://cdn.example.com/badge.png');

    // A link owns no bytes.
    expect(awardPublicDisk()->allFiles())->toBeEmpty();
});

/**
 * A file we host but cannot read as an image has nothing to adopt. The
 * upgrade must survive it, and the column has to keep working as the
 * fallback.
 */
it('skips an award whose file is not a usable image', function (): void {
    $award = Award::factory()->create(['image_url' => null]);
    awardPublicDisk()->put('awards/broken.webp', 'not an image at all');
    seedLegacyAwardImage($award, 'awards/broken.webp');

    awardImagesMigration()->up();

    expect(awardAsset($award))->toBeNull()
        ->and(DB::table('awards')->where('id', $award->id)->value('image_url'))->toBe('awards/broken.webp');
});

/**
 * Neither a file on our disk nor an absolute URL — a site-relative path from
 * a legacy import. storeLink() refuses it, which is a skip, not a failure.
 */
it('skips a relative path that is not on the disk', function (): void {
    $award = Award::factory()->create(['image_url' => null]);
    seedLegacyAwardImage($award, '/img/awards/trophy.png');

    awardImagesMigration()->up();

    expect(awardAsset($award))->toBeNull()
        ->and(DB::table('awards')->where('id', $award->id)->value('image_url'))->toBe('/img/awards/trophy.png');
});

it('is safe to run twice', function (): void {
    $award = Award::factory()->create(['image_url' => null]);
    awardPublicDisk()->put('awards/1.webp', ASSET_TEST_PNG);
    seedLegacyAwardImage($award, 'awards/1.webp');

    awardImagesMigration()->up();
    awardImagesMigration()->up();

    expect(Asset::query()->count())->toBe(1);
    awardPublicDisk()->assertExists('awards/1.webp');
});

/*
 * Filament — AssetImagePicker wired into EditAward's settings drawer
 */

it('stores an uploaded badge as the award asset', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->actingAs(createAdminUser());
    // A rules-based award (the factory default) validates `trigger` as
    // required, and the drawer's autosave dehydrates the WHOLE mounted
    // action schema — an incomplete record blocks that validation and, with
    // it, the autosave the picker relies on.
    $award = Award::factory()->create(['image_url' => null, 'trigger' => AwardTrigger::Pirep]);

    // ->set(), not setActionData()/fillForm(): fillForm disables the
    // schema's afterStateUpdated hooks for testing, which is exactly what
    // the picker's autosave relies on to persist the asset.
    Livewire::test(EditAward::class, ['record' => $award->id])
        ->mountAction('edit')
        ->set('mountedActions.0.data.award_upload', UploadedFile::fake()->image('badge.png', 32, 32));

    $asset = awardAsset($award);

    expect($asset)->not->toBeNull()
        ->and($asset->slot)->toBe(Asset::SLOT_AWARD)
        ->and($asset->storage)->toBe(config('filesystems.public_files'));

    Storage::disk($asset->diskName())->assertExists($asset->path);

    // And the accessor reads the asset rather than the legacy column.
    expect($award->refresh()->image_url)->toBe($asset->url());
});

it('stores an entered badge URL as a link asset via the edit drawer', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->actingAs(createAdminUser());
    $award = Award::factory()->create(['image_url' => null, 'trigger' => AwardTrigger::Pirep]);

    Livewire::test(EditAward::class, ['record' => $award->id])
        ->mountAction('edit')
        ->set('mountedActions.0.data.award_source', AssetImagePicker::SOURCE_URL)
        ->set('mountedActions.0.data.award_url', 'https://cdn.example.com/captain.png');

    $asset = awardAsset($award);

    expect($asset)->not->toBeNull()
        ->and($asset->storage)->toBe(Asset::STORAGE_URL)
        ->and($asset->path)->toBe('https://cdn.example.com/captain.png')
        ->and($award->refresh()->image_url)->toBe('https://cdn.example.com/captain.png');
});

/**
 * The picker is edit-only: with no award id there is nothing to key an
 * asset on, so the control is not offered until the award exists.
 */
it('does not render the image control on the create page', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->actingAs(createAdminUser());

    Livewire::test(CreateAward::class)
        ->assertFormFieldDoesNotExist('award_source')
        ->assertFormFieldDoesNotExist('award_upload')
        ->assertFormFieldDoesNotExist('award_url');
});

/*
 * Export / import round trip
 */

it('round-trips an award whose badge is an asset', function (): void {
    $award = Award::factory()->create(['image_url' => null, 'trigger' => AwardTrigger::Pirep]);
    app(AssetService::class)->storeContents(
        ASSET_TEST_PNG,
        Asset::SLOT_AWARD,
        (string) $award->id,
        storage: (string) config('filesystems.public_files'),
    );

    $imported = AwardExport::fromJson(AwardExport::toJson($award->refresh()));

    // `toJson()` reads the accessor, so the document carries the *resolved*
    // URL rather than a storage path. The importing install has no asset for
    // the new award, so the accessor falls through to that URL verbatim --
    // which is what keeps an imported badge pointing at a real image instead
    // of resolving a path against a disk that has never held the file.
    expect($imported->image_url)->toBe($award->image_url)
        ->and(awardAsset($imported))->toBeNull();
});

it('round-trips a description as plain text', function (): void {
    $award = Award::factory()->create(['description' => '<p>Ten legs</p><p>One tour</p>']);

    $imported = AwardExport::fromJson(AwardExport::toJson($award->refresh()));

    expect($imported->description)->toBe("Ten legs\nOne tour");
});
