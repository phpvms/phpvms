<?php

declare(strict_types=1);

use App\Enums\NavigationGroup;
use App\Exceptions\AutosaveFailed;
use App\Features\Assets\AssetService;
use App\Filament\Pages\Branding;
use App\Jobs\GenerateBrandingSizes;
use App\Models\Asset;
use App\Models\Permission;
use App\Models\Setting;
use App\Models\User;
use App\Services\ImageUploadService;
use App\Support\Branding as BrandingSupport;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    // Every upload here lands on the asset disk, staging included.
    fakeAssetDisks();
});

function brandingAsset(string $key): ?Asset
{
    return app(AssetService::class)->find(Asset::SLOT_BRANDING, $key);
}

function brandingUser(string ...$permissions): User
{
    $user = User::factory()->create();

    foreach ($permissions as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        $user->givePermissionTo($name);
    }

    return $user->fresh();
}

it('renders for a user with the view permission', function (): void {
    $this->actingAs(brandingUser('view:branding'));

    Livewire::test(Branding::class)->assertSuccessful();
});

it('appears in the Config navigation group', function (): void {
    expect(Branding::getNavigationGroup())->toBe(NavigationGroup::Config);
});

it('denies access without the view permission', function (): void {
    $this->actingAs(brandingUser());

    expect(Branding::canAccess())->toBeFalse();
});

