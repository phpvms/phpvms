<?php

namespace App\Filament\Pages;

use App\Enums\Ability;
use App\Enums\NavigationGroup;
use App\Filament\Concerns\AuthorizesAccess;
use App\Filament\Concerns\FormActionIcons;
use App\Jobs\ChangeJournalCurrencies;
use App\Models\Setting;
use App\Services\SettingService;
use App\Support\SettingsTabs;
use BackedEnum;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Facades\FilamentView;
use Igaster\LaravelTheme\Facades\Theme;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Js;
use Illuminate\Support\Str;
use Override;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class Settings extends Page
{
    use AuthorizesAccess;
    use FormActionIcons;
    use InteractsWithFormActions;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Config;

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Phosphor::GearLight;

    public ?array $data = [];

    public string $previousUrl;

    /**
     * Settings exposes an `edit` ability gating the save action, on top of the
     * default `view` ability that gates page access.
     *
     * @return array<int, Ability>
     */
    public static function getPermissionAbilities(): array
    {
        return [Ability::View, Ability::Edit];
    }

    public function mount(): void
    {
        $this->fillForm();
        $this->previousUrl = url()->previous();
    }

    #[Override]
    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    $this->getFormActionsComponents(),
                ]),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components($this->getFormSchema())
            ->statePath('data');
    }

    protected function fillForm(): void
    {
        $settings = Setting::where('type', '!=', 'hidden')->orderBy('order')->get();

        $data = $settings->toArray();
        $formattedData = [];

        foreach ($data as $setting) {
            $formattedData[$setting['key']] = $setting['value'];
        }

        $formattedData = Arr::undot($formattedData);

        $this->form->fill($formattedData);
    }

    public function save(): void
    {
        abort_unless(static::canEdit(), 403);

        try {
            $this->notifySaved('Settings saved successfully', $this->persist(Arr::dot($this->form->getState())));
        } catch (Halt) {
            return;
        }
    }

    /**
     * Saves one category only. The whole form is still validated (Filament's
     * getStateOnly() filters after validating everything), but only the
     * settings whose `group` is this one are written, whatever else was edited.
     */
    public function saveGroup(string $group): void
    {
        abort_unless(static::canEdit(), 403);

        try {
            $keys = Setting::where('type', '!=', 'hidden')
                ->where('group', $group)
                ->pluck('key')
                ->all();

            $data = Arr::only(Arr::dot($this->form->getState()), $keys);

            $this->notifySaved(Str::ucfirst($group).' settings saved', $this->persist($data));
        } catch (Halt) {
            return;
        }
    }

    /**
     * Writes dotted setting keys in one transaction, and queues the journal
     * migration when the currency changed.
     *
     * @param  array<string, mixed> $data
     * @return bool                 Whether the currency changed
     */
    protected function persist(array $data): bool
    {
        // Wrap the settings writes in a single transaction so a partial
        // failure doesn't leave the DB with half-saved settings.
        $currencyChanged = DB::transaction(function () use ($data): bool {
            // Read from the table, not setting(): the application cache can
            // hold a currency that never committed (a save that rolled back),
            // which would make the retry look unchanged and skip the migration.
            $storedCurrency = static fn (): ?string => Setting::query()->byKey('units.currency')->value('value');
            $previousCurrency = $storedCurrency();

            $settingService = app(SettingService::class);
            foreach ($data as $key => $value) {
                $settingService->store($key, $value);
            }

            $currency = $storedCurrency();

            return $currency !== null && $currency !== $previousCurrency;
        });

        // Only when the currency changed: the journals are already on it
        // otherwise. Queued, because the migration touches every journal
        // transaction row, and dispatched after the commit so the job reads
        // the new currency.
        if ($currencyChanged) {
            ChangeJournalCurrencies::dispatch(auth()->id());
        }

        return $currencyChanged;
    }

    private function notifySaved(string $title, bool $currencyChanged): void
    {
        Notification::make()
            ->success()
            ->title($title)
            ->body($currencyChanged ? 'The journals are being moved to the new currency. You will be notified when it is done.' : null)
            ->send();
    }

    public function getFormActionsComponents(): Actions
    {
        return Actions::make($this->reversePrimaryButtons([
            $this->getSaveFormAction(),
            $this->getCancelFormAction(),
        ]));
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

    protected function getFormSchema(): array
    {
        $sections = [];
        $navLinks = [];

        $grouped_settings = Setting::where('type', '!=', 'hidden')
            ->orderBy('order')
            ->get();

        // General leads, the rest keep SettingsTabs' alphabetical order.
        $groups = SettingsTabs::group($grouped_settings)->toBase();
        $general = $groups->filter(fn ($settings, string $group): bool => strcasecmp($group, 'general') === 0);
        $groups = $general->merge($groups->diffKeys($general));

        foreach ($groups as $group => $settings) {
            $id = 'settings-'.Str::slug($group);
            $navLinks[] = ['id' => $id, 'label' => Str::ucfirst($group)];

            // Not collapsible: the heads are plain headings and the nav is how
            // you move around, so a stray click on a title would only hide it.
            $sections[] = Section::make(Str::ucfirst($group))
                ->id($id)
                ->key($id)
                ->schema(
                    $settings->map(function (Setting $setting): DatePicker|Toggle|TextInput|Select {
                        if ($setting->type === 'date') {
                            return DatePicker::make($setting->key)
                                ->label($setting->name)
                                ->inlineLabel()
                                ->belowLabel($setting->description)
                                ->format('Y-m-d')
                                ->native(false);
                        }

                        if ($setting->type === 'boolean' || $setting->type === 'bool') {
                            return Toggle::make($setting->key)
                                ->label($setting->name)
                                ->inlineLabel()
                                ->belowLabel($setting->description)
                                ->offIcon(Phosphor::XLight)
                                ->offColor('danger')
                                ->onIcon(Phosphor::CheckLight)
                                ->onColor('success');
                        }

                        if ($setting->type === 'int') {
                            return TextInput::make($setting->key)
                                ->label($setting->name)
                                ->inlineLabel()
                                ->belowLabel($setting->description)
                                ->integer();
                        }

                        if ($setting->type === 'number' || $setting->type === 'float') {
                            return TextInput::make($setting->key)
                                ->label($setting->name)
                                ->inlineLabel()
                                ->belowLabel($setting->description)
                                ->numeric()
                                ->step(0.01);
                        }

                        if ($setting->type === 'select') {
                            if ($setting->id === 'general_theme') {
                                return Select::make($setting->key)
                                    ->label($setting->name)
                                    ->inlineLabel()
                                    ->belowLabel($setting->description)
                                    ->options(list_to_assoc($this->getThemes()));
                            }

                            if ($setting->id === 'units_currency') {
                                return Select::make($setting->key)
                                    ->label($setting->name)
                                    ->inlineLabel()
                                    ->belowLabel($setting->description)
                                    ->options($this->getCurrencyList())
                                    ->searchable()
                                    ->native(false);
                            }

                            return Select::make($setting->key)
                                ->label($setting->name)
                                ->inlineLabel()
                                ->belowLabel($setting->description)
                                ->options(list_to_assoc(explode(',', (string) $setting->options)));
                        }

                        return TextInput::make($setting->key)
                            ->label($setting->name)
                            ->inlineLabel()
                            ->belowLabel($setting->description)
                            ->string();
                    })->toArray()
                )
                // Flex, not ->alignment() on Actions: .fi-ac.fi-align-end is
                // row-reverse. The Flex does the pushing right.
                ->footer([
                    Flex::make([
                        Actions::make([
                            Action::make('saveSection-'.$id)
                                ->label(__('filament-panels::resources/pages/edit-record.form.actions.save.label'))
                                ->icon(Phosphor::FloppyDiskLight)
                                ->action(fn () => $this->saveGroup($group)),
                        ])->grow(false),
                    ])->grow()->alignment(Alignment::End),
                ]);
        }

        // The nav column and the category sections sit side by side from lg up
        // (see .settings-page in the admin theme); one outer Section holds
        // the categories so the page stays a single sheet region.
        return [
            Grid::make(1)
                ->extraAttributes(['class' => 'settings-page'])
                ->schema([
                    View::make('filament.pages.settings.nav')
                        ->viewData(['links' => $navLinks]),
                    Section::make()
                        ->schema([View::make('filament.pages.settings.empty'), ...$sections]),
                ])
                ->columnSpanFull(),
        ];
    }

    private function getThemes(): array
    {
        Theme::rebuildCache();
        $themes = Theme::all();
        $theme_list = [];
        foreach ($themes as $t) {
            if (!$t) {
                continue;
            }

            if (!$t->name) {
                continue;
            }

            if ($t->name === 'false') {
                continue;
            }

            $theme_list[] = $t->name;
        }

        return $theme_list;
    }

    private function getCurrencyList(): array
    {
        $curr = [];
        foreach (config('money.currencies') as $currency => $attrs) {
            $name = $attrs['name'].' ('.$attrs['symbol'].'/'.$currency.')';
            $curr[$currency] = $name;
        }

        return $curr;
    }

    #[Override]
    public static function getNavigationLabel(): string
    {
        return trans_choice('common.setting', 2);
    }

    #[Override]
    public function getTitle(): string
    {
        return trans_choice('common.setting', 2);
    }
}
