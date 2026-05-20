<?php

namespace App\Filament\Resources\SimBriefAirframes;

use App\Enums\NavigationGroup;
use App\Filament\Resources\SimBriefAirframes\Pages\ManageSimBriefAirframes;
use App\Filament\Resources\SimBriefAirframes\Schemas\SimBriefAirframeForm;
use App\Filament\Resources\SimBriefAirframes\Tables\SimBriefAirframesTable;
use App\Models\SimBriefAirframe;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

class SimBriefAirframeResource extends Resource
{
    protected static ?string $model = SimBriefAirframe::class;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Config;

    protected static ?int $navigationSort = 2;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    #[\Override]
    public static function form(Schema $schema): Schema
    {
        return SimBriefAirframeForm::configure($schema);
    }

    #[\Override]
    public static function table(Table $table): Table
    {
        return SimBriefAirframesTable::configure($table);
    }

    #[\Override]
    public static function getPages(): array
    {
        return [
            'index' => ManageSimBriefAirframes::route('/'),
        ];
    }

    #[\Override]
    public static function getGloballySearchableAttributes(): array
    {
        return ['icao', 'airframe_id'];
    }

    /**
     * @param SimBriefAirframe $record
     */
    #[\Override]
    public static function getGlobalSearchResultTitle(Model $record): string|Htmlable
    {
        return $record->name;
    }

    /**
     * @param SimBriefAirframe $record
     */
    #[\Override]
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'ICAO' => $record->icao,
        ];
    }

    #[\Override]
    public static function getModelLabel(): string
    {
        return __('common.simbrief_airframe');
    }
}
