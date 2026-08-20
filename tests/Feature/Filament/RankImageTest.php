<?php

declare(strict_types=1);

use App\Features\Assets\AssetService;
use App\Filament\Forms\Components\AssetImagePicker;
use App\Filament\Resources\Ranks\Pages\CreateRank;
use App\Filament\Resources\Ranks\Pages\EditRank;
use App\Models\Asset;
use App\Models\Rank;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function (): void {
    fakeAssetDisks();
    $this->seed(RolesPermissionsSeeder::class);
    $this->actingAs(createAdminUser());

    $this->rank = Rank::factory()->create(['image_url' => null]);
});

function rankImageAsset(Rank $rank): ?Asset
{
    return app(AssetService::class)->find(Asset::SLOT_RANK, (string) $rank->id);
}

it('no longer renders a bare image_url input', function (): void {
    Livewire::test(EditRank::class, ['record' => $this->rank->id])
        ->assertFormFieldDoesNotExist('image_url')
        ->assertFormFieldExists('rank_source')
        ->assertFormFieldIsVisible('rank_upload');
});

it('stores an uploaded badge as the rank asset', function (): void {
    Livewire::test(EditRank::class, ['record' => $this->rank->id])
        ->set('data.rank_upload', UploadedFile::fake()->image('badge.png', 32, 32));

    $asset = rankImageAsset($this->rank);

    expect($asset)->not->toBeNull()
        ->and($asset->slot)->toBe(Asset::SLOT_RANK)
        ->and($asset->storage)->toBe(config('filesystems.public_files'));

    Storage::disk($asset->diskName())->assertExists($asset->path);

    // And the accessor reads the asset rather than the legacy column.
    expect($this->rank->refresh()->image_url)->toBe($asset->url());
});

it('stores an entered badge URL as a link asset', function (): void {
    Livewire::test(EditRank::class, ['record' => $this->rank->id])
        ->set('data.rank_source', AssetImagePicker::SOURCE_URL)
        ->set('data.rank_url', 'https://cdn.example.com/captain.png');

    $asset = rankImageAsset($this->rank);

    expect($asset)->not->toBeNull()
        ->and($asset->storage)->toBe(Asset::STORAGE_URL)
        ->and($asset->path)->toBe('https://cdn.example.com/captain.png')
        ->and($this->rank->refresh()->image_url)->toBe('https://cdn.example.com/captain.png');
});

/**
 * The picker is edit-only: with no rank id there is nothing to key an asset on,
 * so the control is not offered until the rank exists.
 */
it('does not render the image control on the create page', function (): void {
    Livewire::test(CreateRank::class)
        ->assertFormFieldDoesNotExist('rank_source')
        ->assertFormFieldDoesNotExist('rank_upload')
        ->assertFormFieldDoesNotExist('rank_url')
        // The section that wrapped it goes too, rather than leaving a heading.
        ->assertDontSee(__('filament.rank_images'));
});

it('creates a rank with no image state to carry', function (): void {
    Livewire::test(CreateRank::class)
        ->fillForm(['name' => 'Ponytail Captain', 'hours' => 100])
        ->call('create')
        ->assertHasNoFormErrors();

    $rank = Rank::where('name', 'Ponytail Captain')->firstOrFail();

    expect(rankImageAsset($rank))->toBeNull()
        ->and($rank->image_url)->toBeNull();
});

it('opens in URL mode on a link asset', function (): void {
    app(AssetService::class)->storeLink('https://cdn.example.com/captain.png', Asset::SLOT_RANK, (string) $this->rank->id);

    Livewire::test(EditRank::class, ['record' => $this->rank->id])
        ->assertSet('data.rank_source', AssetImagePicker::SOURCE_URL)
        ->assertSet('data.rank_url', 'https://cdn.example.com/captain.png');
});
