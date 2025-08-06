<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PirepResource\Pages\EditPirep;
use App\Filament\Resources\PirepResource\Pages\ListPireps;
use App\Filament\Resources\PirepResource\RelationManagers\CommentsRelationManager;
use App\Filament\Resources\PirepResource\RelationManagers\FaresRelationManager;
use App\Filament\Resources\PirepResource\RelationManagers\FieldValuesRelationManager;
use App\Filament\Resources\PirepResource\RelationManagers\TransactionsRelationManager;
use App\Filament\Resources\PirepResource\Widgets\PirepStats;
use App\Models\Airport;
use App\Models\Enums\FlightType;
use App\Models\Enums\PirepSource;
use App\Models\Enums\PirepState;
use App\Models\Pirep;
use App\Services\PirepService;
use App\Support\Units\Time;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PirepResource extends Resource
{
    protected static ?string $model = Pirep::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Pireps';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cloud-arrow-up';

    public static function getNavigationBadge(): ?string
    {
        return Pirep::where('state', PirepState::PENDING)->count() > 0
            ? Pirep::where('state', PirepState::PENDING)->count()
            : null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Basic Information')->schema([

                    TextInput::make('flight_number')
                        ->label('Flight Number'),

                    TextInput::make('route_code')
                        ->label('Route Code'),

                    TextInput::make('route_leg')
                        ->label('Route Leg'),

                    Select::make('flight_type')
                        ->disabled(false)
                        ->options(FlightType::select())
                        ->native(false),

                    Placeholder::make('source')
                        ->content(fn (Pirep $record): string => PirepSource::label($record->source).(filled($record->source_name) ? '('.$record->source_name.')' : ''))
                        ->label('Filed Via: '),
                ])
                    ->columns(5)
                    ->disabled(fn (Pirep $record): bool => $record->read_only),

                Grid::make()->schema([
                    Section::make('Pirep Details')->schema([
                        Grid::make('')->schema([
                            Select::make('airline_id')
                                ->relationship('airline', 'name')
                                ->native(false)
                                ->disabled(fn (Pirep $record): bool => $record->read_only),

                            Select::make('aircraft_id')
                                ->relationship('aircraft', 'name')
                                ->native(false)
                                ->disabled(fn (Pirep $record): bool => $record->read_only),

                            TimePicker::make('flight_time')
                                ->label('Flight Time')
                                ->seconds(false)
                                ->native(false),

                            Grid::make('')->schema([
                                Select::make('dpt_airport_id')
                                    ->label('Departure Airport')
                                    ->relationship('dpt_airport', 'icao')
                                    ->getOptionLabelFromRecordUsing(fn (Airport $record): string => $record->icao.' - '.$record->name)
                                    ->searchable()
                                    ->native(false)
                                    ->columnSpan(1)
                                    ->disabled(fn (Pirep $record): bool => $record->read_only),

                                Select::make('arr_airport_id')
                                    ->label('Arrival Airport')
                                    ->relationship('arr_airport', 'icao')
                                    ->getOptionLabelFromRecordUsing(fn (Airport $record): string => $record->icao.' - '.$record->name)
                                    ->searchable()
                                    ->native(false)
                                    ->columnSpan(1)
                                    ->disabled(fn (Pirep $record): bool => $record->read_only),
                            ])
                                ->columns(2)
                                ->columnSpan(3),

                            TextInput::make('block_fuel')
                                ->hint('In lbs'),

                            TextInput::make('fuel_used')
                                ->label('Used Fuel')
                                ->hint('In lbs'),

                            TextInput::make('level')
                                ->hint('In ft')
                                ->label('Flight Level'),

                            TextInput::make('distance')
                                ->hint('In nmi'),

                            TextInput::make('score'),
                        ])->columns(3),

                        Textarea::make('route'),

                        RichEditor::make('notes'),
                    ])->columnSpan(2),

                    Section::make('Planned Details')->schema([
                        TimePicker::make('planned_flight_time')
                            ->label('Planned Flight Time')
                            ->seconds(false)
                            ->native(false),

                        TextInput::make('level')
                            ->hint('In ft')
                            ->label('Planned Flight Level'),

                        TextInput::make('planned_distance')
                            ->hint('In nmi'),

                        TextInput::make('landing_rate')
                            ->hint('In ft/min'),

                        Textarea::make('route')
                            ->label('Provided Route')
                            ->autosize(),
                    ])
                        ->disabled()
                        ->columnSpan(1),
                ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereNotIn('state', [PirepState::DRAFT, PirepState::IN_PROGRESS, PirepState::CANCELLED]))
            ->columns([
                TextColumn::make('ident')
                    ->label('Flight Ident')
                    ->searchable(['flight_number'])
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('Pilot')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('dpt_airport_id')
                    ->label('DEP')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('arr_airport_id')
                    ->label('ARR')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('flight_time')
                    ->formatStateUsing(fn (int $state): string => Time::minutesToTimeString($state))
                    ->sortable(),

                TextColumn::make('aircraft')
                    ->formatStateUsing(fn (Pirep $record): string => $record->aircraft->registration.' - '.$record->aircraft->name)
                    ->sortable(),

                TextColumn::make('source')
                    ->label('Filed Using')->formatStateUsing(fn (int $state): string => PirepSource::label($state))
                    ->sortable(),

                TextColumn::make('state')
                    ->badge()
                    ->color(fn (int $state): string => match ($state) {
                        PirepState::PENDING  => 'warning',
                        PirepState::ACCEPTED => 'success',
                        PirepState::REJECTED => 'danger',
                        default              => 'info',
                    })
                    ->formatStateUsing(fn (int $state): string => PirepState::label($state))
                    ->sortable(),

                TextColumn::make('submitted_at')
                    ->dateTime('d-m-Y H:i')
                    ->label('File Date')
                    ->sortable(),
            ])
            ->defaultSort('submitted_at', 'desc')
            ->filters([
                Filter::make('submitted_at')
                    ->schema([
                        DatePicker::make('filed_from'),
                        DatePicker::make('filed_until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                isset($data['filed_from']) && $data['filed_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('submitted_at', '>=', $date),
                            )
                            ->when(
                                isset($data['filed_until']) && $data['filed_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('submitted_at', '<=', $date),
                            );
                    }),
                TrashedFilter::make(),
            ])
            ->recordUrl(fn (Pirep $record): string => self::getUrl('edit', ['record' => $record]))
            ->recordActions([
                ActionGroup::make([
                    Action::make('accept')
                        ->color('success')
                        ->icon('heroicon-m-check-circle')
                        ->label('Accept')
                        ->visible(fn (Pirep $record): bool => ($record->state === PirepState::PENDING || $record->state === PirepState::REJECTED))
                        ->action(function (Pirep $record): void {
                            $pirep = app(PirepService::class)->changeState($record, PirepState::ACCEPTED);
                            if ($pirep->state === PirepState::ACCEPTED) {
                                Notification::make()
                                    ->title('Pirep Accepted')
                                    ->success()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('There was an error accepting the Pirep')
                                    ->danger()
                                    ->send();
                            }
                        }),

                    Action::make('reject')
                        ->color('danger')
                        ->icon('heroicon-m-x-circle')
                        ->label('Reject')
                        ->visible(fn (Pirep $record): bool => ($record->state === PirepState::PENDING || $record->state === PirepState::ACCEPTED))
                        ->action(function (Pirep $record): void {
                            $pirep = app(PirepService::class)->changeState($record, PirepState::REJECTED);
                            if ($pirep->state === PirepState::REJECTED) {
                                Notification::make()
                                    ->title('Pirep Rejected')
                                    ->success()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('There was an error rejecting the Pirep')
                                    ->danger()
                                    ->send();
                            }
                        }),

                    EditAction::make(),

                    DeleteAction::make(),
                    ForceDeleteAction::make(),
                    RestoreAction::make(),

                    Action::make('view')
                        ->color('info')
                        ->icon('heroicon-m-eye')
                        ->label('View Pirep')
                        ->url(fn (Pirep $record): string => route('frontend.pireps.show', $record->id))
                        ->openUrlInNewTab(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            FaresRelationManager::class,
            FieldValuesRelationManager::class,
            CommentsRelationManager::class,
            TransactionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPireps::route('/'),
            'edit'  => EditPirep::route('/{record}/edit'),
        ];
    }

    public static function getWidgets(): array
    {
        return [
            PirepStats::class,
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
