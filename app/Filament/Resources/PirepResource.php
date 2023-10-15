<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PirepResource\Pages;
use App\Filament\Resources\PirepResource\Widgets\PirepStats;
use App\Models\Enums\PirepSource;
use App\Models\Enums\PirepState;
use App\Models\Pirep;
use App\Repositories\UserRepository;
use App\Services\PirepService;
use App\Support\Units\Time;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
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
        $userRepo = app(UserRepository::class);
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Pirep sender')->schema([
                            Forms\Components\Placeholder::make('user'),
                        ]),
                        Forms\Components\Section::make('Basic Information')
                            ->schema([
                                Forms\Components\TextInput::make('ident')
                                    ->required()
                                    ->label('Flight Ident'),
                                Forms\Components\TextInput::make('flight_number')
                                    ->required()
                                    ->label('Flight Number'),
                                Forms\Components\TextInput::make('aircraft_id')
                                    ->required()
                                    ->label('Aircraft ID'),
                                Forms\Components\TextInput::make('dpt_airport_id')
                                    ->required()
                                    ->label('Departure Airport ID'),
                                Forms\Components\TextInput::make('arr_airport_id')
                                    ->required()
                                    ->label('Arrival Airport ID'),
                                Forms\Components\TextInput::make('route')
                                    ->label('Route'),
                                Forms\Components\TextInput::make('notes')
                                    ->label('Notes'),
                                Forms\Components\TextInput::make('flight_time')
                                    ->required()
                                    ->label('Flight Time'),
                                Forms\Components\TextInput::make('block_time')
                                    ->required()
                                    ->label('Block Time'),
                                Forms\Components\TextInput::make('fuel_used')
                                    ->required()
                                    ->label('Fuel Used'),
                                Forms\Components\TextInput::make('fuel_unit')
                                    ->required()
                                    ->label('Fuel Unit'),
                                Forms\Components\TextInput::make('source')
                                    ->required()
                                    ->label('Source'),
                                Forms\Components\TextInput::make('state')
                                    ->required()
                                    ->label('State'),
                                Forms\Components\TextInput::make('status')
                                    ->required()
                                    ->label('Status'),
                                Forms\Components\TextInput::make('raw_data')
                                    ->required()
                                    ->label('Raw Data'),
                                Forms\Components\TextInput::make('route_code')
                                    ->required()
                                    ->label('Route Code'),
                                Forms\Components\TextInput::make('route_leg')
                                    ->required()
                                    ->label('Route Leg'),
                                Forms\Components\TextInput::make('distance')
                                    ->required()
                                    ->label('Distance'),
                                Forms\Components\TextInput::make('flight_type')
                                    ->required()
                                    ->label('Flight Type'),
                                Forms\Components\TextInput::make('planned_distance')
                                    ->required()
                                    ->label('Planned Distance'),
                                Forms\Components\TextInput::make('planned_flight_time')
                                    ->required(),
                            ]),
                    ])]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ident')->label('Flight Ident')->searchable(),
                TextColumn::make('user.name')->label('Pilot')->searchable(),
                TextColumn::make('dpt_airport_id')->label('DEP')->searchable(),
                TextColumn::make('arr_airport_id')->label('ARR')->searchable(),
                TextColumn::make('flight_time')->formatStateUsing(fn (int $state): string => Time::minutesToTimeString($state)),
                TextColumn::make('aircraft')->formatStateUsing(fn (Pirep $record): string => $record->aircraft->registration.' - '.$record->aircraft->name),
                TextColumn::make('source')->label('Filed Using')->formatStateUsing(fn (int $state): string => PirepSource::label($state)),
                TextColumn::make('state')->badge()->color(fn (int $state): string => match ($state) {
                    PirepState::PENDING  => 'warning',
                    PirepState::ACCEPTED => 'success',
                    PirepState::REJECTED => 'danger',
                    default              => 'info',
                })->formatStateUsing(fn (int $state): string => PirepState::label($state)),
                TextColumn::make('submitted_at')->dateTime('d-m-Y H:i')->label('File Date'),
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
                                $data['filed_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('submitted_at', '>=', $date),
                            )
                            ->when(
                                $data['filed_until'],
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
                    ->action(fn (Pirep $record) => app(PirepService::class)->changeState($record, PirepState::ACCEPTED)),

                Action::make('reject')
                    ->color('danger')
                    ->icon('heroicon-m-x-circle')
                    ->label('Reject')
                    ->visible(fn (Pirep $record): bool => ($record->state === PirepState::PENDING || $record->state === PirepState::ACCEPTED))
                    ->action(fn (Pirep $record) => app(PirepService::class)->changeState($record, PirepState::REJECTED)),

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
            ])
            ->emptyStateActions([]);
    }

    public static function getRelations(): array
    {
        return [
            //
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
