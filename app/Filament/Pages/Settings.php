<?php

namespace App\Filament\Pages;

use App\Repositories\SettingRepository;
use App\Services\FinanceService;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\Page;
use Filament\Support\Exceptions\Halt;
use Igaster\LaravelTheme\Facades\Theme;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class Settings extends Page
{
    use InteractsWithFormActions;
    use HasPageShield;

    protected static ?string $navigationGroup = 'Config';
    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Settings';

    protected static ?string $navigationIcon = 'heroicon-o-cog-8-tooth';

    protected static string $view = 'filament.pages.settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->fillForm();
    }

    protected function fillForm(): void
    {
        $this->callHook('beforeFill');

        $settings = app(SettingRepository::class)->where('type', '!=', 'hidden')->orderBy('order')->get();

        $data = $this->mutateFormDataBeforeFill($settings->toArray());

        $this->form->fill($data);

        $this->callHook('afterFill');
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $newData = [];

        foreach ($data as $setting) {
            $newData[$setting['key']] = $setting['value'];
        }

        return Arr::undot($newData);
    }

    public function save(): void
    {
        try {
            $this->callHook('beforeValidate');

            $data = $this->form->getState();

            $this->callHook('afterValidate');

            $data = $this->mutateFormDataBeforeSave($data);

            $this->callHook('beforeSave');

            foreach ($data as $key => $value) {
                app(SettingRepository::class)->store($key, $value);

                $cache = config('cache.keys.SETTINGS');
                Cache::forget($cache['key'].$key);
            }

            app(FinanceService::class)->changeJournalCurrencies();

            $this->callHook('afterSave');

            $this->getSavedNotification()?->send();

            if ($redirectUrl = $this->getRedirectUrl()) {
                $this->redirect($redirectUrl);
            }
        } catch (Halt $exception) {
            return;
        }
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return Arr::dot($data);
    }

    public function getSavedNotification(): ?Notification
    {
        return Notification::make()->success()->title('Settings saved successfully');
    }

    public function getFormActions()
    {
        return [
            Action::make('save')->label('Save')->submit('save')->keyBindings(['mod+s']),
        ];
    }

    public function form(Form $form): Form
    {
        return $form;
    }

    protected function getForms(): array
    {
        return [
            'form' => $this->form(
                $this->makeForm()
                    ->schema($this->getFormSchema())
                    ->statePath('data')
                    ->columns(2)
                    ->inlineLabel($this->hasInlineLabels()),
            ),
        ];
    }

    protected function getFormSchema(): array
    {
        $tabs = [];

        $grouped_settings = app(SettingRepository::class)->where('type', '!=', 'hidden')->orderBy('order')->get();
        foreach ($grouped_settings->groupBy('group') as $group => $settings) {
            $tabs[] = Tabs\Tab::make(Str::ucfirst($group))->schema(
                $settings->map(function ($setting) {
                    if ($setting->type === 'date') {
                        return DatePicker::make($setting->key)->label($setting->name)->helperText($setting->description)->format('Y-m-d');
                    } elseif ($setting->type === 'boolean' || $setting->type === 'bool') {
                        return Toggle::make($setting->key)->label($setting->name)->helperText($setting->description)->offIcon('heroicon-m-x-circle')->offColor('danger')->onIcon('heroicon-m-check-circle')->onColor('success');
                    } elseif ($setting->type === 'int') {
                        return TextInput::make($setting->key)->label($setting->name)->helperText($setting->description)->integer();
                    } elseif ($setting->type === 'number') {
                        return TextInput::make($setting->key)->label($setting->name)->helperText($setting->description)->numeric()->step(0.01);
                    } elseif ($setting->type === 'select') {
                        if ($setting->id === 'general_theme') {
                            return Select::make($setting->key)->label($setting->name)->helperText($setting->description)->options(list_to_assoc($this->getThemes()));
                        } elseif ($setting->id === 'units_currency') {
                            return Select::make($setting->key)->label($setting->name)->helperText($setting->description)->options(list_to_assoc($this->getCurrencyList()));
                        }
                        return Select::make($setting->key)->label($setting->name)->helperText($setting->description)->options(list_to_assoc(explode(',', $setting->options)));
                    }
                    return TextInput::make($setting->key)->label($setting->name)->helperText($setting->description)->string();
                })->toArray()
            );
        }

        return [
            Tabs::make('settings')->tabs($tabs)->columnSpanFull(),
        ];
    }

    public function getRedirectUrl(): ?string
    {
        return null;
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
        foreach (config('money') as $currency => $attrs) {
            $name = $attrs['name'].' ('.$attrs['symbol'].'/'.$currency.')';
            $curr[$currency] = $name;
        }

        return $curr;
    }
}
