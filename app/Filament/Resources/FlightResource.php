<?php

namespace App\Filament\Resources;

use App\Filament\RelationManagers\FaresRelationManager;
use App\Filament\Resources\FlightResource\Pages\CreateFlight;
use App\Filament\Resources\FlightResource\Pages\EditFlight;
use App\Filament\Resources\FlightResource\Pages\ListFlights;
use App\Filament\Resources\FlightResource\RelationManagers\FieldValuesRelationManager;
use App\Filament\Resources\FlightResource\RelationManagers\SubfleetsRelationManager;
use App\Models\Airport;
use App\Models\Enums\Days;
use App\Models\Enums\FlightType;
use App\Models\Flight;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class FlightResource extends Resource
{
    protected static ?string $model = Flight::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Flights';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-vertical';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make()->schema([
                    Section::make('flight_information')->heading('Flight Information')->schema([
                        Select::make('airline_id')
                            ->relationship('airline', 'name')
                            ->searchable()
                            ->required()
                            ->native(false),

                        Select::make('flight_type')
                            ->searchable()
                            ->native(false)
                            ->required()
                            ->options(FlightType::select()),

                        TextInput::make('callsign')
                            ->string()
                            ->maxLength(4),

                        TextInput::make('flight_number')
                            ->integer()
                            ->required(),

                        TextInput::make('route_code')
                            ->string()
                            ->maxLength(5),

                        TextInput::make('route_leg')
                            ->integer(),

                        TimePicker::make('flight_time')
                            ->seconds(false)
                            ->label('Flight Time')
                            ->native(false)
                            ->required(),

                        TextInput::make('pilot_pay')
                            ->numeric()
                            ->helperText('Fill this in to pay a pilot a fixed amount for this flight.'),

                        Grid::make()->schema([
                            TextInput::make('load_factor')
                                ->numeric()
                                ->helperText('Percentage value for pax/cargo load, leave blank to use the default value.'),

                            TextInput::make('load_factor_variance')
                                ->numeric()
                                ->helperText('Percentage of how much the load can vary (+/-), leave blank to use the default value.'),

                        ])->columnSpan(3),
                    ])->columns(3)->columnSpan(['lg' => 2]),
                    Section::make('scheduling')->heading('Scheduling')->schema([
                        DatePicker::make('start_date')
                            ->native(false)
                            ->minDate(now()),

                        DatePicker::make('end_date')
                            ->native(false)
                            ->minDate(now()),

                        Select::make('days')
                            ->options(Days::labels())
                            ->multiple()
                            ->native(false),

                        TimePicker::make('dpt_time')
                            ->seconds(false)
                            ->label('Departure Time'),

                        TimePicker::make('arr_time')
                            ->seconds(false)
                            ->label('Arrival Time'),
                    ])->columnSpan(1),
                ])->columns(3),

                Section::make('route')->heading('Route')->schema([
                    Grid::make()->schema([
                        Select::make('dpt_airport_id')
                            ->label('Departure Airport')
                            ->relationship('dpt_airport', 'icao')
                            ->getOptionLabelFromRecordUsing(fn (Airport $record): string => $record->icao.' - '.$record->name)
                            ->searchable()
                            ->required()
                            ->native(false),

                        Select::make('arr_airport_id')
                            ->label('Arrival Airport')
                            ->relationship('arr_airport', 'icao')
                            ->getOptionLabelFromRecordUsing(fn (Airport $record): string => $record->icao.' - '.$record->name)
                            ->searchable()
                            ->required()
                            ->native(false),
                    ])->columns(2),

                    Textarea::make('route'),

                    Grid::make('')->schema([
                        Select::make('alt_aiport_id')
                            ->label('Alternate Airport')
                            ->relationship('alt_airport', 'icao')
                            ->getOptionLabelFromRecordUsing(fn (Airport $record): string => $record->icao.' - '.$record->name)
                            ->searchable()
                            ->native(false),

                        TextInput::make('level')
                            ->label('Flight Level')
                            ->integer()
                            ->hint('In feet'),

                        TextInput::make('distance')
                            ->integer()
                            ->hint('In nautical miles'),
                    ])->columns(3),
                ]),

                Section::make('remarks')->heading('Remarks')->schema([
                    RichEditor::make('notes')
                        ->columnSpanFull(),

                    Toggle::make('active')
                        ->offIcon('heroicon-m-x-circle')
                        ->offColor('danger')
                        ->onIcon('heroicon-m-check-circle')
                        ->onColor('success'),

                    Toggle::make('visible')
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
                TrashedFilter::make(),

                SelectFilter::make('airline')
                    ->relationship('airline', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('dpt_airport')
                    ->label('Departure Airport')
                    ->relationship('dpt_airport', 'icao')
                    ->getOptionLabelFromRecordUsing(fn (Airport $record): string => $record->icao.' - '.$record->name)
                    ->searchable()
                    ->preload(),

                SelectFilter::make('arr_airport')
                    ->label('Arrival Airport')
                    ->relationship('arr_airport', 'icao')
                    ->getOptionLabelFromRecordUsing(fn (Airport $record): string => $record->icao.' - '.$record->name)
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                ForceDeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
                CreateAction::make()
                    ->icon('heroicon-o-plus-circle')
                    ->label('Add Flight'),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            SubfleetsRelationManager::class,
            FieldValuesRelationManager::class,
            FaresRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListFlights::route('/'),
            'create' => CreateFlight::route('/create'),
            'edit'   => EditFlight::route('/{record}/edit'),
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
