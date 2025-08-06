<?php

namespace App\Filament\Resources;

use App\Filament\RelationManagers\ExpensesRelationManager;
use App\Filament\RelationManagers\FaresRelationManager;
use App\Filament\RelationManagers\FilesRelationManager;
use App\Filament\Resources\SubfleetResource\Pages\CreateSubfleet;
use App\Filament\Resources\SubfleetResource\Pages\EditSubfleet;
use App\Filament\Resources\SubfleetResource\Pages\ListSubfleets;
use App\Filament\Resources\SubfleetResource\RelationManagers\RanksRelationManager;
use App\Filament\Resources\SubfleetResource\RelationManagers\TyperatingsRelationManager;
use App\Models\Airport;
use App\Models\Enums\FuelType;
use App\Models\File;
use App\Models\Subfleet;
use App\Services\FileService;
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
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SubfleetResource extends Resource
{
    protected static ?string $model = Subfleet::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Fleet';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-paper-airplane';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('subfleet')
                    ->heading('Subfleet Information')
                    ->description('Subfleets are aircraft groups. The "type" is a short name. Airlines always
                    group aircraft together by feature, so 737s with winglets might have a type of
                    "B.738-WL". You can create as many as you want, you need at least one, though. Read more 
                    about subfleets in the docs.')
                    ->schema([
                        Select::make('airline_id')
                            ->relationship('airline', 'name')
                            ->searchable()
                            ->required()
                            ->native(false),

                        Select::make('hub_id')
                            ->label('Home Base')
                            ->relationship('home', 'icao')
                            ->getOptionLabelFromRecordUsing(fn (Airport $record): string => $record->icao.' - '.$record->name)
                            ->searchable()
                            ->native(false),

                        TextInput::make('type')
                            ->required()
                            ->string(),

                        TextInput::make('simbrief_type')
                            ->label('Simbrief Type')
                            ->string(),

                        TextInput::make('name')
                            ->required()
                            ->string(),

                        Select::make('fuel_type')
                            ->label('Fuel Type')
                            ->options(FuelType::labels())
                            ->searchable()
                            ->native(false),

                        TextInput::make('cost_block_hour')
                            ->label('Cost Per Hour')
                            ->minValue(0)
                            ->numeric()
                            ->step(0.01),

                        TextInput::make('cost_delay_minute')
                            ->label('Cost Delay Per Minute')
                            ->minValue(0)
                            ->numeric()
                            ->step(0.01),

                        TextInput::make('ground_handling_multiplier')
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
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('airline.name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('hub_id')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('aircraft_count')
                    ->label('Aircrafts')
                    ->counts('aircraft')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('aircraft')
                    ->url(fn (Subfleet $record): string => AircraftResource::getUrl('index').'?tableFilters[subfleet][value]='.$record->id)
                    ->label('Aircraft')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success'),

                EditAction::make(),
                DeleteAction::make(),
                ForceDeleteAction::make()->before(function (Subfleet $record) {
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
                        $records->each(fn (Subfleet $record) => $record->files()->each(function (File $file) {
                            app(FileService::class)->removeFile($file);
                        }));
                    }),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
                CreateAction::make()
                    ->icon('heroicon-o-plus-circle')
                    ->label('Add Subfleet'),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RanksRelationManager::class,
            TyperatingsRelationManager::class,
            FaresRelationManager::class,
            ExpensesRelationManager::class,
            FilesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListSubfleets::route('/'),
            'create' => CreateSubfleet::route('/create'),
            'edit'   => EditSubfleet::route('/{record}/edit'),
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
        return ['name', 'type'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string|Htmlable
    {
        return $record->type.' - '.$record->name;
    }
}
