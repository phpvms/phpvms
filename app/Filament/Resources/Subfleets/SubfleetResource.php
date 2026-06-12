<?php

namespace App\Filament\Resources\Subfleets;

use App\Enums\NavigationGroup;
use App\Filament\RelationManagers\ExpensesRelationManager;
use App\Filament\RelationManagers\FaresRelationManager;
use App\Filament\RelationManagers\FilesRelationManager;
use App\Filament\Resources\Subfleets\Pages\CreateSubfleet;
use App\Filament\Resources\Subfleets\Pages\EditSubfleet;
use App\Filament\Resources\Subfleets\Pages\ListSubfleets;
use App\Filament\Resources\Subfleets\RelationManagers\AircraftRelationManager;
use App\Filament\Resources\Subfleets\RelationManagers\RanksRelationManager;
use App\Filament\Resources\Subfleets\RelationManagers\TyperatingsRelationManager;
use App\Filament\Resources\Subfleets\Schemas\SubfleetForm;
use App\Filament\Resources\Subfleets\Tables\SubfleetsTable;
use App\Models\Subfleet;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Override;
use UnitEnum;

class SubfleetResource extends Resource
{
    protected static ?string $model = Subfleet::class;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Operations;

    protected static ?int $navigationSort = 3;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaperAirplane;

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return SubfleetForm::configure($schema);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return SubfleetsTable::configure($table);
    }

    #[Override]
    public static function getRelations(): array
    {
        return [
            AircraftRelationManager::class,
            RanksRelationManager::class,
            TyperatingsRelationManager::class,
            FaresRelationManager::class,
            ExpensesRelationManager::class,
            FilesRelationManager::class,
        ];
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index'  => ListSubfleets::route('/'),
            'create' => CreateSubfleet::route('/create'),
            'edit'   => EditSubfleet::route('/{record}/edit'),
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
    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'type'];
    }

    /**
     * @param Subfleet $record
     */
    #[Override]
    public static function getGlobalSearchResultTitle(Model $record): string|Htmlable
    {
        return $record->type.' - '.$record->name;
    }

    #[Override]
    public static function getModelLabel(): string
    {
        return __('common.subfleet');
    }

    #[Override]
    public static function getNavigationLabel(): string
    {
        return __('common.fleet');
    }
}
