<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PirepResource\Pages;
use App\Filament\Resources\PirepResource\RelationManagers;
use App\Filament\Resources\PirepResource\Widgets\PirepStats;
use App\Models\Airport;
use App\Models\Enums\FlightType;
use App\Models\Enums\PirepSource;
use App\Models\Enums\PirepState;
use App\Models\Pirep;
use App\Services\PirepService;
use App\Support\Units\Time;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Guava\FilamentClusters\Forms\Cluster;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PirepResource extends Resource
{
    protected static ?string $model = Pirep::class;
    protected static ?string $navigationGroup = 'Operations';
    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Pireps';

    protected static ?string $navigationIcon = 'heroicon-o-cloud-arrow-up';

    public static function getNavigationBadge(): ?string
    {
        return Pirep::where('state', PirepState::PENDING)->count() > 0
            ? Pirep::where('state', PirepState::PENDING)->count()
            : null;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Information')->schema([

                    Cluster::make([
                        Forms\Components\TextInput::make('flight_number')
                            ->placeholder('Flight Number'),

                        Forms\Components\TextInput::make('route_code')
                            ->placeholder('Route Code'),

                        Forms\Components\TextInput::make('route_leg')
                            ->placeholder('Route Leg'),
                    ])
                        ->label('Flight Number/Route Code/Route Leg')
                        ->columnSpan(2),

                    Forms\Components\Select::make('flight_type')
                        ->disabled(false)
                        ->options(FlightType::select())
                        ->columnSpan(2)
                        ->native(false),

                    Forms\Components\Placeholder::make('source')
                        ->content(fn (Pirep $record): string => PirepSource::label($record->source).(filled($record->source_name) ? '('.$record->source_name.')' : ''))
                        ->label('Filed Via: '),
                ])
                    ->columns(5)
                    ->disabled(fn (Pirep $record): bool => $record->read_only),

                Forms\Components\Grid::make()->schema([
                    Forms\Components\Section::make('Pirep Details')->schema([
                        Forms\Components\Grid::make('')->schema([
                            Forms\Components\Select::make('airline_id')
                                ->relationship('airline', 'name')
                                ->native(false)
                                ->disabled(fn (Pirep $record): bool => $record->read_only),

                            Forms\Components\Select::make('aircraft_id')
                                ->relationship('aircraft', 'name')
                                ->native(false)
                                ->disabled(fn (Pirep $record): bool => $record->read_only),

                            Cluster::make([
                                Forms\Components\TextInput::make('hours')
                                    ->placeholder('hours')
                                    ->formatStateUsing(fn (Pirep $record): int => $record->flight_time / 60),

                                Forms\Components\TextInput::make('minutes')
                                    ->placeholder('minutes')
                                    ->formatStateUsing(fn (Pirep $record): int => $record->flight_time % 60),
                            ])->label('Flight Time'),

                            Forms\Components\Grid::make('')->schema([
                                Forms\Components\Select::make('dpt_airport_id')
                                    ->label('Departure Airport')
                                    ->relationship('dpt_airport', 'icao')
                                    ->getOptionLabelFromRecordUsing(fn (Airport $record): string => $record->icao.' - '.$record->name)
                                    ->searchable()
                                    ->native(false)
                                    ->columnSpan(1)
                                    ->disabled(fn (Pirep $record): bool => $record->read_only),

                                Forms\Components\Select::make('arr_airport_id')
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

                            Forms\Components\TextInput::make('block_fuel')
                                ->hint('In lbs'),

                            Forms\Components\TextInput::make('fuel_used')
                                ->label('Used Fuel')
                                ->hint('In lbs'),

                            Forms\Components\TextInput::make('level')
                                ->hint('In ft')
                                ->label('Flight Level'),

                            Forms\Components\TextInput::make('distance')
                                ->hint('In nmi'),

                            Forms\Components\TextInput::make('score'),
                        ])->columns(3),

                        Forms\Components\Textarea::make('route'),

                        Forms\Components\RichEditor::make('notes'),
                    ])->columnSpan(2),

                    Forms\Components\Section::make('Planned Details')->schema([
                        Cluster::make([
                            Forms\Components\TextInput::make('pln_hours')
                                ->placeholder('hours')
                                ->formatStateUsing(fn (Pirep $record): int => $record->planned_flight_time / 60),

                            Forms\Components\TextInput::make('pln_minutes')
                                ->placeholder('minutes')
                                ->formatStateUsing(fn (Pirep $record): int => $record->planned_flight_time % 60),
                        ])
                            ->label('Planned Flight Time'),

                        Forms\Components\TextInput::make('level')
                            ->hint('In ft')
                            ->label('Planned Flight Level'),

                        Forms\Components\TextInput::make('planned_distance')
                            ->hint('In nmi'),

                        Forms\Components\TextInput::make('landing_rate')
                            ->hint('In ft/min'),

                        Forms\Components\Textarea::make('route')
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
                    ->form([
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
                Tables\Filters\TrashedFilter::make(),
            ])
            ->recordUrl(fn (Pirep $record): string => self::getUrl('edit', ['record' => $record]))
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('accept')
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

                    Tables\Actions\Action::make('reject')
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

                    Tables\Actions\EditAction::make(),

                    Tables\Actions\DeleteAction::make(),
                    Tables\Actions\ForceDeleteAction::make(),
                    Tables\Actions\RestoreAction::make(),

                    Tables\Actions\Action::make('view')
                        ->color('info')
                        ->icon('heroicon-m-eye')
                        ->label('View Pirep')
                        ->url(fn (Pirep $record): string => route('frontend.pireps.show', $record->id))
                        ->openUrlInNewTab(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\FaresRelationManager::class,
            RelationManagers\FieldValuesRelationManager::class,
            RelationManagers\CommentsRelationManager::class,
            RelationManagers\TransactionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPireps::route('/'),
            'edit'  => Pages\EditPirep::route('/{record}/edit'),
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
