<?php

namespace App\Filament\Resources\Awards;

use App\Enums\NavigationGroup;
use App\Filament\Resources\Awards\Pages\CreateAward;
use App\Filament\Resources\Awards\Pages\EditAward;
use App\Filament\Resources\Awards\Pages\ListAwards;
use App\Filament\Resources\Awards\RelationManagers\UsersRelationManager;
use App\Filament\Resources\Awards\Schemas\AwardForm;
use App\Filament\Resources\Awards\Tables\AwardsTable;
use App\Models\Award;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Override;
use UnitEnum;

class AwardResource extends Resource
{
    protected static ?string $model = Award::class;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Config;

    protected static ?int $navigationSort = 6;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static ?string $recordTitleAttribute = 'name';

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return AwardForm::configure($schema);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return AwardsTable::configure($table);
    }

    #[Override]
    public static function getRelations(): array
    {
        return [
            UsersRelationManager::make(),
        ];
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index'  => ListAwards::route('/'),
            'create' => CreateAward::route('/create'),
            'edit'   => EditAward::route('/{record}/edit'),
        ];
    }

    #[Override]
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    #[Override]
    public static function getModelLabel(): string
    {
        return __('common.award');
    }
}
