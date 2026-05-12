<?php

namespace App\Filament\Resources\Ranks;

use App\Enums\NavigationGroup;
use App\Filament\Resources\Ranks\Pages\CreateRank;
use App\Filament\Resources\Ranks\Pages\EditRank;
use App\Filament\Resources\Ranks\Pages\ListRanks;
use App\Filament\Resources\Ranks\RelationManagers\SubfleetsRelationManager;
use App\Filament\Resources\Ranks\Schemas\RankForm;
use App\Filament\Resources\Ranks\Tables\RanksTable;
use App\Models\Rank;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RankResource extends Resource
{
    protected static ?string $model = Rank::class;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Config;

    protected static ?int $navigationSort = 4;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowTrendingUp;

    protected static ?string $recordTitleAttribute = 'name';

    #[\Override]
    public static function form(Schema $schema): Schema
    {
        return RankForm::configure($schema);
    }

    #[\Override]
    public static function table(Table $table): Table
    {
        return RanksTable::configure($table);
    }

    #[\Override]
    public static function getRelations(): array
    {
        return [
            SubfleetsRelationManager::class,
        ];
    }

    #[\Override]
    public static function getPages(): array
    {
        return [
            'index'  => ListRanks::route('/'),
            'create' => CreateRank::route('/create'),
            'edit'   => EditRank::route('/{record}/edit'),
        ];
    }

    #[\Override]
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    #[\Override]
    public static function getModelLabel(): string
    {
        return __('common.rank');
    }
}
