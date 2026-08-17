<?php

namespace App\Filament\Resources\Pireps;

use App\Enums\NavigationGroup;
use App\Enums\PirepState;
use App\Filament\Resources\Pireps\Pages\EditPirep;
use App\Filament\Resources\Pireps\Pages\ListPireps;
use App\Filament\Resources\Pireps\Pages\ViewPirep;
use App\Filament\Resources\Pireps\Schemas\PirepForm;
use App\Filament\Resources\Pireps\Tables\PirepsTable;
use App\Models\Pirep;
use BackedEnum;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Override;
use UnitEnum;

class PirepResource extends Resource
{
    protected static ?string $model = Pirep::class;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Operations;

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Phosphor::ClipboardTextLight;

    /**
     * Reports waiting on a decision. Soft-deleted rows are excluded by the
     * model's own scope, which is what the list shows too: getEloquentQuery()
     * drops SoftDeletingScope, but the table's TrashedFilter re-applies
     * `withoutTrashed()` while it sits blank. So the number matches the rows
     * an admin lands on.
     */
    public static function getNavigationBadge(): ?string
    {
        $pending = Pirep::where('state', PirepState::PENDING)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return PirepForm::configure($schema);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return PirepsTable::configure($table);
    }

    #[Override]
    public static function getRelations(): array
    {
        // Relation managers are embedded inline by the custom ViewPirep blade
        // (@livewire(CommentsRelationManager::class, ...)) and no longer
        // surface as panel tabs.
        return [];
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListPireps::route('/'),
            'view'  => ViewPirep::route('/{record}'),
            'edit'  => EditPirep::route('/{record}/edit'),
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
        return ['flight_number', 'route_code'];
    }

    /**
     * @param Pirep $record
     */
    #[Override]
    public static function getGlobalSearchResultTitle(Model $record): string|Htmlable
    {
        return $record->airline->icao.$record->flight_number;
    }

    /**
     * @param Pirep $record
     */
    #[Override]
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            __('airports.departure') => $record->dpt_airport_id,
            __('airports.arrival')   => $record->arr_airport_id,
        ];
    }

    #[Override]
    public static function getModelLabel(): string
    {
        return trans_choice('common.pirep', 1);
    }
}
