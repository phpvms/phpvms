<?php

namespace App\Filament\Resources\SimBriefAirframes;

use App\Filament\Resources\SimBriefAirframes\Forms\SimBriefAirframeForm;
use App\Filament\Resources\SimBriefAirframes\Pages\ManageSimBriefAirframes;
use App\Filament\Resources\SimBriefAirframes\Tables\SimBriefAirframesTable;
use App\Models\Enums\NavigationGroup;
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

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedPaperAirplane;

    public static function form(Schema $schema): Schema
    {
        return SimBriefAirframeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SimBriefAirframesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSimBriefAirframes::route('/'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['icao', 'airframe_id'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string|Htmlable
    {
        return $record->name;
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'ICAO' => $record->icao,
        ];
    }

    public static function getModelLabel(): string
    {
        return __('common.simbrief_airframe');
    }
}
