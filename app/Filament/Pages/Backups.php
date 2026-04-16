<?php

namespace App\Filament\Pages;

use App\Models\Enums\NavigationGroup;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Support\Icons\Heroicon;
use ShuvroRoy\FilamentSpatieLaravelBackup\Pages\Backups as BaseBackups;

class Backups extends BaseBackups
{
    use HasPageShield;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static ?int $navigationSort = 4;

    public static function getNavigationGroup(): \UnitEnum
    {
        return NavigationGroup::Developers;
    }
}
