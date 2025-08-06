<?php

namespace App\Filament\Resources;

use App\Filament\RelationManagers\FaresRelationManager;
use App\Filament\Resources\FlightResource\Pages;
use App\Filament\Resources\FlightResource\RelationManagers;
use App\Models\Airport;
use App\Models\Enums\Days;
use App\Models\Enums\FlightType;
use App\Models\Flight;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class FlightResource extends Resource
{
    protected static ?string $model = Flight::class;

    protected static ?string $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Flights';

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-vertical';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make()->schema([
                    Forms\Components\Section::make('flight_information')->heading('Flight Information')->schema([
                        Forms\Components\Select::make('airline_id')
                            ->relationship('airline', 'name')
                            ->searchable()
                            ->required()
                            ->native(false),

                        Forms\Components\Select::make('flight_type')
                            ->searchable()
                            ->native(false)
                            ->required()
                            ->options(FlightType::select()),

                        Forms\Components\TextInput::make('callsign')
                            ->string()
                            ->maxLength(4),

                        Forms\Components\TextInput::make('flight_number')
                            ->integer()
                            ->required(),

                        Forms\Components\TextInput::make('route_code')
                            ->string()
                            ->maxLength(5),

                        Forms\Components\TextInput::make('route_leg')
                            ->integer(),

                        Forms\Components\TimePicker::make('flight_time')
                            ->seconds(false)
                            ->label('Flight Time')
                            ->native(false)
                            ->required(),

                        Forms\Components\TextInput::make('pilot_pay')
                            ->numeric()
                            ->helperText('Fill this in to pay a pilot a fixed amount for this flight.'),

                        Forms\Components\Grid::make()->schema([
                            Forms\Components\TextInput::make('load_factor')
                                ->numeric()
                                ->helperText('Percentage value for pax/cargo load, leave blank to use the default value.'),

                            Forms\Components\TextInput::make('load_factor_variance')
                                ->numeric()
                                ->helperText('Percentage of how much the load can vary (+/-), leave blank to use the default value.'),

                        ])->columnSpan(3),
                    ])->columns(3)->columnSpan(['lg' => 2]),
                    Forms\Components\Section::make('scheduling')->heading('Scheduling')->schema([
                        Forms\Components\DatePicker::make('start_date')
                            ->native(false)
                            ->minDate(now()),

                        Forms\Components\DatePicker::make('end_date')
                            ->native(false)
                            ->minDate(now()),

                        Forms\Components\Select::make('days')
                            ->options(Days::labels())
                            ->multiple()
                            ->native(false),

                        Forms\Components\TimePicker::make('dpt_time')
                            ->seconds(false)
                            ->label('Departure Time'),

                        Forms\Components\TimePicker::make('arr_time')
                            ->seconds(false)
                            ->label('Arrival Time'),
                    ])->columnSpan(1),
                ])->columns(3),

                Forms\Components\Section::make('route')->heading('Route')->schema([
                    Forms\Components\Grid::make()->schema([
                        Forms\Components\Select::make('dpt_airport_id')
                            ->label('Departure Airport')
                            ->relationship('dpt_airport', 'icao')
                            ->getOptionLabelFromRecordUsing(fn (Airport $record): string => $record->icao.' - '.$record->name)
                            ->searchable()
                            ->required()
                            ->native(false),

                        Forms\Components\Select::make('arr_airport_id')
                            ->label('Arrival Airport')
                            ->relationship('arr_airport', 'icao')
                            ->getOptionLabelFromRecordUsing(fn (Airport $record): string => $record->icao.' - '.$record->name)
                            ->searchable()
                            ->required()
                            ->native(false),
                    ])->columns(2),

                    Forms\Components\Textarea::make('route'),

                    Forms\Components\Grid::make('')->schema([
                        Forms\Components\Select::make('alt_aiport_id')
                            ->label('Alternate Airport')
                            ->relationship('alt_airport', 'icao')
                            ->getOptionLabelFromRecordUsing(fn (Airport $record): string => $record->icao.' - '.$record->name)
                            ->searchable()
                            ->native(false),

                        Forms\Components\TextInput::make('level')
                            ->label('Flight Level')
                            ->integer()
                            ->hint('In feet'),

                        Forms\Components\TextInput::make('distance')
                            ->integer()
                            ->hint('In nautical miles'),
                    ])->columns(3),
                ]),

                Forms\Components\Section::make('remarks')->heading('Remarks')->schema([
                    Forms\Components\RichEditor::make('notes')
                        ->columnSpanFull(),

                    Forms\Components\Toggle::make('active')
                        ->offIcon('heroicon-m-x-circle')
                        ->offColor('danger')
                        ->onIcon('heroicon-m-check-circle')
                        ->onColor('success'),

                    Forms\Components\Toggle::make('visible')
                        ->offIcon('heroicon-m-x-circle')
                        ->offColor('danger')
                        ->onIcon('heroicon-m-check-circle')
                        ->onColor('success'),
                ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ident')
                    ->label('Flight #')
                    ->searchable(['flight_number'])
                    ->sortable(['airline_id', 'flight_number']),

                TextColumn::make('dpt_airport_id')
                    ->label('Dep')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('arr_airport_id')
                    ->label('Arr')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('dpt_time')
                    ->label('Dpt Time')
                    ->sortable(),

                TextColumn::make('arr_time')
                    ->label('Arr Time')
                    ->sortable(),

                TextColumn::make('notes'),

                IconColumn::make('active')
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger')
                    ->icon(fn (bool $state): string => $state ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
                    ->sortable(),

                IconColumn::make('visible')
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger')
                    ->icon(fn (bool $state): string => $state ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),

                Tables\Filters\SelectFilter::make('airline')
                    ->relationship('airline', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('dpt_airport')
                    ->label('Departure Airport')
                    ->relationship('dpt_airport', 'icao')
                    ->getOptionLabelFromRecordUsing(fn (Airport $record): string => $record->icao.' - '.$record->name)
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('arr_airport')
                    ->label('Arrival Airport')
                    ->relationship('arr_airport', 'icao')
                    ->getOptionLabelFromRecordUsing(fn (Airport $record): string => $record->icao.' - '.$record->name)
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\ForceDeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->icon('heroicon-o-plus-circle')
                    ->label('Add Flight'),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\SubfleetsRelationManager::class,
            RelationManagers\FieldValuesRelationManager::class,
            FaresRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListFlights::route('/'),
            'create' => Pages\CreateFlight::route('/create'),
            'edit'   => Pages\EditFlight::route('/{record}/edit'),
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
            'Departure Airport' => $record->dpt_airport_id,
            'Arrival Airport'   => $record->arr_airport_id,
        ];
    }
}
