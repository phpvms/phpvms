<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PirepResource\Pages;
use App\Filament\Resources\PirepResource\RelationManagers;
use App\Filament\Resources\PirepResource\Widgets\PirepStats;
use App\Models\Enums\FlightType;
use App\Models\Enums\PirepSource;
use App\Models\Enums\PirepState;
use App\Models\Pirep;
use App\Repositories\AirportRepository;
use App\Services\PirepService;
use App\Support\Units\Time;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Guava\FilamentClusters\Forms\Cluster;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PirepResource extends Resource
{
    protected static ?string $model = Pirep::class;
    protected static ?string $navigationGroup = 'Operations';
    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Pireps';

    protected static ?string $navigationIcon = 'heroicon-o-cloud-arrow-up';

    protected static ?string $recordTitleAttribute = 'ident';

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
                    ])->label('Flight Number/Route Code/Route Leg')->columnSpan(2),

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

                Forms\Components\Section::make('Flight Information')->schema([
                    Forms\Components\Grid::make('')->schema([
                        Forms\Components\Select::make('airline_id')
                            ->relationship('airline', 'name')
                            ->native(false),

                        Forms\Components\Select::make('aircraft_id')
                            ->relationship('aircraft', 'name')
                            ->native(false),

                        Forms\Components\Select::make('dpt_airport_id')
                            ->label('Departure Airport')
                            ->options(app(AirportRepository::class)->selectBoxList())
                            ->native(false),

                        Forms\Components\Select::make('arr_airport_id')
                            ->label('Arrival Airport')
                            ->options(app(AirportRepository::class)->selectBoxList())
                            ->native(false),

                        Cluster::make([
                            Forms\Components\TextInput::make('hours')
                                ->placeholder('hours')
                                ->formatStateUsing(fn (Pirep $record): int => $record->flight_time / 60),
                            Forms\Components\TextInput::make('minutes')
                                ->placeholder('minutes')
                                ->formatStateUsing(fn (Pirep $record): int => $record->flight_time % 60),
                        ])->label('Flight Time'),

                        Forms\Components\TextInput::make('block_fuel')
                            ->disabled(false),
                        Forms\Components\TextInput::make('fuel_used')
                            ->disabled(false),

                        Forms\Components\TextInput::make('level')
                            ->disabled(false),

                        Forms\Components\TextInput::make('distance')
                            ->disabled(false),

                        Forms\Components\TextInput::make('planned_distance')
                            ->disabled(false),
                    ])->columns(4)->columnSpan(2),

                    Forms\Components\Textarea::make('route')
                        ->disabled(false),

                    Forms\Components\RichEditor::make('notes')
                        ->disabled(false),
                ])
                    ->columns(2)
                    ->disabled(fn (Pirep $record): bool => $record->read_only),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ident')
                    ->label('Flight Ident')
                    ->searchable(['flight_number']),

                TextColumn::make('user.name')
                    ->label('Pilot')
                    ->searchable(),

                TextColumn::make('dpt_airport_id')
                    ->label('DEP')
                    ->searchable(),

                TextColumn::make('arr_airport_id')
                    ->label('ARR')
                    ->searchable(),

                TextColumn::make('flight_time')
                    ->formatStateUsing(fn (int $state): string => Time::minutesToTimeString($state)),

                TextColumn::make('aircraft')
                    ->formatStateUsing(fn (Pirep $record): string => $record->aircraft->registration.' - '.$record->aircraft->name),

                TextColumn::make('source')
                    ->label('Filed Using')->formatStateUsing(fn (int $state): string => PirepSource::label($state)),

                TextColumn::make('state')
                    ->badge()
                    ->color(fn (int $state): string => match ($state) {
                        PirepState::PENDING  => 'warning',
                        PirepState::ACCEPTED => 'success',
                        PirepState::REJECTED => 'danger',
                        default              => 'info',
                    })
                    ->formatStateUsing(fn (int $state): string => PirepState::label($state)),

                TextColumn::make('submitted_at')
                    ->dateTime('d-m-Y H:i')
                    ->label('File Date'),
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
            ->actions([
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

                Tables\Actions\DeleteAction::make(),
                Tables\Actions\ForceDeleteAction::make(),
                Tables\Actions\RestoreAction::make(),

                Action::make('view')
                    ->color('info')
                    ->icon('heroicon-m-eye')
                    ->label('View Pirep')
                    ->url(fn (Pirep $record): string => route('frontend.pireps.show', $record->id))
                    ->openUrlInNewTab(),
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
}
