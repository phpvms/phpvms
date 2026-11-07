<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\Ability;
use App\Enums\NavigationGroup;
use App\Filament\Concerns\AuthorizesAccess;
use App\Filament\Concerns\FormActionIcons;
use App\Models\Addon;
use App\Models\AddonSetting;
use App\Services\AddonSettingService;
use App\Support\SettingsTabs;
use BackedEnum;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Actions\Action;
use Filament\Facades\Filament;
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
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Js;
use Illuminate\Support\Str;
use Override;
use UnitEnum;

/**
 * Shared, core-hosted settings page inherited by every addon's Filament panel.
 *
 * A single page class serves all addons: it resolves the owning addon from the
 * current panel id (which equals the addon's moduleKey/alias) and shows only
 * that addon's settings. It is registered into each module panel by
 * App\Contracts\Modules\PanelProvider; on the core admin panel the panel id
 * resolves to no addon, so the page is hidden and inaccessible there.
 *
 * Laid out like the core Settings page: a sticky, searchable category nav
 * beside one section per group, each with its own Save, plus a Save for the
 * whole page (the nav view and `.settings-page` styles are shared).
 *
 * @property-read Schema $form
 */
class AddonSettings extends Page
{
    use AuthorizesAccess;
    use FormActionIcons;
    use InteractsWithFormActions;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Config;

    protected static string|BackedEnum|null $navigationIcon = Phosphor::GearLight;

    protected static ?int $navigationSort = 99;

    public ?array $data = [];

    public string $previousUrl;

    /**
     * Addon settings expose a single `edit` ability gating the save action.
     * Page access itself is gated by the addon's panel (`access:{module-key}`,
     * resolved in User::canAccessPanel()) — reaching the page already means the
     * user can access the module — so no separate `view` ability is declared.
     *
     * @return array<int, Ability>
     */
    public static function getPermissionAbilities(): array
    {
        return [Ability::Edit];
    }

    /**
     * The page is only available inside a panel whose id resolves to an addon
     * that has at least one registered setting. Module access is enforced one
     * level up by User::canAccessPanel(); editing additionally requires the
     * `edit:addon-settings` permission (see canEdit()).
     */
    #[Override]
    public static function canAccess(): bool
    {
        $addon = self::currentAddon();

        if (!$addon instanceof Addon) {
            return false;
        }

        return AddonSetting::query()
            ->where('registry_id', $addon->registry_id)
            ->where('type', '!=', 'hidden')
            ->exists();
    }

    #[Override]
    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

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
        $formattedData = [];

        foreach ($this->settings() as $setting) {
            $formattedData[$setting->key] = $setting->value;
        }

        $this->form->fill($formattedData);
    }

    public function save(): void
    {
        abort_unless(static::canEdit(), 403);

        try {
            $this->persist($this->form->getState());

            Notification::make()
                ->success()
                ->title('Settings saved successfully')
                ->send();
        } catch (Halt) {
            return;
        }
    }

    /**
     * Saves one group only. The whole form is still validated (as on the core
     * Settings page), but only the settings of this group are written,
     * whatever else was edited.
     */
    public function saveGroup(string $group): void
    {
        abort_unless(static::canEdit(), 403);

        try {
            $keys = $this->settings()
                ->filter(fn (AddonSetting $setting): bool => (string) $setting->group === $group)
                ->pluck('key')
                ->all();

            $this->persist(Arr::only($this->form->getState(), $keys));

            Notification::make()
                ->success()
                ->title($this->groupLabel($group).' settings saved')
                ->send();
        } catch (Halt) {
            return;
        }
    }

    /**
     * Writes the given settings of the current addon in one transaction.
     *
     * @param array<string, mixed> $data
     */
    protected function persist(array $data): void
    {
        $addon = self::currentAddon();
        abort_unless($addon instanceof Addon, 403);

        DB::transaction(function () use ($addon, $data): void {
            $service = app(AddonSettingService::class);

            foreach ($data as $key => $value) {
                $service->storeFor($addon->registry_id, $key, $value);
            }
        });
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
            ->visible(static::canEdit())
            ->keyBindings(['mod+s']);
    }

    protected function getCancelFormAction(): Action
    {
        $url = $this->previousUrl ?? Filament::getUrl();

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

        // General leads, the rest keep SettingsTabs' alphabetical order.
        $groups = SettingsTabs::group($this->settings())->toBase();
        $general = $groups->filter(fn ($settings, string $group): bool => strcasecmp($group, 'general') === 0);
        $groups = $general->merge($groups->diffKeys($general));

        foreach ($groups as $group => $settings) {
            $group = (string) $group;
            $id = 'settings-'.(Str::slug($group) ?: 'general');
            $navLinks[] = ['id' => $id, 'label' => $this->groupLabel($group)];

            // Not collapsible, as on the core Settings page: the nav is how you
            // move around, so a stray click on a title would only hide it.
            $sections[] = Section::make($this->groupLabel($group))
                ->id($id)
                ->key($id)
                ->schema(
                    $settings->map(fn (AddonSetting $setting): DatePicker|Toggle|TextInput|Select => $this->fieldFor($setting))->all()
                )
                // Flex, not ->alignment() on Actions: .fi-ac.fi-align-end is
                // row-reverse. The Flex does the pushing right.
                ->footer([
                    Flex::make([
                        Actions::make([
                            Action::make('saveSection-'.$id)
                                ->label(__('filament-panels::resources/pages/edit-record.form.actions.save.label'))
                                ->icon(Phosphor::FloppyDiskLight)
                                ->visible(static::canEdit())
                                ->action(fn () => $this->saveGroup($group)),
                        ])->grow(false),
                    ])->grow()->alignment(Alignment::End),
                ]);
        }

        // The nav column and the group sections sit side by side from lg up
        // (see .settings-page in the admin theme).
        return [
            Grid::make(1)
                ->extraAttributes(['class' => 'settings-page'])
                ->schema([
                    View::make('filament.pages.settings.nav')
                        ->viewData(['links' => $navLinks]),
                    Section::make()
                        ->schema([View::make('filament.pages.settings.empty'), ...$sections]),
                ])
                ->columnSpanFull()
                ->disabled(!static::canEdit()),
        ];
    }

    private function groupLabel(string $group): string
    {
        return $group === '' ? trans_choice('common.setting', 2) : Str::ucfirst($group);
    }

    /**
     * Build the form field for a single setting, by type.
     */
    protected function fieldFor(AddonSetting $setting): DatePicker|Toggle|TextInput|Select
    {
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

        if ($setting->type === 'int' || $setting->type === 'integer') {
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
    }

    /**
     * The current addon's settings (excluding hidden), ordered for display.
     *
     * @return Collection<int, AddonSetting>
     */
    protected function settings(): Collection
    {
        $addon = self::currentAddon();

        if (!$addon instanceof Addon) {
            return AddonSetting::query()->whereRaw('1 = 0')->get();
        }

        return AddonSetting::query()
            ->where('registry_id', $addon->registry_id)
            ->where('type', '!=', 'hidden')
            ->orderBy('order')
            ->get();
    }

    /**
     * Resolve the addon that owns the current panel, or null on the core panel.
     */
    protected static function currentAddon(): ?Addon
    {
        $panelId = Filament::getCurrentPanel()?->getId();

        return app(AddonSettingService::class)->resolveAddon($panelId);
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
