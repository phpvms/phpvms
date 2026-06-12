<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\NavigationGroup;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Support\Icons\Heroicon;
use Override;
use ShuvroRoy\FilamentSpatieLaravelBackup\Pages\Backups as BaseBackups;
use UnitEnum;

class Backups extends BaseBackups
{
    use HasPageShield;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static ?int $navigationSort = 4;

    #[Override]
    public static function getNavigationGroup(): UnitEnum
    {
        return NavigationGroup::Developers;
    }
}
