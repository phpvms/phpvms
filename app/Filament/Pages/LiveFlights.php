<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\NavigationGroup;
use App\Enums\PirepPhase;
use App\Enums\PirepState;
use App\Filament\Concerns\AuthorizesAccess;
use App\Filament\Resources\Pireps\PirepResource;
use App\Models\Pirep;
use App\Models\PirepPosition;
use BackedEnum;
use Carbon\Carbon;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Override;
use UnitEnum;

/**
 * Live map membership, as an admin list.
 *
 * A PIREP is on the live map if and only if it holds a `pirep_positions` row —
 * that is what `Pirep::scopeOnLiveMap()` encodes, and this page consumes the
 * same scope so the page and the public map can never disagree about who is
 * live. The only inversion is the ordering: the scope sorts freshest-first for
 * the map, this page sorts stalest-first for triage.
 *
 * A flight joins the map at prefile, not at its first position report —
 * `PirepService::prefile()` opens the position row with `phase = SCHEDULED`, so
 * `created_at` means prefile time and a never-departed flight has
 * `updated_at == created_at`.
 */
class LiveFlights extends Page implements HasTable
{
    use AuthorizesAccess;
    use InteractsWithTable;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Operations;

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Phosphor::BroadcastLight;

    #[Override]
    public static function getNavigationLabel(): string
    {
        return __('filament.live_flights.navigation_label');
    }

    /**
     * How many flights are on the map right now, from the same scope the page
     * and the public map read — so the badge cannot disagree with either.
     * The scope's eager loads cost nothing here; Eloquent only runs them for
     * a get(), not a count().
     */
    #[Override]
    public static function getNavigationBadge(): ?string
    {
        $live = Pirep::onLiveMap()->count();

        return $live > 0 ? (string) $live : null;
    }

