<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\Ability;
use App\Enums\NavigationGroup;
use App\Features\Themes\ColorSchemeCss;
use App\Features\Themes\ThemeFields;
use App\Filament\Concerns\AuthorizesAccess;
use App\Filament\Concerns\ReversePrimaryButtons;
use App\Http\Middleware\SetActiveTheme;
use App\Services\SettingService;
use BackedEnum;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Actions\Action;
use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Facades\FilamentView;
use Igaster\LaravelTheme\Facades\Theme as Themes;
use Illuminate\Support\Js;
use Override;
use UnitEnum;

/**
 * Content → Theme: the theme the site renders with (`general.theme`). The
 * setting row itself is hidden from the Settings page.
 *
 * @property-read Schema $form
 */
class Theme extends Page
{
    use AuthorizesAccess;
    use ReversePrimaryButtons;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Content;

    protected static ?int $navigationSort = 3;

    protected static string|BackedEnum|null $navigationIcon = Phosphor::PaintBrushLight;

    protected string $view = 'filament.pages.theme';

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
        $theme = (string) setting('general.theme', config('themes.default'));

        $this->form->fill([
            'theme' => $theme,
            'css'   => app(ThemeFields::class)->get($theme, 'css'),
        ]);

        $this->previousUrl = url()->previous();
    }

    #[Override]
    public function content(Schema $schema): Schema
    {
        return $schema->components([$this->getFormContentComponent()]);
    }

    /**
     * Two columns on large screens: the theme select and the Customize card
     * stacked on the left, the live preview on the right.
     */
    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'lg' => 3])
            ->components([
                Grid::make(1)
                    ->columnSpan(['lg' => 1])
                    ->extraAttributes(['class' => 'fi-theme-left'])
                    ->schema([
                        Section::make(__('filament.theme'))
                            ->id('theme')
                            ->icon(Phosphor::PaintBrushLight)
                            ->schema([
                                Select::make('theme')
                                    ->label(__('filament.theme_current'))
                                    ->helperText(__('filament.theme_current_hint'))
                                    ->options(fn (): array => $this->themeOptions())
                                    ->in(fn (): array => array_keys($this->themeOptions()))
                                    ->required()
                                    ->native(false)
                                    ->live()
                                    ->afterStateUpdated(fn (Set $set, ?string $state): mixed => $set(
                                        'css',
                                        $state ? app(ThemeFields::class)->get($state, 'css') : '',
                                    )),
                            ]),

                        Section::make(__('filament.theme_customize'))
                            ->id('customize')
                            ->key('customize')
                            ->icon(Phosphor::SlidersLight)
                            ->schema([
                                Select::make('color_scheme')
                                    ->label(__('filament.theme_color_scheme'))
                                    ->placeholder(__('filament.theme_color_scheme_placeholder'))
                                    ->helperText(__('filament.theme_color_scheme_hint'))
                                    ->options(fn (): array => app(ColorSchemeCss::class)->options())
                                    ->searchable()
                                    ->native(false)
                                    ->live()
                                    ->dehydrated(false)
                                    ->afterStateUpdated(function (Set $set, Get $get, ?string $state): void {
                                        if (!$state) {
                                            return;
                                        }

                                        $scheme = config("presets.color_schemes.$state");
                                        if (!$scheme) {
                                            return;
                                        }

                                        $base = app(ThemeFields::class)->defaultFor((string) $get('theme'), 'css');
                                        $set('css', app(ColorSchemeCss::class)->render($base, $scheme));
                                    }),
                                CodeEditor::make('css')
                                    ->label(__('filament.theme_css'))
                                    ->helperText(__('filament.theme_css_hint'))
                                    ->language(Language::Css),
                            ])
                            ->footerActions([
                                Action::make('resetCss')
                                    ->label(__('filament.theme_css_reset'))
                                    ->color('gray')
                                    ->icon(Phosphor::ArrowCounterClockwiseLight)
                                    ->requiresConfirmation()
                                    ->modalHeading(__('filament.theme_css_reset'))
                                    ->modalDescription(__('filament.theme_css_reset_confirm'))
                                    ->visible(fn (): bool => app(ThemeFields::class)->override((string) ($this->data['theme'] ?? ''), 'css') !== null)
                                    ->action(function (): void {
                                        abort_unless(static::canEdit(), 403);

                                        $theme = (string) ($this->data['theme'] ?? '');
                                        $fields = app(ThemeFields::class);
                                        $fields->reset($theme, 'css');

                                        $this->form->fill([
                                            'theme' => $theme,
                                            'css'   => $fields->get($theme, 'css'),
                                        ]);

                                        Notification::make()
                                            ->success()
                                            ->title(__('filament.theme_css_reset_done'))
                                            ->send();
                                    }),
                            ]),
                    ]),

                View::make('filament.pages.theme.preview')
                    ->viewData([
                        'url'   => route('frontend.dashboard.index'),
                        'param' => SetActiveTheme::PREVIEW_PARAM,
                    ])
                    ->columnSpan(['lg' => 2]),
            ])
            ->statePath('data');
    }

    /**
     * The submit row belongs in the Customize card, which sits inside the
     * left-column Grid where {@see ReversePrimaryButtons::lastFormSection()}
     * (top-level only) would not see it.
     */
    protected function lastFormSection(): ?Section
    {
        $section = $this->getSchema('form')?->getComponent(
            fn (Component $component): bool => $component instanceof Section && $component->getId() === 'customize',
        );

        return $section instanceof Section ? $section : null;
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

            app(ThemeFields::class)->set($theme, 'css', $data['css'] ?? null);

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
