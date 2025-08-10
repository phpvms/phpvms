<?php

namespace App\Filament\Resources\Pireps;

use App\Filament\Resources\Pireps\Pages\EditPirep;
use App\Filament\Resources\Pireps\Pages\ListPireps;
use App\Filament\Resources\Pireps\RelationManagers\CommentsRelationManager;
use App\Filament\Resources\Pireps\RelationManagers\FaresRelationManager;
use App\Filament\Resources\Pireps\RelationManagers\FieldValuesRelationManager;
use App\Filament\Resources\Pireps\RelationManagers\TransactionsRelationManager;
use App\Filament\Resources\Pireps\Schemas\PirepForm;
use App\Filament\Resources\Pireps\Tables\PirepsTable;
use App\Filament\Resources\Pireps\Widgets\PirepStats;
use App\Models\Enums\NavigationGroup;
use App\Models\Enums\PirepState;
use App\Models\Pirep;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PirepResource extends Resource
{
    protected static ?string $model = Pirep::class;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Operations;

    protected static ?int $navigationSort = 1;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCloudArrowUp;

    public static function getNavigationBadge(): ?string
    {
        return Pirep::where('state', PirepState::PENDING)->count() > 0
            ? Pirep::where('state', PirepState::PENDING)->count()
            : null;
    }

    public static function form(Schema $schema): Schema
    {
        return PirepForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PirepsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            FaresRelationManager::class,
            FieldValuesRelationManager::class,
            CommentsRelationManager::class,
            TransactionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPireps::route('/'),
            'edit'  => EditPirep::route('/{record}/edit'),
        ];
    }

    public static function getWidgets(): array
    {
        return [
            PirepStats::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['flight_number', 'route_code'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string|Htmlable
    {
        return $record->airline->icao.$record->flight_number;
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            __('airports.departure') => $record->dpt_airport_id,
            __('airports.arrival')   => $record->arr_airport_id,
        ];
    }

    public static function getModelLabel(): string
    {
        return trans_choice('common.pirep', 1);
    }
}