    #[Override]
    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('filament.live_flights.badge.navigation_tooltip');
    }

    #[Override]
    public function getTitle(): string
    {
        return __('filament.live_flights.page_title');
    }

    #[Override]
    public function getSubheading(): ?string
    {
        return __('filament.live_flights.page_subtitle');
    }

    #[Override]
    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('filament.live_flights.section_title'))
                ->description(__('filament.live_flights.section_description'))
                ->icon(Phosphor::BroadcastLight)
                ->collapsible()
                ->persistCollapsed()
                // Scopes the theme rule that flattens the embedded table's own
                // `.fi-ta-ctn` card, so this Section is the only card on the
                // page and the table's paginator closes it as its footer.
                ->extraAttributes(['class' => 'live-flights'])
                ->schema([
                    EmbeddedTable::make(),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            // The scope IS the definition of map membership; a second query
            // would be a second definition, free to drift.
            ->query(fn (): Builder => Pirep::onLiveMap()->reorder('pirep_positions.updated_at', 'asc'))
            ->columns([
                // Horizontal card: identity on the left, the triage fields
                // running across, badges closing the row.
                Split::make([
                    Stack::make([
                        TextColumn::make('ident')
                            ->weight(FontWeight::Bold)
                            ->size(TextSize::Large)
                            ->state(fn (Pirep $record): string => $record->ident),

                        TextColumn::make('route')
                            ->icon(Phosphor::MapPinLight)
                            ->state(fn (Pirep $record): string => $record->dpt_airport_id.' → '.$record->arr_airport_id),
                    ])->space(1),

                    TextColumn::make('aircraft')
                        ->icon(Phosphor::AirplaneLight)
                        ->placeholder('—')
                        ->state(fn (Pirep $record): ?string => $record->aircraft?->ident),

                    // The primary triage signal, not decoration: phase read
                    // against last-seen is how a ghost is told from a flight.
                    TextColumn::make('phase')
                        ->badge()
                        ->color('info')
                        ->placeholder('—')
                        // `pirep_positions.phase` is deliberately uncast — it is
                        // an open vocabulary from the client — so an unknown
                        // code falls through as-is rather than throwing.
                        ->state(function (Pirep $record): ?string {
                            $phase = $record->position?->phase;

                            return $phase === null
                                ? null
                                : (PirepPhase::tryFrom($phase)?->getLabel() ?? $phase);
                        }),

                    TextColumn::make('last_seen')
                        ->icon(Phosphor::ClockLight)
                        ->placeholder('—')
                        // pirep_positions columns are not in the result set —
                        // the scope selects pireps.* — so both timestamps are
                        // read through the eager-loaded `position` relation.
                        ->state(fn (Pirep $record): ?string => $record->position?->updated_at?->diffForHumans())
                        ->tooltip(fn (Pirep $record): ?string => $record->position?->updated_at?->toDayDateTimeString())
                        ->description(__('filament.live_flights.last_seen'), 'above'),

                    TextColumn::make('on_map_since')
                        ->icon(Phosphor::AirplaneTakeoffLight)
                        ->placeholder('—')
                        ->state(fn (Pirep $record): ?string => $record->position?->created_at?->diffForHumans())
                        ->tooltip(fn (Pirep $record): ?string => $record->position?->created_at?->toDayDateTimeString())
                        ->description(__('filament.live_flights.on_map_since'), 'above'),

                    Stack::make([
                        TextColumn::make('stale')
                            ->badge()
                            ->color('warning')
                            ->icon(Phosphor::WarningLight)
                            ->placeholder('')
                            ->state(fn (Pirep $record): ?string => $this->isStale($record)
                                ? __('filament.live_flights.badge.stale')
                                : null),

                        // Distinct from stale: "landed and never filed" resolves
                        // by opening the PIREP, not by purging a dead client.
                        TextColumn::make('unfiled')
                            ->badge()
                            ->color('danger')
                            ->icon(Phosphor::CheckCircleLight)
                            ->placeholder('')
                            ->state(fn (Pirep $record): ?string => $this->isFinishedButUnfiled($record)
                                ? __('filament.live_flights.badge.unfiled')
                                : null),
                    ])->space(1),
                ]),
            ])
            // One card per row: the card is horizontal, so it takes the width.
            ->contentGrid(['default' => 1])
            ->recordUrl(fn (Pirep $record): string => PirepResource::getUrl('view', ['record' => $record->id]))
            ->recordActions([
                Action::make('purge')
                    ->label(__('filament.live_flights.purge.label'))
                    ->tooltip(__('filament.live_flights.purge.tooltip'))
                    ->icon(Phosphor::TrashSimpleLight)
                    ->color('danger')
                    ->link()
                    // No confirmation: the flight record is untouched, and the
                    // five-minutely cron performs the same delete unattended.
                    ->action(function (Pirep $record): void {
                        PirepPosition::where('pirep_id', $record->id)->delete();

                        Notification::make()
                            ->title(__('filament.live_flights.purge.notification', ['ident' => $record->ident]))
                            ->body(__('filament.live_flights.purge.tooltip'))
                            ->success()
                            ->send();
                    }),
            ])
            ->poll('30s')
            ->emptyStateIcon(Phosphor::BroadcastLight)
            ->emptyStateHeading(__('filament.live_flights.empty.heading'))
            ->emptyStateDescription(__('filament.live_flights.empty.description'));
    }

    /**
     * Past `livemap.idle_time` — the same clock `PirepPositionExpiration` runs
     * on, so the badge and the cron cannot disagree about the threshold.
     */
    private function isStale(Pirep $record): bool
    {
        $idleTime = (int) setting('livemap.idle_time');
        $lastSeen = $record->position?->updated_at;

        if ($idleTime <= 0 || $lastSeen === null) {
            return false;
        }

        return $lastSeen->lt(Carbon::now('UTC')->subMinutes($idleTime));
    }

    /**
     * Flew the whole flight, never filed it: the PIREP is still IN_PROGRESS
     * while the position row reports a terminal phase.
     */
    private function isFinishedButUnfiled(Pirep $record): bool
    {
        return $record->state === PirepState::IN_PROGRESS
            && in_array($record->position?->phase, [
                PirepPhase::ARRIVED->value,
                PirepPhase::ON_BLOCK->value,
                PirepPhase::LANDED->value,
            ], true);
    }
}
