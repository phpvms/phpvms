<?php

namespace App\Filament\Pages;

use App\Enums\Ability;
use App\Enums\NavigationGroup;
use App\Filament\Concerns\AuthorizesAccess;
use App\Filament\Concerns\EditsSettings;
use App\Services\SettingService;
use App\Settings\UnitsSettings;
use App\Support\Settings\SettingDefinition;
use App\Support\Settings\SettingsRegistry;
use BackedEnum;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Override;
use UnitEnum;

/**
 * The core settings: every visible property of the settings classes in
 * App\Settings, one section per settings group.
 */
class Settings extends Page
{
    use AuthorizesAccess;
    use EditsSettings;
    use InteractsWithFormActions;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Config;

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Phosphor::GearLight;

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

    protected function settingDefinitions(): Collection
    {
        return app(SettingsRegistry::class)->all()
            ->reject(fn (SettingDefinition $definition): bool => $definition->hidden)
            ->values();
    }

    protected function settingValue(SettingDefinition $definition): mixed
    {
        return app($definition->class)->{$definition->name};
    }

    /**
     * Writes the settings in one transaction. Changing the currency queues
     * the move of the journals (see SettingsChangeSubscriber); the
     * notification says so.
     */
    protected function persistSettings(array $values): ?string
    {
        $previousCurrency = app(UnitsSettings::class)->currency;

        app(SettingService::class)->storeMany($values, skipNull: false);

        return app(UnitsSettings::class)->currency !== $previousCurrency
            ? 'The journals are being moved to the new currency. You will be notified when it is done.'
            : null;
    }

    protected function sectionLabel(string $section): string
    {
        return Str::ucfirst($section);
    }

    protected function cancelUrl(): string
    {
        return Dashboard::getUrl();
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
