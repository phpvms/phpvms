<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Contracts\Addons\AddonSettings as AddonSettingsClass;
use App\Enums\Ability;
use App\Enums\NavigationGroup;
use App\Features\Addons\Support\AddonSettingsRegistry;
use App\Filament\Concerns\AuthorizesAccess;
use App\Filament\Concerns\EditsSettings;
use App\Models\Addon;
use App\Services\AddonSettingService;
use App\Support\Settings\SettingDefinition;
use BackedEnum;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Facades\Filament;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Override;
use UnitEnum;

/**
 * Shared, core-hosted settings page inherited by every addon's Filament panel.
 *
 * A single page class serves all addons: it resolves the owning addon from the
 * current panel id (which equals the addon's moduleKey/alias) and shows only
 * the settings classes that addon registered, one section per class. It is
 * registered into each module panel by App\Contracts\Modules\PanelProvider; on
 * the core admin panel the panel id resolves to no addon, so the page is hidden
 * and inaccessible there.
 *
 * Laid out like the core Settings page (see {@see EditsSettings}).
 */
class AddonSettings extends Page
{
    use AuthorizesAccess;
    use EditsSettings {
        mount as private mountSettings;
    }
    use InteractsWithFormActions;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Config;

    protected static string|BackedEnum|null $navigationIcon = Phosphor::GearLight;

    protected static ?int $navigationSort = 99;

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
     * that has at least one visible setting. Module access is enforced one
     * level up by User::canAccessPanel(); editing additionally requires the
     * `edit:addon-settings` permission (see canEdit()).
     */
    #[Override]
    public static function canAccess(): bool
    {
        return self::visibleDefinitions()->isNotEmpty();
    }

    #[Override]
    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->mountSettings();
    }

    protected function settingDefinitions(): Collection
    {
        return self::visibleDefinitions();
    }

    protected function settingValue(SettingDefinition $definition): mixed
    {
        return app($definition->class)->{$definition->name};
    }

    protected function persistSettings(array $values): ?string
    {
        $addon = self::currentAddon();
        abort_unless($addon instanceof Addon, 403);

        app(AddonSettingService::class)->storeManyFor($addon->registry_id, $values, skipNull: false);

        return null;
    }

    protected function sectionLabel(string $section): string
    {
        $class = (string) self::visibleDefinitions()
            ->first(fn (SettingDefinition $definition): bool => $definition->section === $section)
            ?->class;

        if (!is_subclass_of($class, AddonSettingsClass::class)) {
            return $section;
        }

        /** @var class-string<AddonSettingsClass> $class */
        return $class::sectionLabel();
    }

    protected function cancelUrl(): string
    {
        return Filament::getUrl();
    }

    protected function showsSaveActions(): bool
    {
        return static::canEdit();
    }

    /**
     * The current addon's visible settings, in section then declaration order.
     *
     * @return Collection<int, SettingDefinition>
     */
    private static function visibleDefinitions(): Collection
    {
        $addon = self::currentAddon();

        if (!$addon instanceof Addon) {
            return collect();
        }

        return collect(AddonSettingsRegistry::definitionsFor($addon->registry_id))
            ->reject(fn (SettingDefinition $definition): bool => $definition->hidden)
            ->values();
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
