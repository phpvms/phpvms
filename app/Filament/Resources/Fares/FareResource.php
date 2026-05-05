<?php

namespace App\Filament\Resources\Fares;

use App\Filament\Resources\Fares\Pages\CreateFare;
use App\Filament\Resources\Fares\Pages\EditFare;
use App\Filament\Resources\Fares\Pages\ListFares;
use App\Filament\Resources\Fares\Schemas\FareForm;
use App\Filament\Resources\Fares\Tables\FaresTable;
use App\Models\Enums\NavigationGroup;
use App\Models\Fare;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class FareResource extends Resource
{
    protected static ?string $model = Fare::class;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Operations;

    protected static ?int $navigationSort = 4;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static ?string $recordTitleAttribute = 'name';

    #[\Override]
    public static function form(Schema $schema): Schema
    {
        return FareForm::configure($schema);
    }

    #[\Override]
    public static function table(Table $table): Table
    {
        return FaresTable::configure($table);
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
    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    #[\Override]
    public static function getPages(): array
    {
        return [
            'index'  => ListFares::route('/'),
            'create' => CreateFare::route('/create'),
            'edit'   => EditFare::route('/{record}/edit'),
        ];
    }

    #[\Override]
    public static function getModelLabel(): string
    {
        return trans_choice('pireps.fare', 1);
    }
}
