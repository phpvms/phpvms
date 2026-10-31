<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\Ability;
use App\Enums\NavigationGroup;
use App\Filament\Concerns\AuthorizesAccess;
use App\Filament\Concerns\ReversePrimaryButtons;
use App\Services\SettingService;
use BackedEnum;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
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
        $this->form->fill([
            'theme' => setting('general.theme', config('themes.default')),
        ]);

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
            ->components([
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
                            ->native(false),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        abort_unless(static::canEdit(), 403);

        try {
            $theme = (string) $this->form->getState()['theme'];

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
