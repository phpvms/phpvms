<?php

namespace App\Filament\Resources;

use App\Filament\RelationManagers\ExpensesRelationManager;
use App\Filament\RelationManagers\FilesRelationManager;
use App\Filament\Resources\AircraftResource\Pages\CreateAircraft;
use App\Filament\Resources\AircraftResource\Pages\EditAircraft;
use App\Filament\Resources\AircraftResource\Pages\ListAircraft;
use App\Models\Aircraft;
use App\Models\Airport;
use App\Models\Enums\AircraftState;
use App\Models\Enums\AircraftStatus;
use App\Models\File;
use App\Models\Subfleet;
use App\Services\FileService;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
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

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('subfleet_and_status')
                    ->heading('Subfleet And Status')
                    ->schema([
                        Select::make('subfleet_id')
                            ->label('Subfleet')
                            ->relationship('subfleet')
                            ->getOptionLabelFromRecordUsing(fn (Subfleet $record) => $record->airline->name.' - '.$record->name)
                            ->preload()
                            ->searchable()
                            ->required()
                            ->native(false),

                        Select::make('status')
                            ->label('Status')
                            ->options(AircraftStatus::labels())
                            ->required()
                            ->native(false),

                        Select::make('hub_id')
                            ->label('Home')
                            ->relationship('home', 'icao')
                            ->getOptionLabelFromRecordUsing(fn (Airport $record): string => $record->icao.' - '.$record->name)
                            ->searchable()
                            ->native(false),

                        Select::make('airport_id')
                            ->label('Location')
                            ->relationship('airport', 'icao')
                            ->getOptionLabelFromRecordUsing(fn (Airport $record): string => $record->icao.' - '.$record->name)
                            ->searchable()
                            ->native(false),
                    ])->columns(4),

                Section::make('aircraft_information')
                    ->heading('Aircraft Information')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->string(),

                        TextInput::make('registration')
                            ->required()
                            ->string(),

                        TextInput::make('fin')
                            ->label('FIN')
                            ->string(),

                        TextInput::make('selcal')
                            ->label('SELCAL')
                            ->string(),

                        TextInput::make('iata')
                            ->label('IATA')
                            ->string(),

                        TextInput::make('icao')
                            ->label('ICAO')
                            ->string(),

                        TextInput::make('simbrief_type')
                            ->label('SimBrief Type')
                            ->string(),

                        TextInput::make('hex_code')
                            ->label('Hex Code')
                            ->string(),
                    ])->columns(4),

                Section::make('weights')
                    ->heading('Certified Weights')
                    ->schema([
                        TextInput::make('dow')
                            ->label('Dry Operating Weight (DOW/OEW)')
                            ->numeric(),

                        TextInput::make('zfw')
                            ->label('Max Zero Fuel Weight (MZFW)')
                            ->numeric(),

                        TextInput::make('mtow')
                            ->label('Max Takeoff Weight (MTOW)')
                            ->numeric(),

                        TextInput::make('mlw')
                            ->label('Max Landing Weight (MLW)')
                            ->numeric(),
                    ])->columns(4),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('registration')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('fin')
                    ->sortable(),

                TextColumn::make('subfleet.name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('hub_id')
                    ->label('Home')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('airport_id')
                    ->label('Location')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('landingTime')
                    ->since()
                    ->sortable(),

                TextColumn::make('flight_time')
                    ->formatStateUsing(fn (string $state): string => floor($state / 60).'h'.$state % 60 .'min')
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        AircraftStatus::ACTIVE => 'success',
                        default                => 'info',
                    })
                    ->sortable()
                    ->formatStateUsing(fn (string $state): string => AircraftStatus::label($state)),

                TextColumn::make('state')
                    ->badge()
                    ->color(fn (int $state): string => match ($state) {
                        AircraftState::PARKED => 'success',
                        default               => 'info',
                    })
                    ->sortable()
                    ->formatStateUsing(fn (int $state): string => AircraftState::label($state)),

            ])
            ->filters([
                TrashedFilter::make(),
                SelectFilter::make('subfleet')
                    ->relationship('subfleet', 'name')
                    ->searchable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                ForceDeleteAction::make()->before(function (Aircraft $record) {
                    $record->files()->each(function (File $file) {
                        app(FileService::class)->removeFile($file);
                    });
                }),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make()->before(function (Collection $records) {
                        $records->each(fn (Aircraft $record) => $record->files()->each(function (File $file) {
                            app(FileService::class)->removeFile($file);
                        }));
                    }),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
                CreateAction::make()
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
            'index'  => ListAircraft::route('/'),
            'create' => CreateAircraft::route('/create'),
            'edit'   => EditAircraft::route('/{record}/edit'),
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
