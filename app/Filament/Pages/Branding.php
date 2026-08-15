<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\Ability;
use App\Enums\NavigationGroup;
use App\Filament\Concerns\AuthorizesAccess;
use App\Filament\Concerns\AutosavesFields;
use App\Filament\Concerns\ReversePrimaryButtons;
use App\Jobs\GenerateBrandingSizes;
use App\Services\ImageUploadService;
use App\Services\SettingService;
use App\Support\Branding as BrandingSupport;
use BackedEnum;
use Closure;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Image;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Colors\Color;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Facades\FilamentView;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Js;
use InvalidArgumentException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Override;
use UnitEnum;

/**
 * Config → Branding: airline name, brand colour, logo and banner. Name and
 * colour save on submit; the logo and banner upload fields autosave through
 * {@see AutosavesFields} (following AirlineForm/EditAirline's logo pattern).
 *
 * @property-read Schema $form
 */
class Branding extends Page
{
    use AuthorizesAccess;
    use AutosavesFields;
    use ReversePrimaryButtons;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Config;

    protected static ?int $navigationSort = 0;

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::Palette;

    public ?array $data = [];

    public string $previousUrl;

    /**
     * @return array<int, Ability>
     */
    public static function getPermissionAbilities(): array
    {
        return [Ability::View, Ability::Edit];
    }

    public function mount(): void
    {
        $branding = app(BrandingSupport::class);

        $this->form->fill(Arr::undot([
            'general.site_name'    => $branding->name(),
            'branding.brand_color' => $branding->brandColor(),
        ]));

        $this->previousUrl = url()->previous();
    }

