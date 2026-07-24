<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\Ability;
use App\Enums\NavigationGroup;
use App\Filament\Concerns\AuthorizesAccess;
use App\Models\Addon;
use App\Models\AddonSetting;
use App\Services\AddonSettingService;
use BackedEnum;
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
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
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
 * @property-read Schema $form
 */
class AddonSettings extends Page
{
    use AuthorizesAccess;
    use InteractsWithFormActions;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Config;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog8Tooth;

    protected static ?int $navigationSort = 99;

    public ?array $data = [];

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
            ->where('addon_id', $addon->id)
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

        $addon = self::currentAddon();
        abort_unless($addon instanceof Addon, 403);

        try {
            $data = $this->form->getState();

            DB::transaction(function () use ($addon, $data): void {
                $service = app(AddonSettingService::class);

                foreach ($data as $key => $value) {
                    $service->storeById($addon->id, $key, $value);
                }
            });

            Notification::make()
                ->success()
                ->title('Settings saved successfully')
                ->send();
        } catch (Halt) {
            return;
        }
    }

    public function getFormActionsComponents(): Actions
    {
        return Actions::make([
            $this->getSaveFormAction(),
        ])->alignment(Alignment::End);
    }

    protected function getSaveFormAction(): Action
    {
        return Action::make('save')
            ->label(__('filament-panels::resources/pages/edit-record.form.actions.save.label'))
            ->submit('save')
            ->visible(static::canEdit())
            ->keyBindings(['mod+s']);
    }

    protected function getFormSchema(): array
    {
        $tabs = [];

        foreach ($this->settings()->groupBy('group') as $group => $settings) {
            $tabs[] = Tab::make(Str::ucfirst((string) $group))
                ->schema(
                    $settings->map(fn (AddonSetting $setting): DatePicker|Toggle|TextInput|Select => $this->fieldFor($setting))->toArray()
                );
        }

        return [
            Tabs::make('addon_settings')
                ->tabs($tabs)
                ->columnSpanFull()
                ->disabled(!static::canEdit()),
        ];
    }

    /**
     * Build the form field for a single setting, by type.
     */
    protected function fieldFor(AddonSetting $setting): DatePicker|Toggle|TextInput|Select
    {
        if ($setting->type === 'date') {
            return DatePicker::make($setting->key)
                ->label($setting->name)
                ->helperText($setting->description)
                ->format('Y-m-d')
                ->native(false);
        }

        if ($setting->type === 'boolean' || $setting->type === 'bool') {
            return Toggle::make($setting->key)
                ->label($setting->name)
                ->helperText($setting->description)
                ->offIcon(Heroicon::XCircle)
                ->offColor('danger')
                ->onIcon(Heroicon::CheckCircle)
                ->onColor('success');
        }

        if ($setting->type === 'int' || $setting->type === 'integer') {
            return TextInput::make($setting->key)
                ->label($setting->name)
                ->helperText($setting->description)
                ->integer();
        }

        if ($setting->type === 'number' || $setting->type === 'float') {
            return TextInput::make($setting->key)
                ->label($setting->name)
                ->helperText($setting->description)
                ->numeric()
                ->step(0.01);
        }

        if ($setting->type === 'select') {
            return Select::make($setting->key)
                ->label($setting->name)
                ->helperText($setting->description)
                ->options(list_to_assoc(explode(',', (string) $setting->options)));
        }

        return TextInput::make($setting->key)
            ->label($setting->name)
            ->helperText($setting->description)
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
            ->where('addon_id', $addon->id)
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
