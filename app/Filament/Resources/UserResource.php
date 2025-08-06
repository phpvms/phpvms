<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Filament\Resources\UserResource\RelationManagers\AwardsRelationManager;
use App\Filament\Resources\UserResource\RelationManagers\FieldsRelationManager;
use App\Filament\Resources\UserResource\RelationManagers\PirepsRelationManager;
use App\Filament\Resources\UserResource\RelationManagers\TypeRatingsRelationManager;
use App\Filament\Resources\UserResource\Widgets\UserStats;
use App\Models\Airport;
use App\Models\Enums\UserState;
use App\Models\User;
use App\Support\Timezonelist;
use Filament\Actions\BulkActionGroup;
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
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use League\ISO3166\ISO3166;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Users';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationBadge(): ?string
    {
        return User::where('state', UserState::PENDING)->count() > 0
            ? User::where('state', UserState::PENDING)->count()
            : null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Section::make('Basic Information')
                            ->schema([
                                TextInput::make('pilot_id')
                                    ->required()
                                    ->numeric()
                                    ->label('Pilot ID'),

                                TextInput::make('callsign'),

                                TextInput::make('name')
                                    ->required()
                                    ->string(),

                                TextInput::make('email')
                                    ->required()
                                    ->email(),

                                TextInput::make('password')
                                    ->required(fn (string $operation) => $operation === 'create')
                                    ->password()
                                    ->autocomplete('new-password')
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),
                        Section::make('Location Information')
                            ->schema([
                                Select::make('country')
                                    ->required()
                                    ->options(collect((new ISO3166())->all())->mapWithKeys(fn ($item, $key) => [strtolower($item['alpha2']) => str_replace('&bnsp;', ' ', $item['name'])]))
                                    ->searchable()
                                    ->native(false),

                                Select::make('timezone')
                                    ->options(Timezonelist::toArray())
                                    ->searchable()
                                    ->allowHtml()
                                    ->required(fn (string $operation) => $operation === 'create')
                                    ->native(false),

                                Select::make('home_airport_id')
                                    ->label('Home Airport')
                                    ->relationship('home_airport', 'icao')
                                    ->getOptionLabelFromRecordUsing(fn (Airport $record): string => $record->icao.' - '.$record->name)
                                    ->searchable()
                                    ->required(fn (string $operation) => $operation === 'create')
                                    ->native(false),

                                Select::make('current_airport_id')
                                    ->label('Current Airport')
                                    ->relationship('current_airport', 'icao')
                                    ->getOptionLabelFromRecordUsing(fn (Airport $record): string => $record->icao.' - '.$record->name)
                                    ->searchable()
                                    ->native(false),
                            ])
                            ->columns(2),
                    ])->columnSpan(['lg' => 2]),
                Group::make()
                    ->schema([
                        Section::make('User Information')
                            ->schema([
                                Select::make('state')
                                    ->options(UserState::labels())
                                    ->searchable()
                                    ->native(false),

                                Select::make('airline_id')
                                    ->relationship('airline', 'name')
                                    ->searchable()
                                    ->required(fn (string $operation) => $operation === 'create')
                                    ->native(false),

                                Select::make('rank_id')
                                    ->relationship('rank', 'name')
                                    ->searchable()
                                    ->native(false),

                                TextInput::make('transfer_time')
                                    ->label('Transferred Hours')
                                    ->numeric(),

                                Select::make('roles')
                                    ->label('Roles')
                                    ->visible(Auth::user()?->hasRole('super_admin') ?? false)
                                    ->relationship('roles', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->multiple(),

                                RichEditor::make('notes')
                                    ->label('Management Notes')
                                    ->columnSpanFull(),
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
                    ->searchable(['pilot_id'])
                    ->sortable(),

                TextColumn::make('callsign')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Registered On')
                    ->dateTime('d-m-Y')
                    ->sortable(),

                TextColumn::make('state')
                    ->badge()
                    ->color(fn (int $state): string => match ($state) {
                        UserState::PENDING => 'warning',
                        UserState::ACTIVE  => 'success',
                        default            => 'info',
                    })
                    ->formatStateUsing(fn (int $state): string => UserState::label($state))
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TrashedFilter::make(),
                SelectFilter::make('state')
                    ->options(UserState::labels()),
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
            ]);
    }

    public static function getRelations(): array
    {
        return [
            FieldsRelationManager::class,
            AwardsRelationManager::class,
            TypeRatingsRelationManager::class,
            PirepsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit'   => EditUser::route('/{record}/edit'),
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
