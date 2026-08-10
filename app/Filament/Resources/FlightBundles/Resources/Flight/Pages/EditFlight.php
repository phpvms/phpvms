<?php

namespace App\Filament\Resources\FlightBundles\Resources\Flight\Pages;

use App\Filament\Actions\EditDetailsAction;
use App\Filament\Concerns\ReversePrimaryButtons;
use App\Filament\Concerns\StacksRelationManagers;
use App\Filament\Resources\FlightBundles\Resources\Flight\FlightResource;
use App\Filament\Resources\FlightBundles\Resources\Flight\Schemas\FlightForm;
use App\Models\Flight;
use App\Support\Days;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Field;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\HtmlString;
use Override;

class EditFlight extends EditRecord
{
    use ReversePrimaryButtons;
    use StacksRelationManagers;

    protected static string $resource = FlightResource::class;

    /**
     * Keep the form's action row (Save/Cancel) anchored to the right edge
     * instead of Filament's default left-aligned start. Alignment::End maps to
     * flex-row-reverse, which would visually flip the Cancel-then-Save order
     * set by ReversePrimaryButtons — a plain justify-end keeps the order and
     * pushes the row right.
     */
    #[Override]
    public function getFormActionsAlignment(): string|Alignment
    {
        return 'justify-end';
    }

    /**
     * The page uses the panel's standard hero header. The heading carries the
     * flight ident and its route; everything else the mockup puts in the page
     * head rides in the subheading, so the band matches the other pages.
     */
    #[Override]
    public function getHeading(): string|Htmlable
    {
        /** @var Flight $record */
        $record = $this->getRecord();

        return new HtmlString(sprintf(
            '%s <span class="id fi-header-heading-route">%s → %s</span>',
            e($record->ident),
            e($record->dpt_airport->icao),
            e($record->arr_airport->icao),
        ));
    }

    #[Override]
    public function getSubheading(): string|Htmlable|null
    {
        /** @var Flight $record */
        $record = $this->getRecord();
        [$statusLabel, $statusColor] = FlightForm::flightStatusBadge($record);

        return view('filament.shared.hero-subheading', [
            'meta' => implode(' · ', array_filter([
                $record->airline?->name,
                $record->flight_type?->getLabel(),
                self::frequencyLabel($record),
            ])),
            'chip'    => ['label' => $statusLabel, 'color' => $statusColor],
            'figures' => array_values(array_filter([
                ['value' => Carbon::createFromTime((int) ($record->flight_time / 60), $record->flight_time % 60, 0)->format('H:i'), 'label' => __('flights.flight_time')],
                ['value' => (int) round($record->distance?->toUnit('nmi') ?? 0), 'label' => __('common.nautical_miles_short')],
                $record->level ? ['value' => 'FL'.(int) ($record->level / 100), 'label' => __('flights.level')] : null,
                ['value' => $record->subfleets()->count(), 'label' => trans_choice('common.subfleet', $record->subfleets()->count())],
            ])),
        ]);
    }

    /**
     * How often the flight runs, for the descriptor line under the ident.
     * An empty mask means every day, which is what the schedule builder
     * assumes when nothing is picked.
     */
    protected static function frequencyLabel(Flight $record): string
    {
        $days = (int) $record->days;

        $selected = array_filter(
            array_keys(Days::$labels),
            fn (int $day): bool => Days::in($days, $day),
        );

        if ($selected === [] || count($selected) === count(Days::$labels)) {
            return __('filament.flights.frequency_daily');
        }

        return implode(', ', array_map(
            fn (int $day): string => Carbon::now()
                ->startOfWeek(CarbonInterface::MONDAY)
                ->addDays(array_search($day, array_values(Days::$isoDayMap), true))
                ->shortDayName,
            $selected,
        ));
    }

    /**
     * The relation-manager half of the jump bar is derived by the trait.
     *
     * @return array<string, string>
     */
    protected function jumpBarFormSections(): array
    {
        return [
            'flight-information' => __('filament.flight_information'),
            'route'              => __('flights.route'),
            'scheduling'         => __('filament.scheduling'),
            'loadsheet'          => __('filament.flights.sections.loadsheet'),
        ];
    }

    /** The identity overview sits above the jump bar. */
    protected function contentHeader(): array
    {
        return [
            View::make('components.admin.overview')
                ->viewData([
                    'cards'      => $this->summaryCards(),
                    'ariaLabel'  => __('filament.flight_information'),
                    'editAction' => $this->editAction,
                ]),
        ];
    }

