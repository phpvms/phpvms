<?php

namespace App\Filament\Resources\Subfleets\Resources\Aircraft\Pages;

use App\Filament\Actions\EditDetailsAction;
use App\Filament\Concerns\ReversePrimaryButtons;
use App\Filament\Concerns\StacksRelationManagers;
use App\Filament\Resources\Subfleets\Resources\Aircraft\AircraftResource;
use App\Filament\Resources\Subfleets\Resources\Aircraft\Schemas\AircraftForm;
use App\Models\Aircraft;
use App\Models\File;
use App\Services\FileService;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Field;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Arr;
use Illuminate\Support\HtmlString;
use Override;

/**
 * Same identity → workspace split as the flight edit page: name, registration,
 * ICAO and status are read in the overview and edited only through the drawer
 * it opens, leaving the page form for placement, identifiers and weights.
 */
class EditAircraft extends EditRecord
{
    use ReversePrimaryButtons;
    use StacksRelationManagers;

    protected static string $resource = AircraftResource::class;

    #[Override]
    public function getHeading(): string|Htmlable
    {
        /** @var Aircraft $record */
        $record = $this->getRecord();

        return new HtmlString(sprintf(
            '%s <span class="id fi-header-heading-route">%s</span>',
            e($record->registration),
            e((string) $record->icao),
        ));
    }

    #[Override]
    public function getSubheading(): string|Htmlable|null
    {
        /** @var Aircraft $record */
        $record = $this->getRecord();

        return view('filament.shared.hero-subheading', [
            'meta' => implode(' · ', array_filter([
                $record->name,
                $record->airline?->name,
            ])),
            'chip'    => ['label' => $record->status->getLabel(), 'color' => $record->status->getColor()],
            'figures' => [
                ['value' => number_format(round(($record->flight_time ?? 0) / 60)), 'label' => __('flights.flighthours')],
                ['value' => number_format($record->pireps()->count()), 'label' => trans_choice('common.pirep', 2)],
            ],
        ]);
    }

    /**
     * The expenses and files relation managers are appended by the trait.
     *
     * @return array<string, string>
     */
    protected function jumpBarFormSections(): array
    {
        return [
            'aircraft-information' => __('filament.aircraft_information'),
            'identifiers'          => __('filament.aircraft_identifiers'),
            'certified-weights'    => __('filament.certified_weights'),
        ];
    }

    /** The identity overview sits above the jump bar. */
    protected function contentHeader(): array
    {
        return [
            View::make('components.admin.overview')
                ->viewData([
                    'cards'      => $this->summaryCards(),
                    'ariaLabel'  => __('filament.aircraft_information'),
                    'editAction' => $this->editAction,
                ]),
        ];
    }

    /**
     * @return array<int, array{icon: TablerIcon, tint: string|null, label: string, value: string, note: string}>
     */
    protected function summaryCards(): array
    {
        /** @var Aircraft $record */
        $record = $this->getRecord();

        return [
            [
                'icon'  => TablerIcon::Power,
                'tint'  => null,
                'label' => __('common.status'),
                'value' => $record->status->getLabel(),
                'note'  => $record->state->getLabel(),
            ],
            [
                'icon'  => TablerIcon::Hash,
                'tint'  => 'blue',
                'label' => __('aircraft.registration'),
                'value' => (string) $record->registration,
                'note'  => (string) $record->icao,
            ],
            [
                'icon'  => TablerIcon::Stack2,
                'tint'  => 'teal',
                'label' => trans_choice('common.subfleet', 1),
                'value' => (string) $record->subfleet?->type,
                'note'  => (string) $record->subfleet?->name,
            ],
            [
                'icon'  => TablerIcon::MapPin,
                'tint'  => 'violet',
                'label' => __('airports.current'),
                'value' => (string) ($record->airport?->icao ?: '—'),
                'note'  => (string) $record->home?->icao,
            ],
        ];
    }

    /**
     * The Edit trigger rendered inside the overview's last card.
     *
     * Only the identity keys are handed to the drawer. Filling it from the
     * whole record would put the weights — Mass value objects — into Livewire's
     * state, which it cannot serialise, and the drawer would 500 on open.
     */
    public function editAction(): Action
    {
        $identityFields = AircraftForm::identityFields();

        $keys = array_map(
            fn (Field $field): string => $field->getName(),
            $identityFields,
        );

        return EditDetailsAction::make($identityFields)
            ->modalHeading(__('filament.aircraft_information'))
            ->mutateRecordDataUsing(fn (array $data): array => Arr::only($data, $keys))
            ->extraModalFooterActions([
                DeleteAction::make()->cancelParentActions(),
            ]);
    }

    /** The identity fields live in the drawer, not the page form. */
    #[Override]
    public function form(Schema $schema): Schema
    {
        return AircraftForm::configure($schema, withIdentity: false);
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
            ForceDeleteAction::make()->before(function (Aircraft $record): void {
                $record->files()->each(function (File $file): void {
                    app(FileService::class)->removeFile($file);
                });
            }),
            RestoreAction::make(),
        ];
    }

    #[Override]
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['fuel_onboard'] = $data['fuel_onboard']->toUnit(setting('units.fuel'));

        $data['dow'] = round($data['dow']->toUnit(setting('units.weight')));
        $data['zfw'] = round($data['zfw']->toUnit(setting('units.weight')));
        $data['mtow'] = round($data['mtow']->toUnit(setting('units.weight')));
        $data['mlw'] = round($data['mlw']->toUnit(setting('units.weight')));

        return $data;
    }
}