it('saves the airline name to general.site_name', function (): void {
    $this->actingAs(brandingUser('view:branding', 'edit:branding'));

    Livewire::test(Branding::class)
        ->fillForm([
            'general'  => ['site_name' => 'Acme Air'],
            'branding' => ['brand_color' => '#4f46e5'],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Setting::where('id', Setting::formatKey('general.site_name'))->value('value'))->toBe('Acme Air')
        ->and(Setting::where('id', Setting::formatKey('branding.brand_color'))->value('value'))->toBe('#4f46e5');
});

it('saves a palette name to branding.brand_color', function (): void {
    $this->actingAs(brandingUser('view:branding', 'edit:branding'));

    Livewire::test(Branding::class)
        ->fillForm([
            'general'  => ['site_name' => 'Acme Air'],
            'branding' => ['brand_color' => 'blue'],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Setting::where('id', Setting::formatKey('branding.brand_color'))->value('value'))->toBe('blue');
});

it('rejects an invalid hex colour and writes nothing', function (): void {
    $this->actingAs(brandingUser('view:branding', 'edit:branding'));

    Livewire::test(Branding::class)
        ->fillForm([
            'general'  => ['site_name' => 'Acme Air'],
            'branding' => ['brand_color' => 'not-a-color'],
        ])
        ->call('save')
        ->assertHasFormErrors(['branding.brand_color']);

    expect(Setting::where('id', Setting::formatKey('general.site_name'))->value('value'))->not->toBe('Acme Air')
        ->and(Setting::where('id', Setting::formatKey('branding.brand_color'))->value('value'))->toBe('');
});

it('autosaves the logo upload without calling save', function (): void {
    $this->actingAs(brandingUser('view:branding', 'edit:branding'));

    Livewire::test(Branding::class)
        ->set('data.logo', UploadedFile::fake()->image('logo.png', 64, 64))
        ->assertDispatched('autosaved');

    $asset = brandingAsset(BrandingSupport::KEY_LOGO);

    expect($asset)->not->toBeNull()
        ->and($asset->content_type)->toBe('image/webp')
        // Branding renders on the login screen, so it has to be reachable
        // without a session.
        ->and($asset->storage)->toBe(config('filesystems.public_files'))
        ->and(Setting::where('id', Setting::formatKey('general.site_name'))->value('value'))->not->toBe('Acme Air');

    // The staging copy is not left behind on the disk.
    expect(Storage::disk(Asset::STORAGE_LOCAL)->files(Asset::PATH_PREFIX.'/staging'))->toBeEmpty();
});

/**
 * Clearing the field removes the asset outright rather than leaving a row
 * pointing at nothing. The bytes and the row are one thing.
 */
it('deletes the asset when the upload is cleared', function (): void {
    $this->actingAs(brandingUser('view:branding', 'edit:branding'));

    $component = Livewire::test(Branding::class)
        ->set('data.logo', UploadedFile::fake()->image('logo.png', 64, 64));

    $stored = brandingAsset(BrandingSupport::KEY_LOGO);
    expect($stored)->not->toBeNull();

    $component->set('data.logo');

    expect(brandingAsset(BrandingSupport::KEY_LOGO))->toBeNull();
    Storage::disk($stored->diskName())->assertMissing($stored->path);
});

/**
 * Every admin image upload routes through ImageUploadService. With no
 * WebP-capable driver it must degrade to storing the upload unconverted
 * rather than throwing -- an install without GD/Imagick webp support has to
 * keep working. Forcing this via a partial mock of webpDriver() (rather than
 * disabling the real extension) is what proves the service's fallback branch
 * runs at all: reverting ImageUploadService::store()'s `$driver === null`
 * branch to always attempt ->encode('webp', ...) makes this fail, because
 * the mocked driver name ('gd') is still handed to a fresh ImageManager that
 * really does have WebP support, so encoding would still succeed and the
 * stored file would still end in .webp.
 */
it('stores the original file unconverted when no webp driver is available', function (): void {
    $mock = Mockery::mock(ImageUploadService::class)->makePartial();
    $mock->shouldReceive('webpDriver')->andReturn(null);
    app()->instance(ImageUploadService::class, $mock);

    $this->actingAs(brandingUser('view:branding', 'edit:branding'));

    Livewire::test(Branding::class)
        ->set('data.logo', UploadedFile::fake()->image('logo.png', 64, 64))
        ->assertDispatched('autosaved');

    expect(brandingAsset(BrandingSupport::KEY_LOGO)?->content_type)->toBe('image/png');
});

it('dispatches GenerateBrandingSizes when the logo is autosaved', function (): void {
    Queue::fake();

    $this->actingAs(brandingUser('view:branding', 'edit:branding'));

    Livewire::test(Branding::class)
        ->set('data.logo', UploadedFile::fake()->image('logo.png', 64, 64))
        ->assertDispatched('autosaved');

    $asset = brandingAsset(BrandingSupport::KEY_LOGO);

    Queue::assertPushed(
        GenerateBrandingSizes::class,
        fn (GenerateBrandingSizes $job): bool => $job->assetId === $asset->id,
    );
});

it('autosaves the dark logo upload without calling save', function (): void {
    $this->actingAs(brandingUser('view:branding', 'edit:branding'));

    Livewire::test(Branding::class)
        ->set('data.logo_dark', UploadedFile::fake()->image('logo-dark.png', 64, 64))
        ->assertDispatched('autosaved');

    expect(brandingAsset(BrandingSupport::KEY_LOGO_DARK))->not->toBeNull()
        ->and(Setting::where('id', Setting::formatKey('general.site_name'))->value('value'))->not->toBe('Acme Air');
});

it('does not dispatch GenerateBrandingSizes when the dark logo is autosaved', function (): void {
    Queue::fake();

    $this->actingAs(brandingUser('view:branding', 'edit:branding'));

    Livewire::test(Branding::class)
        ->set('data.logo_dark', UploadedFile::fake()->image('logo-dark.png', 64, 64))
        ->assertDispatched('autosaved');

    Queue::assertNotPushed(GenerateBrandingSizes::class);
});

it('does not dispatch GenerateBrandingSizes when the banner is autosaved', function (): void {
    Queue::fake();

    $this->actingAs(brandingUser('view:branding', 'edit:branding'));

    Livewire::test(Branding::class)
        ->set('data.banner', UploadedFile::fake()->image('banner.png', 64, 64))
        ->assertDispatched('autosaved');

    Queue::assertNotPushed(GenerateBrandingSizes::class);
});

/**
 * SettingService::store() only updates rows that already exist, so an install
 * that has not run `migrate-data` persists nothing. The page used to report
 * success anyway, which is how a real "I changed the colour and it did nothing"
 * report came in.
 */
it('reports failure instead of success when a settings row is missing', function (): void {
    $this->actingAs(brandingUser('view:branding', 'edit:branding'));

    Setting::where('id', 'branding_brand_color')->delete();

    Livewire::test(Branding::class)
        ->fillForm([
            'general.site_name'    => 'Acme Air',
            'branding.brand_color' => '#4f46e5',
        ])
        ->call('save')
        ->assertNotified(__('filament.branding_save_failed'));

    expect(Setting::find('branding_brand_color'))->toBeNull();
});

it('still reports success when every row exists', function (): void {
    $this->actingAs(brandingUser('view:branding', 'edit:branding'));

    Livewire::test(Branding::class)
        ->fillForm([
            'general.site_name'    => 'Acme Air',
            'branding.brand_color' => '#4f46e5',
        ])
        ->call('save')
        ->assertNotified(__('filament.branding_saved'));

    expect(Setting::find('branding_brand_color')->value)->toBe('#4f46e5');
});

/**
 * The 1:1 crop is the whole point of the logo uploads -- the derivatives are
 * squares and the favicon/switcher render them small. Assert on the component
 * rather than the markup, since the editor's button is drawn by FilePond at
 * runtime and never appears in the server-rendered HTML.
 */
it('enables a 1:1 image editor on both logo uploads but not the banner', function (): void {
    $this->actingAs(brandingUser('view:branding', 'edit:branding'));

    /** @var Branding $page */
    $page = Livewire::test(Branding::class)->instance();
    $form = $page->form;

    // getComponent() is typed as Action|ActionGroup|Component, so each result
    // is narrowed before the FileUpload-specific calls.
    foreach (['logo', 'logo_dark'] as $key) {
        $upload = $form->getComponent(fn ($component): bool => $component instanceof FileUpload
            && $component->getName() === $key);

        assert($upload instanceof FileUpload);

        expect($upload->hasImageEditor())->toBeTrue()
            // The dialog is opened by auto-open, not by the FilePond edit
            // button -- that button is an overlay on the preview, which
            // previewable(false) removes.
            ->and($upload->shouldAutomaticallyOpenImageEditorForAspectRatio())->toBeTrue()
            ->and($upload->getAutomaticallyOpenImageEditorForAspectRatio())->toBe(1.0);
    }

    $banner = $form->getComponent(fn ($component): bool => $component instanceof FileUpload
        && $component->getName() === 'banner');

    assert($banner instanceof FileUpload);

    expect($banner->hasImageEditor())->toBeFalse();
});

/**
 * runAutosave() persists every key in autosaveKeys(), and the upload fields are
 * never prefilled on mount -- so on a fresh page load the two untouched uploads
 * hold null and used to write '' over settings saved in an earlier session.
 * Uploading a logo silently destroyed the banner and dark logo.
 */
it('does not wipe the other uploads when one is autosaved', function (): void {
    $this->actingAs(brandingUser('view:branding', 'edit:branding'));

    $banner = createBrandingAsset(BrandingSupport::KEY_BANNER);
    $dark = createBrandingAsset(BrandingSupport::KEY_LOGO_DARK);

    Livewire::test(Branding::class)
        ->set('data.logo', UploadedFile::fake()->image('logo.png', 64, 64))
        ->assertDispatched('autosaved');

    // Now that a blank field DELETES its asset, an untouched field being
    // persisted would destroy a file rather than blank a string -- so this is
    // load-bearing in a way it was not before.
    expect(brandingAsset(BrandingSupport::KEY_BANNER)?->id)->toBe($banner->id)
        ->and(brandingAsset(BrandingSupport::KEY_LOGO_DARK)?->id)->toBe($dark->id)
        ->and(brandingAsset(BrandingSupport::KEY_LOGO))->not->toBeNull();

    Storage::disk($banner->diskName())->assertExists($banner->path);
    Storage::disk($dark->diskName())->assertExists($dark->path);
});

/**
 * Missing bytes leave the stored branding alone and report the failure through
 * AutosaveFailed, which the trait turns into the one danger toast.
 */
it('leaves the stored branding alone when the staged file has gone missing', function (): void {
    $this->actingAs(brandingUser('view:branding', 'update:branding'));

    $existing = createBrandingAsset(BrandingSupport::KEY_BANNER);

    // persistAutosavedField is protected -- the trait calls it. Replay it with a
    // staging path that was never written.
    $page = livewireInstance(Branding::class);

    expect(function () use ($page): void {
        (function (): void {
            $this->persistAutosavedField('banner', Asset::PATH_PREFIX.'/staging/gone.png');
        })->call($page);
    })->toThrow(AutosaveFailed::class, __('filament.branding_save_failed'));

    expect(brandingAsset(BrandingSupport::KEY_BANNER)?->id)->toBe($existing->id);
});
