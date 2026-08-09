<?php

namespace App\Filament\Resources\FlightBundles\Resources\Flight\Pages;

use App\Filament\Concerns\ReversePrimaryButtons;
use App\Filament\Resources\FlightBundles\Resources\Flight\FlightResource;
use App\Filament\Resources\FlightBundles\Resources\Flight\Schemas\FlightForm;
use App\Models\Flight;
use Carbon\Carbon;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\RelationManagers\RelationManagerConfiguration;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Livewire;
use Filament\Support\Enums\Alignment;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Override;

class EditFlight extends EditRecord
{
    use ReversePrimaryButtons;

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
     * Render the related-data managers stacked below the form (like Filament
     * does with a single relation manager) instead of a tab bar. Each manager
     * draws its own complete card (heading + actions + table), so we only wrap
     * it in a plain anchor holder — no second card.
     */
    #[Override]
    public function getRelationManagersContentComponent(): Component
    {
        $managers = $this->getRelationManagers();
        $ownerRecord = $this->getRecord();
        $managerLivewireData = ['ownerRecord' => $ownerRecord, 'pageClass' => static::class];

        $sections = [];
        foreach ($managers as $manager) {
            $managerClass = $this->normalizeRelationManagerClass($manager);

            $sectionId = match (true) {
                str_ends_with($managerClass, 'SubfleetsRelationManager')   => 'subfleets',
                str_ends_with($managerClass, 'FieldValuesRelationManager') => 'fields',
                default                                                    => 'fares',
            };

            $sections[] = Group::make()
                ->id($sectionId)
                ->extraAttributes(['class' => 'scroll-mt-32'])
                ->schema([
                    Livewire::make(
                        $managerClass,
                        [
                            ...$managerLivewireData,
                            ...(($manager instanceof RelationManagerConfiguration)
                                ? [...$manager->relationManager::getDefaultProperties(), ...$manager->getProperties()]
                                : $managerClass::getDefaultProperties()),
                        ],
                    )->key($managerClass),
                ])
                ->columnSpanFull();
        }

        return Grid::make()
            ->columns(1)
            ->schema($sections);
    }

    /**
     * Replace the default page header with the identity strip: flight ident,
     * route, status, quick stats, section-jump links, and a flight switcher.
     */
    #[Override]
    public function getHeader(): ?View
    {
        /** @var Flight $record */
        $record = $this->getRecord();
        $sectionIds = ['flight-information', 'scheduling', 'route', 'subfleets', 'fields', 'fares'];
        [$statusLabel, $statusColor] = FlightForm::flightStatusBadge($record);

        return view('filament.resources.flight.edit-header', [
            'ident'              => $record->ident,
            'dptIcao'            => $record->dpt_airport->icao,
            'arrIcao'            => $record->arr_airport->icao,
            'statusLabel'        => $statusLabel,
            'statusColor'        => $statusColor,
            'flightTime'         => Carbon::createFromTime((int) ($record->flight_time / 60), $record->flight_time % 60, 0)->format('H:i'),
            'distance'           => (int) round($record->distance?->toUnit('nmi') ?? 0),
            'level'              => (int) (($record->level ?? 0) / 100),
            'subfleetCount'      => $record->subfleets()->count(),
            'sectionIds'         => $sectionIds,
            'sectionLinks'       => $this->getSectionLinks(),
            'bundleFlights'      => $this->getBundleFlights(),
            'currentFlightLabel' => $record->ident,
            'headerActions'      => $this->getCachedHeaderActions(),
        ]);
    }

    /**
     * Label + count for each section the strip links to.
     *
     * @return array<int, array{id: string, label: string, count: int|null}>
     */
    protected function getSectionLinks(): array
    {
        /** @var Flight $record */
        $record = $this->getRecord();

        return [
            ['id' => 'flight-information', 'label' => __('filament.flight_information'), 'count' => null],
            ['id' => 'scheduling', 'label' => __('filament.scheduling'), 'count' => null],
            ['id' => 'route', 'label' => __('flights.route'), 'count' => null],
            ['id' => 'subfleets', 'label' => trans_choice('common.subfleet', 1), 'count' => $record->subfleets()->count()],
            ['id' => 'fields', 'label' => trans_choice('common.field', 2), 'count' => $record->field_values()->count()],
            ['id' => 'fares', 'label' => trans_choice('pireps.fare', 2), 'count' => $record->fares()->count()],
        ];
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
            DeleteAction::make(),
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
        $flt_time = Carbon::parse($data['flight_time']);
        $data['flight_time'] = $flt_time->hour * 60 + $flt_time->minute;

        return $data;
    }
}
