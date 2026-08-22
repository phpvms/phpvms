<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserState;
use App\Filament\Forms\Components\AirportSelect;
use App\Models\Airline;
use App\Models\Role;
use App\Models\User;
use App\Support\Timezonelist;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Unique;
use League\ISO3166\ISO3166;

class UserForm
{
    public static function configure(Schema $schema, bool $withIdentity = true): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament.user_information'))
                    ->id('user-information')
                    ->icon(Phosphor::UserLight)
                    ->collapsible()
                    ->persistCollapsed()
                    // The footer renders the toggle on its own ruled row above
                    // the submit row, per the enable-toggle house pattern.
                    ->footer([
                        Toggle::make('auto_accept_pireps')
                            ->inline()
                            ->label(__('filament.user_auto_accept_pireps'))
                            ->helperText(__('filament.user_auto_accept_pireps_hint')),
                    ])
                    ->schema([
                        // On the edit page these live in the overview's drawer
                        // instead; the create page still needs them inline,
                        // because there is no overview to edit yet.
                        ...($withIdentity ? self::identityFields() : []),

                        Select::make('state')
                            ->label(__('common.state'))
                            ->required()
                            ->options(UserState::class)
                            ->searchable()
                            ->native(false),

                        Select::make('rank_id')
                            ->label(__('common.rank'))
                            ->relationship('rank', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false),

                        TextInput::make('transfer_time')
                            ->label(__('profile.transfer_hours'))
                            ->numeric(),

                        Select::make('roles')
                            ->label(trans_choice('common.role', 2))
                            ->visible(Auth::user()?->hasRole(Role::superAdminName()) ?? false)
                            ->relationship('roles', 'name')
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->multiple(),

                        // Nested Sections render as sequent head bands inside
                        // the same card, not as second and third cards.
                        Section::make(__('filament.location_information'))
                            ->id('location-information')
                            ->icon(Phosphor::MapPinLight)
                            ->collapsible()
                            ->persistCollapsed()
                            ->columns(2)
                            ->schema([
                                Select::make('country')
                                    ->label(__('common.country'))
                                    ->options(collect(new ISO3166()->all())->mapWithKeys(fn (array $item, string $key): array => [strtolower($item['alpha2']) => str_replace('&bnsp;', ' ', $item['name'])]))
                                    ->searchable()
                                    ->native(false),

                                Select::make('timezone')
                                    ->label(__('common.timezone'))
                                    ->options(Timezonelist::toArray())
                                    ->searchable()
                                    ->allowHtml()
                                    ->native(false),

                                AirportSelect::make('home_airport_id')
                                    ->label(__('airports.home'))
                                    ->airportRelationship('home_airport')
                                    ->required(),

                                AirportSelect::make('current_airport_id')
                                    ->label(__('airports.current'))
                                    ->airportRelationship('current_airport'),
                            ])
                            ->columnSpanFull(),

                        Section::make(__('common.notes'))
                            ->icon(Phosphor::NoteLight)
                            ->collapsible()
                            ->persistCollapsed()
                            ->schema([
                                Textarea::make('notes')
                                    ->hiddenLabel()
                                    // Floor the height: an empty autosized
                                    // textarea collapses to a sliver that
                                    // does not read as an input at all.
                                    ->rows(4)
                                    ->autosize()
                                    ->columnSpanFull(),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
            ])
            ->columns(3);
    }

    /**
     * Who the pilot is: the fields the ident, the ATC callsign and the login are
     * built from. Shown in the edit page's overview and edited through its
     * drawer, so the two places that render them stay in step.
     *
     * Every element is a Field, not just a Component -- EditUser maps over these
     * calling getName(), which only Field has.
     *
     * They are `live` for the drawer's preview strip (identityPreview()), which
     * recomputes the ident from state rather than from the saved record.
     *
     * @return array<int, Field>
     */
    public static function identityFields(): array
    {
        return [
            TextInput::make('pilot_id')
                ->label(__('common.pilot_id'))
                ->required()
                ->numeric()
                ->unique(modifyRuleUsing: fn (Unique $rule): Unique => setting('pilots.id_reuse_deleted')
                    ? $rule->whereNull('deleted_at')
                    : $rule)
                ->live(debounce: 500)
                ->hint(fn (int|string|null $state): ?string => self::pilotIdRangeHint($state))
                ->hintColor('warning'),

            TextInput::make('callsign')
                ->label(__('filament.user_atc_callsign'))
                ->live(debounce: 500),

            TextInput::make('name')
                ->label(__('common.name'))
                ->required()
                ->string()
                ->live(debounce: 500),

            TextInput::make('email')
                ->label(__('common.email'))
                ->unique()
                ->required()
                ->email(),

            Select::make('airline_id')
                ->label(__('common.airline'))
                ->relationship('airline', 'name')
                ->preload()
                ->searchable()
                ->required()
                ->live()
                ->native(false),

            TextInput::make('password')
                ->label(__('auth.password'))
                ->required(fn (string $operation): bool => $operation === 'create')
                ->password()
                ->autocomplete('new-password')
                ->columnSpanFull(),
        ];
    }

    /**
     * What the identity fields currently add up to, read from live form state.
     *
     * The ident and the ATC callsign are assembled from the pilot ID, the
     * callsign and the airline's ICAO (User::ident() and User::atc()), so an
     * admin editing any of the three cannot tell what they are producing
     * without seeing it. The airline is read from state, not from the record,
     * because the drawer can change it.
     *
     * Laid out as a strip so it reads as a header above the drawer's fields
     * rather than as three more of them.
     */
    public static function identityPreview(): Grid
    {
        return Grid::make(3)->columnSpanFull()->schema([
            Callout::make(__('filament.user_ident_preview_notice'))
                ->info()
                ->columnSpanFull(),

            TextEntry::make('ident_preview')
                ->label(__('filament.user_ident'))
                ->state(fn (Get $get): string => User::formatIdent(self::selectedAirline($get), $get('pilot_id')))
                ->helperText(fn (Get $get): ?string => self::selectedAirline($get)?->name),

            TextEntry::make('name_preview')
                ->label(__('common.name'))
                ->state(fn (Get $get): string => (string) $get('name'))
                ->helperText(fn (?User $record): ?string => $record?->rank?->name),

            TextEntry::make('atc_preview')
                ->label(__('filament.user_atc_callsign'))
                ->state(fn (Get $get): string => User::formatAtc(
                    self::selectedAirline($get),
                    $get('pilot_id'),
                    $get('callsign'),
                )),
        ]);
    }

    /**
     * Non-blocking warning shown when the entered pilot ID falls outside an
     * enabled `pilots.id_range_*` range. Returns null when the range is
     * disabled, the value is blank, or the value is in range.
     */
    public static function pilotIdRangeHint(int|string|null $pilotId): ?string
    {
        if (!setting('pilots.id_range_enabled') || $pilotId === null || $pilotId === '') {
            return null;
        }

        $start = (int) setting('pilots.id_range_start');
        $end = (int) setting('pilots.id_range_end');

        if ((int) $pilotId >= $start && (int) $pilotId <= $end) {
            return null;
        }

        return __('filament.pilot_id_out_of_range_hint', ['start' => $start, 'end' => $end]);
    }

    private static function selectedAirline(Get $get): ?Airline
    {
        $airlineId = $get('airline_id');

        return blank($airlineId) ? null : Airline::find($airlineId);
    }
}