    #[Override]
    public function content(Schema $schema): Schema
    {
        return $schema->components([$this->getFormContentComponent()]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components($this->getFormSchema())
            ->statePath('data');
    }

    /**
     * Only the logo and banner uploads autosave; the name and colour fields
     * save on submit.
     *
     * @return list<string>
     */
    protected function autosaveKeys(): array
    {
        return ['logo', 'logo_dark', 'banner'];
    }

    protected function persistAutosavedField(string $key, mixed $value): void
    {
        // Keyed on autosaveKeys(); an unlisted key means the two have drifted,
        // and failing loudly beats an UnhandledMatchError from a bare match.
        $settingKey = match ($key) {
            'logo'      => 'branding.logo_url',
            'logo_dark' => 'branding.logo_dark_url',
            'banner'    => 'branding.banner_url',
            default     => throw new InvalidArgumentException("No branding setting is mapped to the autosaved field [{$key}]."),
        };
        $path = is_string($value) ? $value : null;

        $url = filled($path)
            ? Storage::disk(config('filesystems.public_files'))->url($path)
            : '';

        app(SettingService::class)->store($settingKey, $url);

        if ($key === 'logo' && filled($path)) {
            GenerateBrandingSizes::dispatch($path);
        }
    }

    protected function autosaveNotificationTitle(): string
    {
        return __('filament.branding_saved');
    }

    public function save(): void
    {
        abort_unless(static::canEdit(), 403);

        try {
            $data = $this->form->getState();

            // store() returns null for a key with no row, and settings rows are
            // seeded by migration -- so an install that has not run
            // `migrate-data` silently persists nothing. Reporting success there
            // is worse than failing: the admin sees a green toast, reloads, and
            // finds the old value with no clue why.
            $missing = DB::transaction(function () use ($data): array {
                $settingService = app(SettingService::class);

                $writes = [
                    'general.site_name'    => $data['general']['site_name'] ?? '',
                    'branding.brand_color' => $data['branding']['brand_color'] ?? '',
                ];

                return array_keys(array_filter(
                    $writes,
                    fn (string $value, string $key): bool => $settingService->store($key, $value) === null,
                    ARRAY_FILTER_USE_BOTH,
                ));
            });

            if ($missing !== []) {
                Notification::make()
                    ->danger()
                    ->title(__('filament.branding_save_failed'))
                    ->body(__('filament.branding_save_failed_body', ['keys' => implode(', ', $missing)]))
                    ->persistent()
                    ->send();

                return;
            }

            Notification::make()
                ->success()
                ->title(__('filament.branding_saved'))
                ->send();
        } catch (Halt) {
            return;
        }
    }

    /**
     * @return array<int, Action>
     */
    protected function getFormActions(): array
    {
        return $this->reversePrimaryButtons([
            $this->getSaveFormAction(),
            $this->getCancelFormAction(),
        ]);
    }

    protected function getSaveFormAction(): Action
    {
        return Action::make('save')
            ->label(__('filament-panels::resources/pages/edit-record.form.actions.save.label'))
            ->submit('save')
            ->keyBindings(['mod+s']);
    }

    protected function getCancelFormAction(): Action
    {
        $url = $this->previousUrl ?? Dashboard::getUrl();

        return Action::make('cancel')
            ->label(__('filament-panels::resources/pages/edit-record.form.actions.cancel.label'))
            ->alpineClickHandler(
                FilamentView::hasSpaMode($url)
                    ? 'document.referrer ? window.history.back() : Livewire.navigate('.Js::from($url).')'
                    : 'document.referrer ? window.history.back() : (window.location.href = '.Js::from($url).')',
            )
            ->color('gray');
    }

    /**
     * Plain `Filament\Pages\Page` has no submit-record concept, so this
     * supplies the method name `ReversePrimaryButtons::getFormContentComponent()`
     * needs -- the same role `EditRecord::getSubmitFormLivewireMethodName()`
     * plays for a resource page.
     */
    protected function getSubmitFormLivewireMethodName(): string
    {
        return 'save';
    }

    /**
     * @return array<int, Section>
     */
    protected function getFormSchema(): array
    {
        return [
            Section::make(__('filament.branding'))
                ->id('branding')
                ->icon(TablerIcon::Palette)
                ->collapsible()
                ->persistCollapsed()
                ->schema([
                    // The airline name sits directly in the card rather than in
                    // a band of its own: it is the card's own subject, and a
                    // head band above the first real subsection reads as a
                    // label for nothing.
                    TextInput::make('general.site_name')
                        ->label(__('filament.branding_airline_name'))
                        ->helperText(__('filament.branding_airline_name_hint'))
                        ->string()
                        ->required(),

                    Section::make(__('filament.branding_admin_colors'))
                        ->id('branding-admin-colors')
                        ->icon(TablerIcon::ColorSwatch)
                        ->collapsible()
                        ->persistCollapsed()
                        // Four columns so the custom picker sits at a quarter
                        // width beside the swatch row rather than stretching
                        // across the card; the swatches span the full width.
                        ->columns(4)
                        ->schema([
                            View::make('filament.pages.branding.color-presets')
                                ->viewData(fn (Get $get): array => [
                                    'palettes' => app(BrandingSupport::class)->palettes(),
                                    'selected' => strtolower((string) $get('branding.brand_color')),
                                ])
                                ->columnSpanFull(),

                            ColorPicker::make('branding.custom_color')
                                ->label(__('filament.branding_admin_colors_custom'))
                                ->helperText(__('filament.branding_admin_colors_custom_hint'))
                                ->hex()
                                // UI-only: this field never holds the saved value
                                // (see the Hidden field below), only a hex the
                                // admin is currently dragging towards. Saving it
                                // would silently overwrite a palette selection
                                // the next time this page loads.
                                ->dehydrated(false)
                                ->default(fn (): string => $this->customColorSeed())
                                ->live()
                                ->afterStateUpdated(function (Set $set, ?string $state): void {
                                    if (is_string($state) && preg_match('/^#[0-9a-fA-F]{6}$/', $state) === 1) {
                                        $set('branding.brand_color', $state);
                                    }
                                }),

                            Hidden::make('branding.brand_color')
                                ->required()
                                ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                    $palettes = app(BrandingSupport::class)->palettes();

                                    if (isset($palettes[strtolower((string) $value)])) {
                                        return;
                                    }

                                    if (preg_match('/^#[0-9a-fA-F]{6}$/', (string) $value) === 1) {
                                        return;
                                    }

                                    $fail(__('filament.branding_admin_colors_invalid'));
                                }),
                        ]),

                    Section::make(__('filament.branding_logo'))
                        ->id('branding-logo')
                        ->icon(TablerIcon::Photo)
                        ->collapsible()
                        ->persistCollapsed()
                        ->columns(2)
                        ->schema([
                            $this->upload('logo')
                                ->helperText(__('filament.branding_logo_hint')),

                            Image::make(
                                url: fn (): string => app(BrandingSupport::class)->logo(),
                                alt: __('filament.branding_logo'),
                            )
                                ->imageHeight('10rem')
                                ->alignCenter(),

                            $this->upload('logo_dark')
                                ->label(__('filament.branding_logo_dark'))
                                ->helperText(__('filament.branding_logo_dark_hint')),

                            Image::make(
                                url: fn (): string => app(BrandingSupport::class)->logoDark(),
                                alt: __('filament.branding_logo_dark'),
                            )
                                ->imageHeight('10rem')
                                ->alignCenter()
                                ->visible(fn (): bool => app(BrandingSupport::class)->hasDarkLogo()),
                        ]),

                    Section::make(__('filament.branding_banner'))
                        ->id('branding-banner')
                        ->icon(TablerIcon::Layout)
                        ->collapsible()
                        ->persistCollapsed()
                        ->columns(2)
                        ->schema([
                            $this->upload('banner')
                                ->helperText(__('filament.branding_banner_hint')),

                            Image::make(
                                url: fn (): ?string => app(BrandingSupport::class)->banner(),
                                alt: __('filament.branding_banner'),
                            )
                                ->imageHeight('10rem')
                                ->alignCenter()
                                ->visible(fn (): bool => filled(app(BrandingSupport::class)->banner())),
                        ]),

                ]),
        ];
    }

    /**
     * Seed value for the custom colour picker: the stored hex verbatim when
     * a hex is stored, or the selected palette's 600 shade converted to hex
     * when a palette name is stored. Reading {@see BrandingSupport::brandPalette()}
     * directly here (rather than the raw hex) would round the input through
     * Filament's generated ramp for a hex value, which is not what was typed.
     */
    private function customColorSeed(): string
    {
        $branding = app(BrandingSupport::class);
        $stored = $branding->brandColor();

        if (isset($branding->palettes()[strtolower($stored)])) {
            return Color::convertToHex($branding->brandPalette()[600]);
        }

        return $stored;
    }

    /**
     * Drag-and-drop upload for the logo/banner. Not bound to the setting
     * key directly -- the field only ever holds a freshly dropped file, and
     * {@see persistAutosavedField()} converts the stored disk path to a URL
     * before writing it to the setting. The current image is rendered by a
     * separate {@see Image} component reading {@see BrandingSupport}
     * directly, so the drop zone never needs to hydrate the existing file.
     *
     * Deterministic filename ("logo.png") so a re-upload overwrites in
     * place and the URL never changes.
     */
    private function upload(string $key): FileUpload
    {
        // Both logos are square by contract: they render as a favicon and a
        // switcher icon, and GenerateBrandingSizes cuts 32/64/180 squares.
        //
        // The crop dialog is opened by `automaticallyOpenImageEditorForAspectRatio()`,
        // NOT by `imageEditor()`. `imageEditor()` only sets
        // isImageEditorExplicitlyEnabled, which turns on FilePond's own edit
        // BUTTON -- and that button is drawn as an overlay on the image preview,
        // which `previewable(false)` removes. Auto-open needs no button: it runs
        // in FilePond's `beforeAddFile` hook (file-upload.js:180-199), so it
        // fires the moment a file is chosen.
        //
        // Note it only opens when the image does NOT already match the ratio
        // (`checkImageAspectRatio` short-circuits at :193). Uploading an
        // already-square logo correctly shows no dialog.
        //
        // `imageAspectRatio()` must hold exactly ONE ratio here -- auto-open
        // throws on a multi-ratio list (FileUpload.php:626-630). The deprecated
        // `imageCropAspectRatio()` is the old name for
        // imageAspectRatio() + automaticallyCropImagesToAspectRatio(), i.e. the
        // silent centre-crop, which is not what we want.
        $isLogo = str_starts_with($key, 'logo');

        return FileUpload::make($key)
            ->label('')
            ->image()
            ->when($isLogo, fn (FileUpload $upload): FileUpload => $upload
                ->imageAspectRatio('1:1')
                ->automaticallyOpenImageEditorForAspectRatio())
            ->previewable(false)
            ->disk(config('filesystems.public_files'))
            ->directory('branding')
            ->visibility('public')
            ->getUploadedFileNameForStorageUsing(
                fn (TemporaryUploadedFile $file): string => $key.'.'.strtolower($file->getClientOriginalExtension())
            )
            // Converts to WebP through the shared upload service instead of
            // storing whatever format was dropped; see ImageUploadService.
            ->saveUploadedFileUsing(
                fn (FileUpload $component, TemporaryUploadedFile $file): string => app(ImageUploadService::class)->storeFilamentUpload($component, $file)
            )
            ->live()
            // The trait's own wiring closure, as the acars-plugin pages use.
            // Hand-rolling `$livewire->runAutosave($component)` here duplicated
            // it; that form is only needed by a schema class shared across
            // pages, where there is no `$this` (see AirlineForm::logoUpload()).
            ->afterStateUpdated($this->autosave());
    }

    #[Override]
    public static function getNavigationLabel(): string
    {
        return __('filament.branding');
    }

    #[Override]
    public function getTitle(): string
    {
        return __('filament.branding');
    }

    #[Override]
    public function getSubheading(): ?string
    {
        return __('filament.branding_subheading');
    }
}
