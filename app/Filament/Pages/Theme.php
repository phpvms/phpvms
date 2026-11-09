<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\Ability;
use App\Enums\NavigationGroup;
use App\Features\Themes\ColorSchemeCss;
use App\Features\Themes\ThemeFields;
use App\Features\Themes\ThemeOptions;
use App\Filament\Concerns\AuthorizesAccess;
use App\Filament\Concerns\ReversePrimaryButtons;
use App\Services\SettingService;
use BackedEnum;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Facades\FilamentView;
use Igaster\LaravelTheme\Facades\Theme as Themes;
use Illuminate\Support\Arr;
use Illuminate\Support\Js;
use Override;
use UnitEnum;

/**
 * Customize → Theme: the theme the site renders with (`general.theme`). The
 * setting row itself is hidden from the Settings page.
 *
 * @property-read Schema $form
 */
class Theme extends Page
{
    use AuthorizesAccess;
    use ReversePrimaryButtons;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Content;

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Phosphor::PaintBrushLight;

    protected string $view = 'filament.pages.theme';

    public ?array $data = [];

    public string $previousUrl;

    /**
     * Names of the selected theme's `css`-tagged fields, in schema order. The
     * live preview concatenates their state.
     *
     * @var array<int, string>
     */
    public array $cssFields = [];

    /**
     * The CSS of the form's current `color_scheme` state; empty when the theme
     * has none or the key is unknown. The live preview puts it before the css
     * fields.
     */
    public string $schemeCss = '';

    /**
     * @return array<int, Ability>
     */
    public static function getPermissionAbilities(): array
    {
        return [Ability::View, Ability::Edit];
    }

    public function mount(): void
    {
        $this->fillOptions((string) setting('general.theme', config('themes.default')));

        $this->previousUrl = url()->previous();
    }

    #[Override]
    public function content(Schema $schema): Schema
    {
        return $schema->components([$this->getFormContentComponent()]);
    }

    /**
     * Two columns on large screens: the theme select and the selected theme's
     * registered option tabs on the left, the live preview on the right. The
     * tabs are built lazily from the form's own `theme` state, so a theme
     * change rebuilds them.
     */
    public function form(Schema $schema): Schema
    {
        return $schema
            ->disabled(fn (): bool => !static::canEdit())
            ->columns(['default' => 1, 'lg' => 3])
            ->components([
                Grid::make(1)
                    ->columnSpan(['lg' => 1])
                    ->schema([$this->themeSection()]),

                ThemeOptions::preview(),
            ])
            ->statePath('data');
    }

