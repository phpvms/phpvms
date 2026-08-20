<?php

declare(strict_types=1);

use App\Features\Assets\AssetService;
use App\Filament\Concerns\AutosavesFields;
use App\Filament\Forms\Components\AssetImagePicker;
use App\Models\Airline;
use App\Models\Asset;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\Livewire;

/**
 * The picker is a schema helper with no page of its own, so the harnesses below
 * stand in for the two kinds of host it supports: a page that autosaves each
 * field, and a create page whose record does not exist until submit.
 *
 * Airline is the stand-in record — any model with a key would do; the picker
 * only ever asks the resolver for one.
 */
const PICKER_SLOT = 'test-image';

function pickerDisk(): string
{
    return (string) config('filesystems.public_files');
}

function pickerAsset(int|string $key): ?Asset
{
    return app(AssetService::class)->find(PICKER_SLOT, (string) $key);
}

/**
 * Edit-shaped host: fills from the record and autosaves each field through the
 * shared trait, exactly as EditAirline does for the airline logo.
 *
 * @property-read Schema $form
 */
class AssetPickerHarness extends Component implements HasForms
{
    use AutosavesFields;
    use InteractsWithForms;

    public ?array $data = [];

    public ?string $recordId = null;

    public function mount(): void
    {
        $this->form->fill($this->record()?->attributesToArray() ?? []);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                AssetImagePicker::make(
                    PICKER_SLOT,
                    fn (?Airline $record): int|string|null => $record?->id,
                    pickerDisk(),
                ),
            ])
            ->record($this->record())
            ->statePath('data');
    }

    public function record(): ?Airline
    {
        return $this->recordId === null ? null : Airline::query()->find($this->recordId);
    }

    protected function autosaveKeys(): array
    {
        return AssetImagePicker::stateKeys(PICKER_SLOT);
    }

    protected function persistAutosavedField(string $key, mixed $value): void
    {
        AssetImagePicker::persist(PICKER_SLOT, $this->record()?->id, pickerDisk(), $key, $value);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

/**
 * Create-shaped host: no record, no autosave. The record is created first, then
 * the picker's state is persisted against the key that only exists afterwards.
 * A real create page also keeps these keys out of the attributes it creates the
 * model with, which is what stateKeys() is for.
 *
 * @property-read Schema $form
 */
class AssetPickerCreateHarness extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];

    public ?string $createdId = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                AssetImagePicker::make(
                    PICKER_SLOT,
                    fn (?Airline $record): int|string|null => $record?->id,
                    pickerDisk(),
                ),
            ])
            ->statePath('data');
    }

    public function create(): void
    {
        $state = $this->form->getState();

        $airline = Airline::factory()->create(['icao' => 'NEW', 'iata' => 'NW', 'country' => 'us']);

        AssetImagePicker::persistState(PICKER_SLOT, $airline->id, pickerDisk(), $state);

        $this->createdId = (string) $airline->id;
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

beforeEach(function (): void {
    fakeAssetDisks();
    $this->actingAs(createAdminUser());

    $this->airline = Airline::factory()->create(['icao' => 'ABC', 'iata' => 'AB', 'country' => 'us']);
});

it('offers both sources and shows one input at a time', function (): void {
    $component = Livewire::test(AssetPickerHarness::class, ['recordId' => $this->airline->id])
        ->assertFormFieldExists('test_image_source')
        ->assertFormFieldIsVisible('test_image_upload')
        ->assertFormFieldIsHidden('test_image_url');

    $component->set('data.test_image_source', AssetImagePicker::SOURCE_URL)
        ->assertFormFieldIsHidden('test_image_upload')
        ->assertFormFieldIsVisible('test_image_url');
});

it('stores an upload as an asset on the caller disk', function (): void {
    Livewire::test(AssetPickerHarness::class, ['recordId' => $this->airline->id])
        ->set('data.test_image_upload', UploadedFile::fake()->image('pic.png', 32, 32));

    $asset = pickerAsset($this->airline->id);

    expect($asset)->not->toBeNull()
        ->and($asset->slot)->toBe(PICKER_SLOT)
        ->and($asset->storage)->toBe(pickerDisk());

    Storage::disk($asset->diskName())->assertExists($asset->path);

    // Staging keeps no copy.
    expect(Storage::disk(Asset::STORAGE_LOCAL)->files(Asset::PATH_PREFIX.'/staging'))->toBeEmpty();
});

it('stores an entered URL as a link asset with no file written', function (): void {
    Livewire::test(AssetPickerHarness::class, ['recordId' => $this->airline->id])
        ->set('data.test_image_source', AssetImagePicker::SOURCE_URL)
        ->set('data.test_image_url', 'https://cdn.example.com/badge.png');

    $asset = pickerAsset($this->airline->id);

    expect($asset)->not->toBeNull()
        ->and($asset->storage)->toBe(Asset::STORAGE_URL)
        ->and($asset->path)->toBe('https://cdn.example.com/badge.png')
        ->and($asset->isLink())->toBeTrue()
        // Nothing was sniffed, because nothing was read.
        ->and($asset->content_type)->toBeNull()
        ->and($asset->url())->toBe('https://cdn.example.com/badge.png');

    expect(Storage::disk(pickerDisk())->allFiles())->toBeEmpty()
        ->and(Storage::disk(Asset::STORAGE_LOCAL)->allFiles())->toBeEmpty();
});

it('replaces the stored file when the source switches to URL', function (): void {
    $component = Livewire::test(AssetPickerHarness::class, ['recordId' => $this->airline->id])
        ->set('data.test_image_upload', UploadedFile::fake()->image('pic.png', 32, 32));

    $stored = pickerAsset($this->airline->id);
    expect($stored->isLink())->toBeFalse();

    $component->set('data.test_image_source', AssetImagePicker::SOURCE_URL)
        ->set('data.test_image_url', 'https://cdn.example.com/badge.png');

    $link = pickerAsset($this->airline->id);

    expect($link->id)->toBe($stored->id)
        ->and($link->storage)->toBe(Asset::STORAGE_URL);

    Storage::disk($stored->diskName())->assertMissing($stored->path);
});

it('deletes the asset when the upload is cleared', function (): void {
    $component = Livewire::test(AssetPickerHarness::class, ['recordId' => $this->airline->id])
        ->set('data.test_image_upload', UploadedFile::fake()->image('pic.png', 32, 32));

    $stored = pickerAsset($this->airline->id);

    $component->set('data.test_image_upload');

    expect(pickerAsset($this->airline->id))->toBeNull();
    Storage::disk($stored->diskName())->assertMissing($stored->path);
});

it('opens in URL mode on a link asset and shows the stored URL', function (): void {
    app(AssetService::class)->storeLink('https://cdn.example.com/badge.png', PICKER_SLOT, (string) $this->airline->id);

    Livewire::test(AssetPickerHarness::class, ['recordId' => $this->airline->id])
        ->assertSet('data.test_image_source', AssetImagePicker::SOURCE_URL)
        ->assertSet('data.test_image_url', 'https://cdn.example.com/badge.png');
});

it('opens in upload mode on a stored asset', function (): void {
    app(AssetService::class)->storeContents(
        ASSET_TEST_PNG,
        PICKER_SLOT,
        (string) $this->airline->id,
        storage: pickerDisk(),
    );

    Livewire::test(AssetPickerHarness::class, ['recordId' => $this->airline->id])
        ->assertSet('data.test_image_source', AssetImagePicker::SOURCE_UPLOAD)
        ->assertSet('data.test_image_url', null);
});

it('opens in upload mode with no preview when no asset exists', function (): void {
    Livewire::test(AssetPickerHarness::class, ['recordId' => $this->airline->id])
        ->assertSet('data.test_image_source', AssetImagePicker::SOURCE_UPLOAD)
        // Nothing resolves, so the preview is not rendered at all. The path
        // prefix is what a rendered preview's src would carry.
        ->assertDontSee(Asset::PATH_PREFIX.'/'.PICKER_SLOT, escape: false);

    expect(pickerAsset($this->airline->id))->toBeNull();
});

/**
 * The preview reads the asset's own URL. Storage::fake() leaves the disk's `url`
 * config in place, so a public row still resolves to a string — assert the shape
 * rather than the value.
 */
it('renders the preview from the resolved asset URL', function (): void {
    $asset = app(AssetService::class)->storeContents(
        ASSET_TEST_PNG,
        PICKER_SLOT,
        (string) $this->airline->id,
        storage: pickerDisk(),
    );

    expect($asset->url())->toBeString();

    Livewire::test(AssetPickerHarness::class, ['recordId' => $this->airline->id])
        ->assertSee($asset->url(), escape: false);
});

it('keys the asset on the new record when the create page saves', function (): void {
    $component = Livewire::test(AssetPickerCreateHarness::class)
        ->set('data.test_image_upload', UploadedFile::fake()->image('pic.png', 32, 32));

    // Nothing yet: there is no record to key an asset on.
    expect(Asset::query()->slot(PICKER_SLOT)->count())->toBe(0);

    $component->call('create');

    $createdId = $component->get('createdId');

    expect($createdId)->not->toBeNull()
        ->and(pickerAsset($createdId))->not->toBeNull()
        ->and(pickerAsset($createdId)->storage)->toBe(pickerDisk());
});

it('creates no asset when a staged upload is abandoned', function (): void {
    $component = Livewire::test(AssetPickerCreateHarness::class)
        ->set('data.test_image_upload', UploadedFile::fake()->image('pic.png', 32, 32));

    // The upload really is held in form state — the point is that holding it is
    // not an asset, not that the drop silently did nothing. Filament only moves
    // it onto a disk when the form's state is resolved, which on a create page
    // is the submit that never happened here.
    expect($component->get('data.test_image_upload'))->not->toBeEmpty()
        ->and(Storage::disk(Asset::STORAGE_LOCAL)->files(Asset::PATH_PREFIX.'/staging'))->toBeEmpty()
        ->and(Asset::query()->slot(PICKER_SLOT)->count())->toBe(0);
});

it('refuses a link URL that is not http(s)', function (): void {
    expect(fn () => app(AssetService::class)->storeLink('javascript:alert(1)', PICKER_SLOT, (string) $this->airline->id))
        ->toThrow(InvalidArgumentException::class);

    expect(pickerAsset($this->airline->id))->toBeNull();
});
