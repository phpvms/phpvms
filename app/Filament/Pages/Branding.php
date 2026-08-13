<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\Ability;
use App\Enums\NavigationGroup;
use App\Filament\Concerns\AuthorizesAccess;
use App\Filament\Concerns\AutosavesFields;
use App\Filament\Concerns\ReversePrimaryButtons;
use App\Services\SettingService;
use App\Support\Branding as BrandingSupport;
use BackedEnum;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Image;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Facades\FilamentView;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Js;
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
        return ['logo', 'banner'];
    }

    protected function persistAutosavedField(string $key, mixed $value): void
    {
        $settingKey = $key === 'logo' ? 'branding.logo_url' : 'branding.banner_url';
        $path = is_string($value) ? $value : null;

        $url = filled($path)
            ? Storage::disk(config('filesystems.public_files'))->url($path)
            : '';

        app(SettingService::class)->store($settingKey, $url);
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

            DB::transaction(function () use ($data): void {
                $settingService = app(SettingService::class);
                $settingService->store('general.site_name', $data['general']['site_name'] ?? '');
                $settingService->store('branding.brand_color', $data['branding']['brand_color'] ?? '');
            });

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
                    Section::make(__('filament.branding_identity'))
                        ->id('branding-identity')
                        ->icon(TablerIcon::IdBadge2)
                        ->collapsible()
                        ->persistCollapsed()
                        ->columns(2)
                        ->schema([
                            TextInput::make('general.site_name')
                                ->label(__('filament.branding_airline_name'))
                                ->helperText(__('filament.branding_airline_name_hint'))
                                ->string()
                                ->required(),

                            ColorPicker::make('branding.brand_color')
                                ->label(__('filament.branding_color'))
                                ->helperText(__('filament.branding_color_hint'))
                                ->hex()
                                ->rule('regex:/^#[0-9a-fA-F]{6}$/')
                                ->validationMessages([
                                    'regex' => __('filament.branding_color_invalid'),
                                ])
                                ->required(),
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
        return FileUpload::make($key)
            ->label('')
            ->image()
            ->previewable(false)
            ->disk(config('filesystems.public_files'))
            ->directory('branding')
            ->visibility('public')
            ->getUploadedFileNameForStorageUsing(
                fn (TemporaryUploadedFile $file): string => $key.'.'.strtolower($file->getClientOriginalExtension())
            )
            ->live()
            ->afterStateUpdated(function (FileUpload $component, $livewire): void {
                $livewire->runAutosave($component);
            });
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
