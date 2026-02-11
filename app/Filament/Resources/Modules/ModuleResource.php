<?php

namespace App\Filament\Resources\Modules;

use App\Filament\Resources\Modules\Pages\ManageModules;
use App\Filament\Resources\Modules\Schemas\ModuleForm;
use App\Filament\Resources\Modules\Tables\ModulesTable;
use App\Models\Enums\NavigationGroup;
use App\Models\Module;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ModuleResource extends Resource
{
    protected static ?string $model = Module::class;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Developers;

    protected static ?int $navigationSort = 1;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ModuleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ModulesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageModules::route('/'),
        ];
    }

    public static function getModelLabel(): string
    {
        return __('common.module');
    }
}
