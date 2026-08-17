<?php

namespace App\Filament\Resources\Pireps\Tables;

use App\Enums\PirepState;
use App\Filament\Resources\Pireps\PirepResource;
use App\Filament\Resources\Subfleets\Resources\Aircraft\AircraftResource;
use App\Filament\Widgets\ActivityCalendarWidget;
use App\Models\Airport;
use App\Models\Pirep;
use App\Support\Units\Time;
use Filafly\Icons\Phosphor\Enums\Phosphor;
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
use Filament\Forms\Components\DateTimePicker;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

/**
 * Table configuration for the PIREP list page.
 */
class PirepsTable
{
    public static function configure(Table $table): Table
    {
        return $table->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
            'airline',
            'aircraft',
            'user',
            'dpt_airport:id,icao,name',
            'arr_airport:id,icao,name',
        ])
            ->whereNotIn('state', [PirepState::DRAFT, PirepState::IN_PROGRESS, PirepState::CANCELLED]))
            ->columns([
                TextColumn::make('ident')
                    ->label(trans_choice('common.flight', 1))
                    ->fontFamily(FontFamily::Mono)
                    ->url(fn (Pirep $record): string => PirepResource::getUrl('view', ['record' => $record]))
                    ->sortable(['flight_number'])
                    ->icon(fn (Pirep $record): Phosphor => match ($record->state) {
                        PirepState::ACCEPTED => Phosphor::CheckFat,
                        PirepState::REJECTED => Phosphor::XBold,
                        default              => Phosphor::SealQuestionBold
                    })
                    ->iconColor(fn (Pirep $record): string => match ($record->state) {
                        PirepState::ACCEPTED => 'success',
                        PirepState::REJECTED => 'danger',
                        default              => 'info'
                    }),

                /*TextColumn::make('state')
                    ->label(__('common.state'))
                    ->badge()
                    ->sortable()
                    ->alignCenter(),*/

                TextColumn::make('user.name')
                    ->label(trans_choice('common.pilot', 1))
                    ->sortable(),

                TextColumn::make('route')
                    ->label(__('flights.route'))
                    ->fontFamily(FontFamily::Mono)
                    ->state(fn (Pirep $record): string => trim($record->dpt_airport_id.' → '.$record->arr_airport_id)),

                TextColumn::make('aircraft.registration')
                    ->label(__('pireps.tail'))
                    ->fontFamily(FontFamily::Mono)
                    // Tail and type read as one fact about the airframe, so they
                    // share a column: "N854AL (B738)".
                    ->state(function (Pirep $record): string {
                        $aircraft = $record->aircraft;

                        if ($aircraft === null) {
                            return '';
                        }

                        return filled($aircraft->icao)
                            ? "{$aircraft->registration} ({$aircraft->icao})"
                            : (string) $aircraft->registration;
                    })
                    // AircraftResource declares $parentResource = SubfleetResource,
                    // so its routes are nested and the subfleet segment is part of
                    // the URL, not an extra lookup: subfleet_id is a column on the
                    // aircraft row this table already eager-loads, so reading it
                    // here costs no query.
                    ->url(fn (Pirep $record): ?string => $record->aircraft === null ? null : AircraftResource::getUrl('edit', [
                        'subfleet' => $record->aircraft->subfleet_id,
                        'record'   => $record->aircraft,
                    ]))
                    ->alignCenter(),

                TextColumn::make('flight_time')
                    ->label(__('pireps.block'))
                    ->fontFamily(FontFamily::Mono)
                    ->alignCenter()
                    ->formatStateUsing(function (?int $state): string {
                        $hm = Time::minutesToTimeParts($state ?? 0);

                        return sprintf('%d:%02d', $hm['h'], $hm['m']);
                    }),

                TextColumn::make('score')
                    ->label(__('pireps.score'))
                    ->fontFamily(FontFamily::Mono)
                    ->alignCenter()
                    // ->formatStateUsing(fn (int|float|null $state): string => $state !== null && (int) $state !== 0 ? number_format((float) $state) : '—')
                    ->tooltip(fn (int|float|null $state): ?string => $state !== null && (int) $state !== 0
                        ? number_format((float) $state).' fpm'
                        : null)
                    ->color(fn (int|float|null $state): ?string => match (true) {
                        $state === null || (int) $state === 0 => 'gray',
                        $state > 90                           => 'success',
                        $state >= 80                          => 'warning',
                        $state < 80                           => 'danger',
                        default                               => null,
                    }),

                TextColumn::make('submitted_at')
                    ->label(__('pireps.filed'))
                    ->fontFamily(FontFamily::Mono)
                    ->alignEnd()
                    ->dateTime('j M')
                    ->dateTimeTooltip()
                    ->sortable(),
            ])
            ->paginated([25])
            ->defaultPaginationPageOption(25)
            ->defaultSort('submitted_at', 'desc')
            // Row click opens the report, not the edit form (Filament's
            // default recordUrl prefers the edit page when one exists).
            ->recordUrl(fn (Pirep $record): string => PirepResource::getUrl('view', ['record' => $record]))
            ->filters([
                SelectFilter::make('state')
                    ->label(__('common.state'))
                    ->options(
                        collect(PirepState::cases())->reject(fn (PirepState $state): bool => in_array(
                            $state,
                            [PirepState::DRAFT, PirepState::IN_PROGRESS, PirepState::CANCELLED],
                            true,
                        ))
                            ->mapWithKeys(fn (PirepState $state): array => [$state->value => $state->getLabel()])
                            ->all(),
                    ),

                SelectFilter::make('airline')
                    ->relationship('airline', 'name')
                    ->label(__('common.airline'))
                    ->searchable()
                    ->preload(),

                SelectFilter::make('user')
                    ->relationship('user', 'name')
                    ->label(trans_choice('common.user', 1))
                    ->searchable()
                    ->preload(),

                SelectFilter::make('dpt_airport')
                    ->label(__('airports.departure'))
                    ->relationship('dpt_airport', 'icao')
                    ->getOptionLabelFromRecordUsing(fn (Airport $record): string => $record->icao.' - '.$record->name)
                    ->searchable()
                    ->preload(),

                SelectFilter::make('arr_airport')
                    ->label(__('airports.arrival'))
                    ->relationship('arr_airport', 'icao')
                    ->getOptionLabelFromRecordUsing(fn (Airport $record): string => $record->icao.' - '.$record->name)
                    ->searchable()
                    ->preload(),

                Filter::make('submitted_at')
                    ->schema([
                        DatePicker::make('filed_after')
                            ->label(__('filament.filed_after')),
                        DatePicker::make('filed_before')
                            ->label(__('filament.filed_before')),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        isset($data['filed_after']) && $data['filed_after'],
                        fn (Builder $query): Builder => $query->whereDate('submitted_at', '>=', $data['filed_after']),
                    )
                        ->when(
                            isset($data['filed_before']) && $data['filed_before'],
                            fn (Builder $query): Builder => $query->whereDate(
                                'submitted_at',
                                '<=',
                                $data['filed_before'],
                            ),
                        )),
                TrashedFilter::make(),

                // Deep-linked from the dashboard activity calendar: /admin/pireps?departed_from=...&departed_to=...
                Filter::make('departed')
                    ->label(__('filament.departed_between'))
                    ->schema([
                        DateTimePicker::make('from')
                            ->label(__('filament.departed_from')),
                        DateTimePicker::make('to')
                            ->label(__('filament.departed_to')),
                    ])
                    // Same expression the activity calendar buckets on, so a
                    // box there and the list it deep-links to select the same
                    // rows. block_off_time alone would drop every PIREP that
                    // never got one from ACARS.
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        filled($data['from'] ?? null),
                        fn (Builder $query): Builder => $query->whereRaw(ActivityCalendarWidget::ACTIVITY_AT.' >= ?', [
                            $data['from'],
                        ]),
                    )
                        ->when(
                            filled($data['to'] ?? null),
                            fn (Builder $query): Builder => $query->whereRaw(ActivityCalendarWidget::ACTIVITY_AT
                            .' <= ?', [$data['to']]),
                        )),
            ])
            // No funnel/dropdown: the quick bar below and the always-visible
            // filters card in the page's context column are the filter UI.
            ->filtersLayout(FiltersLayout::Hidden)
            ->deferFilters(false)
            ->filtersFormColumns(1)
            ->persistFiltersInSession()
            // Mockup's inline quick-filter bar (pireps.html:426-465), top of
            // the table panel: state + airline selects bound straight onto
            // tableFilters, and the page-of-total count.
            ->header(fn (Table $table): Factory|View => view('filament.pireps.partials.list-filter-bar', [
                'records' => $table->getLivewire()->getTableRecords(),
            ]))
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make(),
                    ForceDeleteAction::make(),
                    RestoreAction::make(),
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
}
