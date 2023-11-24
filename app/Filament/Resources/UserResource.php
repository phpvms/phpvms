<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Filament\Resources\UserResource\Widgets\UserStats;
use App\Models\Airport;
use App\Models\Enums\UserState;
use App\Models\User;
use App\Support\Timezonelist;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use League\ISO3166\ISO3166;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationGroup = 'Operations';
    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Users';

    protected static ?string $navigationIcon = 'heroicon-o-user';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationBadge(): ?string
    {
        return User::where('state', UserState::PENDING)->count() > 0
            ? User::where('state', UserState::PENDING)->count()
            : null;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                ->schema([
                    Forms\Components\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\TextInput::make('id')
                            ->required()
                            ->numeric()
                            ->label('Pilot ID'),

                        Forms\Components\TextInput::make('callsign'),

                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->string(),

                        Forms\Components\TextInput::make('email')
                            ->required()
                            ->email(),

                        Forms\Components\TextInput::make('password')
                        ->password()
                        ->autocomplete('new-password')
                        ->columnSpanFull(),
                    ])
                    ->columns(2),
                    Forms\Components\Section::make('Location Information')
                    ->schema([
                        Forms\Components\Select::make('country')
                            ->required()
                            ->options(collect((new ISO3166())->all())->mapWithKeys(fn ($item, $key) => [strtolower($item['alpha2']) => str_replace('&bnsp;', ' ', $item['name'])]))
                            ->searchable()
                            ->native(false),

                        Forms\Components\Select::make('timezone')
                            ->options(Timezonelist::toArray())
                            ->searchable()
                            ->allowHtml()
                            ->native(false),

                        Forms\Components\Select::make('home_airport_id')
                            ->label('Home Airport')
                            ->relationship('home_airport', 'icao')
                            ->getOptionLabelFromRecordUsing(fn (Airport $record): string => $record->icao.' - '.$record->name)
                            ->searchable()
                            ->native(false),

                        Forms\Components\Select::make('current_airport_id')
                            ->label('Current Airport')
                            ->relationship('current_airport', 'icao')
                            ->getOptionLabelFromRecordUsing(fn (Airport $record): string => $record->icao.' - '.$record->name)
                            ->searchable()
                            ->native(false),
                    ])
                    ->columns(2),
                ])->columnSpan(['lg' => 2]),
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('User Information')
                        ->schema([
                            Forms\Components\Select::make('state')
                                ->options(UserState::labels())
                                ->searchable()
                                ->native(false),

                            Forms\Components\Select::make('airline_id')
                                ->relationship('airline', 'name')
                                ->searchable()
                                ->native(false),

                            Forms\Components\Select::make('rank_id')
                                ->relationship('rank', 'name')
                                ->searchable()
                                ->native(false),

                            Forms\Components\TextInput::make('transfer_time')
                                ->label('Transferred Hours')
                                ->numeric(),

                            Forms\Components\Select::make('roles')
                            ->label('Roles')
                            ->visible(Auth::user()?->can('update_role') ?? false)
                            ->relationship('roles', 'name')
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->multiple(),

                            Forms\Components\Textarea::make('notes')
                                ->label('Management Notes')
                                ->columnSpan('full'),
                        ])
                        ->columnSpan(['lg' => 1]),
                    ]),
            ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ident')
                    ->label('ID')
                    ->searchable(['pilot_id']),

                TextColumn::make('callsign')
                    ->searchable(),

                TextColumn::make('name')
                    ->searchable(),

                TextColumn::make('email')
                    ->searchable(),

                TextColumn::make('created_at')
                    ->label('Registered On')
                    ->dateTime('d-m-Y'),

                TextColumn::make('state')
                    ->badge()
                    ->color(fn (int $state): string => match ($state) {
                        UserState::PENDING => 'warning',
                        UserState::ACTIVE  => 'success',
                        default            => 'info',
                    })
                    ->formatStateUsing(fn (int $state): string => UserState::label($state)),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\SelectFilter::make('state')
                    ->options(UserState::labels()),
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
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\FieldsRelationManager::class,
            RelationManagers\AwardsRelationManager::class,
            RelationManagers\TypeRatingsRelationManager::class,
            RelationManagers\PirepsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'edit'  => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function getWidgets(): array
    {
        return [
            UserStats::class,
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
