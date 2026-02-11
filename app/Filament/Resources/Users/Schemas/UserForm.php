<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\Airport;
use App\Models\Enums\UserState;
use App\Support\Timezonelist;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use League\ISO3166\ISO3166;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Section::make(__('filament.basic_informations'))
                            ->schema([
                                TextInput::make('pilot_id')
                                    ->required()
                                    ->numeric()
                                    ->unique()
                                    ->label(__('common.pilot_id')),

                                TextInput::make('callsign')
                                    ->label(__('flights.callsign')),

                                TextInput::make('name')
                                    ->label(__('common.name'))
                                    ->required()
                                    ->string(),

                                TextInput::make('email')
                                    ->label(__('common.email'))
                                    ->unique()
                                    ->required()
                                    ->email(),

                                TextInput::make('password')
                                    ->label(__('auth.password'))
                                    ->required(fn (string $operation) => $operation === 'create')
                                    ->password()
                                    ->autocomplete('new-password')
                                    ->columnSpanFull(),
                            ])
                            ->columnSpanFull()
                            ->columns(),
                        Section::make(__('filament.location_informations'))
                            ->schema([
                                Select::make('country')
                                    ->label(__('common.country'))
                                    ->options(collect((new ISO3166())->all())->mapWithKeys(fn (array $item, string $key) => [strtolower($item['alpha2']) => str_replace('&bnsp;', ' ', $item['name'])]))
                                    ->searchable()
                                    ->native(false),

                                Select::make('timezone')
                                    ->label(__('common.timezone'))
                                    ->options(Timezonelist::toArray())
                                    ->searchable()
                                    ->allowHtml()
                                    ->native(false),

                                Select::make('home_airport_id')
                                    ->label(__('airports.home'))
                                    ->relationship('home_airport', 'icao')
                                    ->getOptionLabelFromRecordUsing(fn (Airport $record): string => $record->icao.' - '.$record->name)
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->native(false),

                                Select::make('current_airport_id')
                                    ->label(__('airports.current'))
                                    ->relationship('current_airport', 'icao')
                                    ->getOptionLabelFromRecordUsing(fn (Airport $record): string => $record->icao.' - '.$record->name)
                                    ->searchable()
                                    ->preload()
                                    ->native(false),
                            ])
                            ->columnSpanFull()
                            ->columns(),
                    ])->columnSpan(['lg' => 2]),
                Section::make(__('filament.user_informations'))
                    ->schema([
                        Select::make('state')
                            ->label(__('common.state'))
                            ->required()
                            ->options(UserState::labels())
                            ->searchable()
                            ->native(false),

                        Select::make('airline_id')
                            ->label(__('common.airline'))
                            ->relationship('airline', 'name')
                            ->preload()
                            ->searchable()
                            ->required()
                            ->native(false),

                        Select::make('rank_id')
                            ->label(__('common.rank'))
                            ->relationship('rank', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false),

                        TextInput::make('transfer_time')
                            ->label(__('profile.transferhours'))
                            ->numeric(),

                        Select::make('roles')
                            ->label(trans_choice('common.role', 2))
                            ->visible(Auth::user()?->hasRole('super_admin') ?? false)
                            ->relationship('roles', 'name')
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->multiple(),

                        Textarea::make('notes')
                            ->label(__('common.notes'))
                            ->autosize()
                            ->columnSpanFull(),
                    ])
                    ->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }
}