    /**
     * Airline, number and type are read here and edited in the drawer, so they
     * are dropped from the page form (see FlightForm::configure()).
     *
     * @return array<int, array{icon: TablerIcon, tint: string|null, label: string, value: string, note: string}>
     */
    protected function summaryCards(): array
    {
        /** @var Flight $record */
        $record = $this->getRecord();

        return [
            [
                'icon'  => TablerIcon::BuildingArch,
                'tint'  => null,
                'label' => __('common.airline'),
                'value' => (string) $record->airline?->name,
                'note'  => (string) $record->airline?->icao,
            ],
            [
                'icon'  => TablerIcon::Hash,
                'tint'  => 'blue',
                'label' => __('flights.flightnumber'),
                'value' => (string) $record->flight_number,
                'note'  => implode(' · ', array_filter([
                    filled($record->route_code) ? __('flights.routecode').' '.$record->route_code : null,
                    $record->route_leg !== null ? __('flights.routeleg').' '.$record->route_leg : null,
                ])),
            ],
            [
                'icon'  => TablerIcon::Plane,
                'tint'  => 'violet',
                'label' => __('flights.flighttype'),
                'value' => (string) $record->flight_type->getLabel(),
                'note'  => (string) $record->callsign,
            ],
        ];
    }

    /**
     * The Edit trigger rendered inside the overview's last card.
     *
     * Only the identity keys are handed to the drawer. Filling it from the
     * whole record would put `distance` — a Distance value object — into
     * Livewire's state, which it cannot serialise, and the drawer would 500 on
     * open. Deriving the keys from the fields keeps the two in step.
     */
    public function editAction(): Action
    {
        $identityFields = FlightForm::identityFields();

        $keys = array_map(
            fn (Field $field): string => $field->getName(),
            $identityFields,
        );

        return EditDetailsAction::make($identityFields)
            ->modalHeading(__('filament.flight_information'))
            ->mutateRecordDataUsing(fn (array $data): array => Arr::only($data, $keys))
            ->extraModalFooterActions([
                DeleteAction::make()->cancelParentActions(),
            ]);
    }

    /** The identity fields live in the drawer, not the page form. */
    #[Override]
    public function form(Schema $schema): Schema
    {
        return FlightForm::configure($schema, withIdentity: false);
    }

    protected function jumpBarSwitcher(): array
    {
        return $this->getBundleFlights();
    }

    protected function jumpBarSwitcherLabel(): string
    {
        return $this->getRecord()->ident;
    }

    /**
     * Other flights in the same bundle, for the switcher dropdown.
     *
     * @return array<int, array{label: string, url: string}>
     */
    protected function getBundleFlights(): array
    {
        /** @var Flight $record */
        $record = $this->getRecord();
        $bundle = $record->bundle;

        if ($bundle === null) {
            return [];
        }

        /** @var Collection<int, Flight> $flights */
        $flights = $bundle->flights()
            ->whereKeyNot($record->getKey())
            ->with(['dpt_airport', 'arr_airport'])
            ->orderBy('flight_number')
            ->get();

        return $flights
            ->map(fn (Flight $flight): array => [
                'label' => sprintf('%s · %s → %s', $flight->ident, $flight->dpt_airport->icao, $flight->arr_airport->icao),
                'url'   => FlightResource::getUrl('edit', ['record' => $flight, 'bundle' => $bundle], shouldGuessMissingParameters: true),
            ])
            ->all();
    }

    #[Override]
    protected function getFormActions(): array
    {
        return $this->reversePrimaryButtons(parent::getFormActions());
    }

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            // Delete lives in the settings drawer's footer (editAction()).
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    #[Override]
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['distance'] = $data['distance']->toUnit('nmi');

        $data['flight_time'] = Carbon::createFromTime(
            (int) ($data['flight_time'] / 60),
            $data['flight_time'] % 60,
            0
        );

        return $data;
    }

    #[Override]
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // flight_time is not on the form any more; only convert it when a
        // caller actually supplied one.
        if (isset($data['flight_time'])) {
            $flt_time = Carbon::parse($data['flight_time']);
            $data['flight_time'] = $flt_time->hour * 60 + $flt_time->minute;
        }

        return $data;
    }
}
