<?php

namespace App\Filament\Resources;

use App\Filament\RelationManagers\ExpensesRelationManager;
use App\Filament\RelationManagers\FilesRelationManager;
use App\Filament\Resources\AirportResource\Pages;
use App\Models\Airport;
use App\Models\File;
use App\Services\AirportService;
use App\Services\FileService;
use App\Support\Timezonelist;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AirportResource extends Resource
{
    protected static ?string $model = Airport::class;

    protected static ?string $navigationGroup = 'Config';
    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Airports';
    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('airport_information')
                ->heading('Airport Information')
                ->schema([
                    Forms\Components\TextInput::make('icao')
                        ->label('ICAO')
                        ->required()
                        ->string()
                        ->length(4)
                        ->columnSpan(2)
                        ->hintAction(
                            Forms\Components\Actions\Action::make('lookup')
                                ->icon('heroicon-o-magnifying-glass')
                                ->action(function (Forms\Get $get, Forms\Set $set) {
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

                    Forms\Components\TextInput::make('iata')
                        ->label('IATA')
                        ->string()
                        ->length(3)
                        ->columnSpan(2),

                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->string(),

                    Forms\Components\TextInput::make('lat')
                        ->label('Latitude')
                        ->required()
                        ->minValue(-90)
                        ->maxValue(90)
                        ->numeric(),

                    Forms\Components\TextInput::make('lon')
                        ->label('Longitude')
                        ->required()
                        ->minValue(-180)
                        ->maxValue(180)
                        ->numeric(),

                    Forms\Components\TextInput::make('elevation')
                        ->numeric(),

                    Forms\Components\TextInput::make('country')
                        ->string(),

                    Forms\Components\TextInput::make('location')
                        ->string(),

                    Forms\Components\TextInput::make('region')
                        ->string(),

                    Forms\Components\Select::make('timezone')
                        ->options(Timezonelist::toArray())
                        ->searchable()
                        ->allowHtml()
                        ->native(false),

                    Forms\Components\TextInput::make('ground_handling_cost')
                        ->label('Ground Handling Cost')
                        ->helperText('This is the base rate per-flight. A multiplier for this rate can be set in the subfleet, so you can modulate those costs from there.')
                        ->numeric(),

                    Forms\Components\TextInput::make('fuel_jeta_cost')
                        ->label('Jet A Fuel Cost')
                        ->helperText('This is the cost per lbs.')
                        ->numeric(),

                    Forms\Components\TextInput::make('fuel_100ll_cost')
                        ->label('100LL Fuel Cost')
                        ->helperText('This is the cost per lbs.')
                        ->numeric(),

                    Forms\Components\TextInput::make('fuel_mogas_cost')
                        ->label('MOGAS Fuel Cost')
                        ->helperText('This is the cost per lbs.')
                        ->numeric(),

                    Forms\Components\RichEditor::make('notes')
                        ->columnSpan(4),

                    Forms\Components\Toggle::make('hub')
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
                Tables\Columns\TextColumn::make('icao')
                    ->label('ICAO')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('iata')
                    ->label('IATA')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('location')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\IconColumn::make('hub')
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger')
                    ->icon(fn (bool $state): string => $state ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
                    ->sortable(),

                Tables\Columns\TextInputColumn::make('ground_handling_cost')
                    ->label('GH Cost')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextInputColumn::make('fuel_jeta_cost')
                    ->label('JetA')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextInputColumn::make('fuel_100ll_cost')
                    ->label('100LL')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextInputColumn::make('fuel_mogas_cost')
                    ->label('MOGAS')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('only_hubs')->query(fn (Builder $query): Builder => $query->where('hub', 1)),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\ForceDeleteAction::make()->before(function (Airport $record) {
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
                        $records->each(fn (Airport $record) => $record->files()->each(function (File $file) {
                            app(FileService::class)->removeFile($file);
                        }));
                    }),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
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
            'index'  => Pages\ListAirports::route('/'),
            'create' => Pages\CreateAirport::route('/create'),
            'edit'   => Pages\EditAirport::route('/{record}/edit'),
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
