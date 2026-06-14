<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\NavigationGroup;
use App\Filament\Concerns\AuthorizesAccess;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Override;
use ShuvroRoy\FilamentSpatieLaravelBackup\Pages\Backups as BaseBackups;
use UnitEnum;

class Backups extends BaseBackups
{
    use AuthorizesAccess;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static ?int $navigationSort = 4;

    #[Override]
    public static function getNavigationGroup(): UnitEnum
    {
        return NavigationGroup::Developers;
    }

    /**
     * The backup action permissions live alongside the page's `view` permission
     * in a single "Backups" group.
     *
     * @return array<int, array{name: string, ability: null, label: string}>
     */
    public static function extraPermissions(): array
    {
        return [
            ['name' => 'create-backup', 'ability' => null, 'label' => __('filament.backup_create')],
            ['name' => 'download-backup', 'ability' => null, 'label' => __('filament.backup_download')],
            ['name' => 'delete-backup', 'ability' => null, 'label' => __('filament.backup_delete')],
        ];
    }
}
