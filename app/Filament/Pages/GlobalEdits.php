<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\Ability;
use App\Enums\NavigationGroup;
use App\Filament\Concerns\AuthorizesAccess;
use App\Livewire\Filament\GlobalSlotList;
use BackedEnum;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Pages\Page;
use Override;
use UnitEnum;

/**
 * Customize → Global Edits: the site-wide CSS, head and footer inserts that apply
 * to every theme. The list itself is {@see GlobalSlotList};
 * this page only gates it on `view:global-slot` and `edit:global-slot`.
 */
class GlobalEdits extends Page
{
    use AuthorizesAccess;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Customize;

    protected static ?int $navigationSort = 4;

    protected static string|BackedEnum|null $navigationIcon = Phosphor::CodeLight;

    protected string $view = 'filament.pages.global-edits';

    public static function getPermissionKey(): string
    {
        return 'global-slot';
    }

    /**
     * @return array<int, Ability>
     */
    public static function getPermissionAbilities(): array
    {
        return [Ability::View, Ability::Edit];
    }

    #[Override]
    public static function getNavigationLabel(): string
    {
        return __('filament.global_edits');
    }

    #[Override]
    public function getTitle(): string
    {
        return __('filament.global_edits');
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return ['canEdit' => static::canEdit()];
    }
}
