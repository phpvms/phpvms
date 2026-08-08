<?php

namespace App\Filament\Resources\Fares;

use App\Enums\NavigationGroup;
use App\Filament\Resources\Fares\Pages\CreateFare;
use App\Filament\Resources\Fares\Pages\EditFare;
use App\Filament\Resources\Fares\Pages\ListFares;
use App\Filament\Resources\Fares\Schemas\FareForm;
use App\Filament\Resources\Fares\Tables\FaresTable;
use App\Models\Fare;
use BackedEnum;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Override;
use UnitEnum;

class FareResource extends Resource
{
    protected static ?string $model = Fare::class;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Finance;

    protected static ?int $navigationSort = 3;

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::PresentationAnalytics;

    protected static ?string $recordTitleAttribute = 'name';

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return FareForm::configure($schema);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return FaresTable::configure($table);
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
    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index'  => ListFares::route('/'),
            'create' => CreateFare::route('/create'),
            'edit'   => EditFare::route('/{record}/edit'),
        ];
    }

    #[Override]
    public static function getModelLabel(): string
    {
        return trans_choice('pireps.fare', 1);
    }
}
