<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AirframeResource\Pages;
use App\Models\Enums\AirframeSource;
use App\Models\SimBriefAirframe;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

class AirframeResource extends Resource
{
    protected static ?string $model = SimBriefAirframe::class;

    protected static ?string $navigationGroup = 'Config';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'SimBrief Airframe';

    protected static ?string $navigationIcon = 'heroicon-o-paper-airplane';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('icao')
                    ->label('ICAO')
                    ->required()
                    ->string(),

                Forms\Components\TextInput::make('name')
                    ->required()
                    ->string(),

                Forms\Components\TextInput::make('airframe_id')
                    ->label('SimBrief Aiframe ID')
                    ->string(),

                Forms\Components\Hidden::make('source')
                    ->visibleOn('create')
                    ->formatStateUsing(fn () => AirframeSource::INTERNAL),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('icao')
                    ->label('ICAO')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('airframe_id')
                    ->label('SimBrief Aiframe ID')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->date('d/m/Y H:i'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->date('d/m/Y H:i'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageAirframes::route('/'),
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
