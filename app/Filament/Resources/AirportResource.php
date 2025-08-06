<?php

namespace App\Filament\Resources;

use App\Filament\RelationManagers\ExpensesRelationManager;
use App\Filament\RelationManagers\FilesRelationManager;
use App\Filament\Resources\AirportResource\Pages\CreateAirport;
use App\Filament\Resources\AirportResource\Pages\EditAirport;
use App\Filament\Resources\AirportResource\Pages\ListAirports;
use App\Models\Airport;
use App\Models\File;
use App\Services\AirportService;
use App\Services\FileService;
use App\Support\Timezonelist;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AirportResource extends Resource
{
    protected static ?string $model = Airport::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Config';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Airports';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-map-pin';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('airport_information')
                    ->heading('Airport Information')
                    ->schema([
                        TextInput::make('icao')
                            ->label('ICAO')
                            ->required()
                            ->string()
                            ->length(4)
                            ->columnSpan(2)
                            ->hintAction(
                                Action::make('lookup')
                                    ->icon('heroicon-o-magnifying-glass')
                                    ->action(function (Get $get, Set $set) {
                                        $airport = app(AirportService::class)->lookupAirport($get('icao'));

                                        foreach ($airport as $key => $value) {
                                            if ($key === 'city') {
                                                $key = 'location';
                                            }

                                            $set($key, $value);
                                        }

                                        if (count($airport) > 0) {
                                            Notification::make('')
                                                ->success()
                                                ->title('Lookup Successful')
                                                ->send();
                                        } else {
                                            Notification::make('')
                                                ->danger()
                                                ->title('Lookup Failed')
                                                ->body('No airport was found with ICAO: '.$get('icao'))
                                                ->send();
                                        }
                                    })
                            ),

                        TextInput::make('iata')
                            ->label('IATA')
                            ->string()
                            ->length(3)
                            ->columnSpan(2),

                        TextInput::make('name')
                            ->required()
                            ->string(),

                        TextInput::make('lat')
                            ->label('Latitude')
                            ->required()
                            ->minValue(-90)
                            ->maxValue(90)
                            ->numeric(),

                        TextInput::make('lon')
                            ->label('Longitude')
                            ->required()
                            ->minValue(-180)
                            ->maxValue(180)
                            ->numeric(),

                        TextInput::make('elevation')
                            ->numeric(),

                        TextInput::make('country')
                            ->string(),

                        TextInput::make('location')
                            ->string(),

                        TextInput::make('region')
                            ->string(),

                        Select::make('timezone')
                            ->options(Timezonelist::toArray())
                            ->searchable()
                            ->allowHtml()
                            ->native(false),

                        TextInput::make('ground_handling_cost')
                            ->label('Ground Handling Cost')
                            ->helperText('This is the base rate per-flight. A multiplier for this rate can be set in the subfleet, so you can modulate those costs from there.')
                            ->numeric(),

                        TextInput::make('fuel_jeta_cost')
                            ->label('Jet A Fuel Cost')
                            ->helperText('This is the cost per lbs.')
                            ->numeric(),

                        TextInput::make('fuel_100ll_cost')
                            ->label('100LL Fuel Cost')
                            ->helperText('This is the cost per lbs.')
                            ->numeric(),

                        TextInput::make('fuel_mogas_cost')
                            ->label('MOGAS Fuel Cost')
                            ->helperText('This is the cost per lbs.')
                            ->numeric(),

                        RichEditor::make('notes')
                            ->columnSpan(4),

                        Toggle::make('hub')
                            ->offIcon('heroicon-m-x-circle')
                            ->offColor('danger')
                            ->onIcon('heroicon-m-check-circle')
                            ->onColor('success'),
                    ])->columns(4),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('icao')
                    ->label('ICAO')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('iata')
                    ->label('IATA')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('location')
                    ->searchable()
                    ->sortable(),

                IconColumn::make('hub')
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger')
                    ->icon(fn (bool $state): string => $state ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
                    ->sortable(),

                TextInputColumn::make('ground_handling_cost')
                    ->label('GH Cost')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextInputColumn::make('fuel_jeta_cost')
                    ->label('JetA')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextInputColumn::make('fuel_100ll_cost')
                    ->label('100LL')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextInputColumn::make('fuel_mogas_cost')
                    ->label('MOGAS')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('only_hubs')->query(fn (Builder $query): Builder => $query->where('hub', 1)),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                ForceDeleteAction::make()->before(function (Airport $record) {
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
                        $records->each(fn (Airport $record) => $record->files()->each(function (File $file) {
                            app(FileService::class)->removeFile($file);
                        }));
                    }),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
                CreateAction::make()
                    ->icon('heroicon-o-plus-circle')
                    ->label('Add Airport'),
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
            'index'  => ListAirports::route('/'),
            'create' => CreateAirport::route('/create'),
            'edit'   => EditAirport::route('/{record}/edit'),
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
        return ['name', 'icao', 'location'];
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
