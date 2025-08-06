<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AirframeResource\Pages\ManageAirframes;
use App\Models\Enums\AirframeSource;
use App\Models\SimBriefAirframe;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

class AirframeResource extends Resource
{
    protected static ?string $model = SimBriefAirframe::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Config';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'SimBrief Airframe';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-paper-airplane';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('icao')
                    ->label('ICAO')
                    ->required()
                    ->string(),

                TextInput::make('name')
                    ->required()
                    ->string(),

                TextInput::make('airframe_id')
                    ->label('SimBrief Aiframe ID')
                    ->string(),

                Hidden::make('source')
                    ->visibleOn('create')
                    ->formatStateUsing(fn () => AirframeSource::INTERNAL),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('icao')
                    ->label('ICAO')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('airframe_id')
                    ->label('SimBrief Aiframe ID')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->date('d/m/Y H:i'),

                TextColumn::make('updated_at')
                    ->date('d/m/Y H:i'),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAirframes::route('/'),
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
}