    private function themeSection(): Section
    {
        return Section::make(__('filament.theme'))
            ->id('theme')
            ->icon(Phosphor::PaintBrushLight)
            ->collapsible()
            ->persistCollapsed()
            ->schema([
                Select::make('theme')
                    ->label(__('filament.theme_current'))
                    ->helperText(__('filament.theme_current_hint'))
                    ->options(fn (): array => $this->themeOptions())
                    ->in(fn (): array => array_keys($this->themeOptions()))
                    ->required()
                    ->native(false)
                    ->live()
                    ->afterStateUpdated(function (?string $state): void {
                        if ($state && array_key_exists($state, $this->themeOptions())) {
                            $this->fillOptions($state);
                        }
                    }),

                Section::make(__('filament.theme_customize'))
                    ->id('customize')
                    ->key('customize')
                    ->icon(Phosphor::SlidersLight)
                    ->collapsible()
                    ->persistCollapsed()
                    ->schema([
                        Tabs::make('options')
                            ->tabs(fn (Get $get): array => app(ThemeOptions::class)->tabsFor((string) $get('theme'))),
                    ])
                    ->hidden(fn (Get $get): bool => app(ThemeOptions::class)->defaults((string) $get('theme')) === [])
                    ->footerActions([
                        Action::make('resetField')
                            ->label(__('filament.theme_field_reset'))
                            ->color('gray')
                            ->icon(Phosphor::ArrowCounterClockwiseLight)
                            ->modalHeading(__('filament.theme_field_reset'))
                            ->modalDescription(__('filament.theme_field_reset_confirm'))
                            ->visible(fn (): bool => $this->overriddenFields() !== [])
                            ->schema([
                                Select::make('field')
                                    ->label(__('filament.theme_field'))
                                    ->options(fn (): array => $this->overriddenFieldOptions())
                                    ->required()
                                    ->native(false),
                            ])
                            ->action(function (array $data): void {
                                abort_unless(static::canEdit(), 403);

                                $this->resetField((string) $data['field']);

                                Notification::make()
                                    ->success()
                                    ->title(__('filament.theme_field_reset_done'))
                                    ->send();
                            }),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * The submit row belongs in the theme card, which sits inside the
     * left-column Grid where {@see ReversePrimaryButtons::lastFormSection()}
     * (top-level only) would not see it.
     */
    protected function lastFormSection(): ?Section
    {
        $section = $this->getSchema('form')?->getComponent(
            fn (Component $component): bool => $component instanceof Section && $component->getId() === 'theme',
        );

        return $section instanceof Section ? $section : null;
    }

    /**
     * Fills the form for $theme: its defaults, replaced by its stored
     * overrides. Filament applies `default()` only when filled with no data,
     * so the merge is explicit.
     */
    private function fillOptions(string $theme): void
    {
        $defaults = app(ThemeOptions::class)->defaults($theme);
        $fields = app(ThemeFields::class);

        $this->cssFields = array_map(
            strval(...),
            array_keys(array_filter($defaults, fn (array $field): bool => $field['emit'] === 'css')),
        );

        $state = [];

        foreach ($defaults as $name => $field) {
            $override = $fields->override($theme, (string) $name);

            data_set($state, (string) $name, $override ?? $field['default']);
        }

        $this->form->fill([...$state, 'theme' => $theme]);
        $this->refreshSchemeCss();
    }

    /**
     * Livewire hook: runs after any `data.*` property changes.
     */
    public function updatedData(mixed $value, string $key): void
    {
        if ($key === 'color_scheme') {
            $this->refreshSchemeCss();
        }
    }

    private function refreshSchemeCss(): void
    {
        $scheme = config('presets.color_schemes.'.($this->data['color_scheme'] ?? ''));

        $this->schemeCss = is_array($scheme) && ($this->data['color_scheme'] ?? '') !== ''
            ? app(ColorSchemeCss::class)->render($scheme)
            : '';
    }

    /**
     * Deletes one field's stored override and puts its default back in the
     * form. The other fields keep their unsaved edits.
     */
    private function resetField(string $name): void
    {
        $theme = (string) ($this->data['theme'] ?? '');
        $defaults = app(ThemeOptions::class)->defaults($theme);

        if (!array_key_exists($name, $defaults)) {
            return;
        }

        app(ThemeFields::class)->reset($theme, $name);
        data_set($this->data, $name, $defaults[$name]['default']);
        $this->refreshSchemeCss();
    }

    /**
     * The fields of the selected theme that have a stored override, by name.
     *
     * @return array<int, string>
     */
    private function overriddenFields(): array
    {
        $theme = (string) ($this->data['theme'] ?? '');
        $fields = app(ThemeFields::class);

        return array_values(array_filter(
            array_map(strval(...), array_keys(app(ThemeOptions::class)->defaults($theme))),
            fn (string $name): bool => $fields->override($theme, $name) !== null,
        ));
    }

    /**
     * The overridden fields as `[name => label]`, for the reset select.
     *
     * @return array<string, string>
     */
    private function overriddenFieldOptions(): array
    {
        $fields = $this->form->getFlatFields(withHidden: true);
        $options = [];

        foreach ($this->overriddenFields() as $name) {
            $options[$name] = isset($fields[$name]) ? (string) $fields[$name]->getLabel() : $name;
        }

        return $options;
    }

    public function save(): void
    {
        abort_unless(static::canEdit(), 403);

        try {
            $data = $this->form->getState();
            $theme = (string) $data['theme'];

            // store() returns null when the row does not exist; an install that
            // has not run `migrate-data` must hear that rather than see a green
            // toast over a value that never changed.
            if (app(SettingService::class)->store('general.theme', $theme) === null) {
                Notification::make()
                    ->danger()
                    ->title(__('filament.theme_save_failed'))
                    ->body(__('filament.theme_save_failed_body'))
                    ->persistent()
                    ->send();

                return;
            }

            // getState() leaves out non-dehydrated and hidden fields, so their
            // stored rows stay as they are.
            $defaults = app(ThemeOptions::class)->defaults($theme);
            $fields = app(ThemeFields::class);

            foreach (array_keys($defaults) as $name) {
                $name = (string) $name;

                if (!Arr::has($data, $name)) {
                    continue;
                }

                $value = data_get($data, $name);

                // An emptied text field comes back as null; over a non-empty
                // default that is a deliberate blank, which must be stored.
                if ($value === null && is_string($defaults[$name]['default']) && $defaults[$name]['default'] !== '') {
                    $value = '';
                }

                $fields->set($theme, $name, $value);
            }

            Notification::make()
                ->success()
                ->title(__('filament.theme_saved'))
                ->send();
        } catch (Halt) {
            return;
        }
    }

    /**
     * Installed theme names, keyed by themselves. Rebuilds the theme cache
     * first so a theme added since the cache was written is offered.
     *
     * @return array<string, string>
     */
    private function themeOptions(): array
    {
        Themes::rebuildCache();

        $names = [];

        foreach (Themes::all() as $theme) {
            $name = $theme?->name;

            if (!is_string($name) || $name === '' || $name === 'false') {
                continue;
            }

            $names[$name] = $name;
        }

        return $names;
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
     * Plain `Filament\Pages\Page` has no submit-record concept; this supplies
     * the method name `ReversePrimaryButtons::getFormContentComponent()` needs.
     */
    protected function getSubmitFormLivewireMethodName(): string
    {
        return 'save';
    }

    #[Override]
    public static function getNavigationLabel(): string
    {
        return __('filament.theme');
    }

    #[Override]
    public function getTitle(): string
    {
        return __('filament.theme');
    }
}
