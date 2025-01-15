<?php

namespace App\Filament\Resources;

use App\Filament\RelationManagers\ExpensesRelationManager;
use App\Filament\RelationManagers\FilesRelationManager;
use App\Filament\Resources\AircraftResource\Pages;
use App\Models\Aircraft;
use App\Models\Airport;
use App\Models\Enums\AircraftState;
use App\Models\Enums\AircraftStatus;
use App\Models\File;
use App\Services\FileService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AircraftResource extends Resource
{
    protected static ?string $model = Aircraft::class;

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('subfleet_and_status')
                    ->heading('Subfleet And Status')
                    ->schema([
                        Forms\Components\Select::make('subfleet')
                            ->label('Subfleet')
                            ->relationship('subfleet', 'name')
                            ->searchable()
                            ->required()
                            ->native(false),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options(AircraftStatus::labels())
                            ->required()
                            ->native(false),

                        Forms\Components\Select::make('hub_id')
                            ->label('Home')
                            ->relationship('home', 'icao')
                            ->getOptionLabelFromRecordUsing(fn (Airport $record): string => $record->icao.' - '.$record->name)
                            ->searchable()
                            ->native(false),

                        Forms\Components\Select::make('airport_id')
                            ->label('Location')
                            ->relationship('airport', 'icao')
                            ->getOptionLabelFromRecordUsing(fn (Airport $record): string => $record->icao.' - '.$record->name)
                            ->searchable()
                            ->native(false),
                    ])->columns(4),

                Forms\Components\Section::make('aircraft_information')
                    ->heading('Aircraft Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->string(),

                        Forms\Components\TextInput::make('registration')
                            ->required()
                            ->string(),

                        Forms\Components\TextInput::make('fin')
                            ->label('FIN')
                            ->string(),

                        Forms\Components\TextInput::make('selcal')
                            ->label('SELCAL')
                            ->string(),

                        Forms\Components\TextInput::make('iata')
                            ->label('IATA')
                            ->string(),

                        Forms\Components\TextInput::make('icao')
                            ->label('ICAO')
                            ->string(),

                        Forms\Components\TextInput::make('simbrief_type')
                            ->label('SimBrief Type')
                            ->string(),

                        Forms\Components\TextInput::make('hex_code')
                            ->label('Hex Code')
                            ->string(),
                    ])->columns(4),

                Forms\Components\Section::make('weights')
                    ->heading('Certified Weights')
                    ->schema([
                        Forms\Components\TextInput::make('dow')
                            ->label('Dry Operating Weight (DOW/OEW)')
                            ->numeric(),

                        Forms\Components\TextInput::make('zfw')
                            ->label('Max Zero Fuel Weight (MZFW)')
                            ->numeric(),

                        Forms\Components\TextInput::make('mtow')
                            ->label('Max Takeoff Weight (MTOW)')
                            ->numeric(),

                        Forms\Components\TextInput::make('mlw')
                            ->label('Max Landing Weight (MLW)')
                            ->numeric(),
                    ])->columns(4),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('registration')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('fin')
                    ->sortable(),

                Tables\Columns\TextColumn::make('subfleet.name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('hub_id')
                    ->label('Home')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('airport_id')
                    ->label('Location')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('landingTime')
                    ->since()
                    ->sortable(),

                Tables\Columns\TextColumn::make('flight_time')
                    ->formatStateUsing(fn (string $state): string => floor($state / 60).'h'.$state % 60 .'min')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        AircraftStatus::ACTIVE => 'success',
                        default                => 'info',
                    })
                    ->sortable()
                    ->formatStateUsing(fn (string $state): string => AircraftStatus::label($state)),

                Tables\Columns\TextColumn::make('state')
                    ->badge()
                    ->color(fn (int $state): string => match ($state) {
                        AircraftState::PARKED => 'success',
                        default               => 'info',
                    })
                    ->sortable()
                    ->formatStateUsing(fn (int $state): string => AircraftState::label($state)),

            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\SelectFilter::make('subfleet')
                    ->relationship('subfleet', 'name')
                    ->searchable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\ForceDeleteAction::make()->before(function (Aircraft $record) {
                    $record->files()->each(function (File $file) {
                        app(FileService::class)->removeFile($file);
                    });
                }),
                Tables\Actions\RestoreAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make()->before(function (Collection $records) {
                        $records->each(fn (Aircraft $record) => $record->files()->each(function (File $file) {
                            app(FileService::class)->removeFile($file);
                        }));
                    }),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->icon('heroicon-o-plus-circle')
                    ->label('Add Aircraft'),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ExpensesRelationManager::class,
            FilesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListAircraft::route('/'),
            'create' => Pages\CreateAircraft::route('/create'),
            'edit'   => Pages\EditAircraft::route('/{record}/edit'),
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
        return ['name', 'registration', 'icao'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string|Htmlable
    {
        return $record->name.' - '.$record->registration;
    }
}
