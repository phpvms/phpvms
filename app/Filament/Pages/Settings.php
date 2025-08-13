<?php

namespace App\Filament\Pages;

use App\Models\Enums\NavigationGroup;
use App\Models\Setting;
use App\Repositories\SettingRepository;
use App\Services\FinanceService;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
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
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Facades\FilamentView;
use Filament\Support\Icons\Heroicon;
use Igaster\LaravelTheme\Facades\Theme;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Js;
use Illuminate\Support\Str;

/**
 * @property-read Schema $form
 */
class Settings extends Page
{
    use HasPageShield;
    use InteractsWithFormActions;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Developers;

    protected static ?int $navigationSort = 6;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCog8Tooth;

    public ?array $data = [];

    public function mount(): void
    {
        $this->fillForm();
        $this->previousUrl = url()->previous();
    }

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
        $settings = app(SettingRepository::class)->where('type', '!=', 'hidden')->orderBy('order')->get();

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
        abort_unless(static::canAccess(), 403);

        try {
            $data = $this->form->getState();

            $data = Arr::dot($data);

            foreach ($data as $key => $value) {
                app(SettingRepository::class)->store($key, $value);

                $cache = config('cache.keys.SETTINGS');
                Cache::forget($cache['key'].$key);
            }

            app(FinanceService::class)->changeJournalCurrencies();

            Notification::make()
                ->success()
                ->title('Settings saved successfully')
                ->send();
        } catch (Halt $exception) {
            return;
        }
    }

    public function getFormActionsComponents(): Actions
    {
        return Actions::make([
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

    protected function getFormSchema(): array
    {
        $tabs = [];

        $grouped_settings = app(SettingRepository::class)
            ->where('type', '!=', 'hidden')
            ->orderBy('order')
            ->get();

        foreach ($grouped_settings->groupBy('group') as $group => $settings) {
            $tabs[] = Tab::make(Str::ucfirst($group))
                ->schema(
                    $settings->map(function (Setting $setting) {
                        if ($setting->type === 'date') {
                            return DatePicker::make($setting->key)
                                ->label($setting->name)
                                ->helperText($setting->description)
                                ->format('Y-m-d')
                                ->native(false);
                        } elseif ($setting->type === 'boolean' || $setting->type === 'bool') {
                            return Toggle::make($setting->key)
                                ->label($setting->name)
                                ->helperText($setting->description)
                                ->offIcon(Heroicon::XCircle)
                                ->offColor('danger')
                                ->onIcon(Heroicon::CheckCircle)
                                ->onColor('success');
                        } elseif ($setting->type === 'int') {
                            return TextInput::make($setting->key)
                                ->label($setting->name)
                                ->helperText($setting->description)
                                ->integer();
                        } elseif ($setting->type === 'number') {
                            return TextInput::make($setting->key)
                                ->label($setting->name)
                                ->helperText($setting->description)
                                ->numeric()
                                ->step(0.01);
                        } elseif ($setting->type === 'select') {
                            if ($setting->id === 'general_theme') {
                                return Select::make($setting->key)
                                    ->label($setting->name)
                                    ->helperText($setting->description)
                                    ->options(list_to_assoc($this->getThemes()));
                            } elseif ($setting->id === 'units_currency') {
                                return Select::make($setting->key)
                                    ->label($setting->name)
                                    ->helperText($setting->description)
                                    ->options($this->getCurrencyList())
                                    ->searchable()
                                    ->native(false);
                            }

                            return Select::make($setting->key)
                                ->label($setting->name)
                                ->helperText($setting->description)
                                ->options(list_to_assoc(explode(',', $setting->options)));
                        }

                        return TextInput::make($setting->key)
                            ->label($setting->name)
                            ->helperText($setting->description)
                            ->string();
                    })->toArray()
                );
        }

        return [
            Tabs::make('settings')
                ->tabs($tabs)
                ->columnSpanFull(),
        ];
    }

    private function getThemes(): array
    {
        Theme::rebuildCache();
        $themes = Theme::all();
        $theme_list = [];
        foreach ($themes as $t) {
            if (!$t || !$t->name || $t->name === 'false') {
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

    public static function getNavigationLabel(): string
    {
        return trans_choice('common.setting', 2);
    }

    public function getTitle(): string
    {
        return trans_choice('common.setting', 2);
    }
}
