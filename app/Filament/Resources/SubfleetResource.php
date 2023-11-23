<?php

namespace App\Filament\Resources;

use App\Filament\RelationManagers\ExpensesRelationManager;
use App\Filament\RelationManagers\FaresRelationManager;
use App\Filament\RelationManagers\FilesRelationManager;
use App\Filament\Resources\SubfleetResource\Pages;
use App\Filament\Resources\SubfleetResource\RelationManagers;
use App\Models\Enums\FuelType;
use App\Models\File;
use App\Models\Subfleet;
use App\Repositories\AirlineRepository;
use App\Repositories\AirportRepository;
use App\Services\FileService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SubfleetResource extends Resource
{
    protected static ?string $model = Subfleet::class;
    protected static ?string $navigationGroup = 'Operations';
    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Fleet';

    protected static ?string $navigationIcon = 'heroicon-o-paper-airplane';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        $airportRepo = app(AirportRepository::class);
        $airports = $airportRepo->all()->mapWithKeys(fn ($item) => [$item->id => $item->icao.' - '.$item->name]);

        return $form
            ->schema([
                Forms\Components\Section::make('subfleet')
                    ->heading('Subfleet Information')
                    ->description('Subfleets are aircraft groups. The "type" is a short name. Airlines always
                    group aircraft together by feature, so 737s with winglets might have a type of
                    "B.738-WL". You can create as many as you want, you need at least one, though. Read more 
                    about subfleets in the docs.')
                    ->schema([
                        Forms\Components\Select::make('airline_id')
                            ->label('Airline')
                            ->options(app(AirlineRepository::class)->all()->pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->native(false),

                        // If we want to use an async search we need to change the dpt_airport relationship from hasOne to belongsTo (to use the relationship() method)
                        Forms\Components\Select::make('hub_id')
                            ->label('Home Base')
                            ->options($airports)
                            ->searchable()
                            ->native(false),

                        Forms\Components\TextInput::make('type')
                            ->required()
                            ->string(),

                        Forms\Components\TextInput::make('simbrief_type')
                        ->label('Simbrief Type')
                        ->string(),

                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->string(),

                        Forms\Components\Select::make('fuel_type')
                            ->label('Fuel Type')
                            ->options(FuelType::labels())
                            ->searchable()
                            ->native(false),

                        Forms\Components\TextInput::make('cost_block_hour')
                            ->label('Cost Per Hour')
                            ->minValue(0)
                            ->numeric()
                            ->step(0.01),

                        Forms\Components\TextInput::make('cost_delay_minute')
                            ->label('Cost Delay Per Minute')
                            ->minValue(0)
                            ->numeric()
                            ->step(0.01),

                        Forms\Components\TextInput::make('ground_handling_multiplier')
                            ->label('Expense Multiplier')
                            ->helperText('This is the multiplier for all expenses (inc GH costs) being applied to aircraft in this subfleet, as a percentage. Defaults to 100.')
                            ->minValue(0)
                            ->integer(),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextColumn::make('airline.name'),
                Tables\Columns\TextColumn::make('type'),
                Tables\Columns\TextColumn::make('hub_id'),
                Tables\Columns\TextColumn::make('aircraft_count')->label('Aircrafts')->counts('aircraft'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\Action::make('aircrafts')->url(fn (Subfleet $record) => AircraftResource::getUrl('index').'?tableFilters[subfleet][value]='.$record->id)->label('Aircrafts')->icon('heroicon-o-paper-airplane')->color('success'),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\ForceDeleteAction::make()->before(function (Subfleet $record) {
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
                        $records->each(fn (Subfleet $record) => $record->files()->each(function (File $file) {
                            app(FileService::class)->removeFile($file);
                        }));
                    }),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\RanksRelationManager::class,
            RelationManagers\TyperatingsRelationManager::class,
            FaresRelationManager::class,
            ExpensesRelationManager::class,
            FilesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListSubfleets::route('/'),
            'create' => Pages\CreateSubfleet::route('/create'),
            'edit'   => Pages\EditSubfleet::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
