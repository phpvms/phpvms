<?php

declare(strict_types=1);

namespace App\Features\Pireps\Livewire;

use App\Enums\PirepFieldSource;
use App\Features\Pireps\Actions\ListFilingAircraft;
use App\Features\Pireps\Actions\PirepFilingDenied;
use App\Features\Pireps\Actions\SaveManualPirep;
use App\Features\Pireps\Livewire\Forms\PirepFormData;
use App\Features\Profile\Actions\SearchAirports;
use App\Models\Aircraft;
use App\Models\Airline;
use App\Models\Fare;
use App\Models\Flight;
use App\Models\Pirep;
use App\Models\PirepField;
use App\Models\SimBrief;
use App\Models\User;
use App\Services\PirepService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laracasts\Flash\Flash;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * File a new PIREP, or edit one of the pilot's own.
 *
 * Filing can start from a flight (`flightId`) or a SimBrief plan
 * (`simbriefId`); both lock the route. The fares follow the chosen aircraft and
 * keep any count already entered for a fare both aircraft have. Saving and
 * submitting go through `SaveManualPirep`, the same action as the web
 * controller.
 *
 * @property bool                                        $isEditing
 * @property ?Pirep                                      $pirep
 * @property bool                                        $readOnly
 * @property bool                                        $routeLocked
 * @property array<string, string>                       $airlineOptions
 * @property array<array-key, string|array<int, string>> $aircraftOptions
 * @property Collection<int, Fare>                       $fares
 * @property Collection<int, array<string, mixed>>       $customFields
 * @property list<array{value: string, label: string}>   $dptResults
 * @property list<array{value: string, label: string}>   $arrResults
 */
class PirepForm extends Component
{
    public PirepFormData $form;

    /** The PIREP being edited; null when filing a new one. */
    #[Locked]
    public ?string $pirepId = null;

    #[Locked]
    public ?string $flightId = null;

    #[Locked]
    public ?string $simbriefId = null;

    public string $dptSearch = '';

    public string $arrSearch = '';

    public string $dptLabel = '';

    public string $arrLabel = '';

    public function mount(?string $pirepId = null, ?string $flightId = null, ?string $simbriefId = null): void
    {
        $user = $this->pilot();
        $seedFares = [];

        if (filled($pirepId)) {
            $pirep = $this->ownedPirep($pirepId);
            $this->pirepId = $pirep->id;
            $this->flightId = $pirep->flight_id;
        } else {
            abort_if(setting('pireps.disable_manual', false), 405, 'Manual PIREP filing is disabled.');

            $pirep = new Pirep();

            if (filled($simbriefId)) {
                $simbrief = SimBrief::with('aircraft.subfleet.fares')->where('user_id', $user->id)->findOrFail($simbriefId);
                $this->simbriefId = (string) $simbrief->id;
                $this->flightId = $simbrief->flight?->id;
                $pirep = Pirep::fromSimBrief($simbrief);
                $this->form->aircraft_id = (string) $simbrief->aircraft_id;

                foreach ($this->simbriefFares() as $fare) {
                    $seedFares[$fare->id] = (string) ($fare->count ?? '');
                }
            } elseif (filled($flightId)) {
                $flight = Flight::findOrFail($flightId);
                $this->flightId = (string) $flight->id;
                $pirep = Pirep::fromFlight($flight);
            }
        }

        $aircraftId = $this->form->aircraft_id;
        $this->form->fillFromPirep($pirep);
        $this->form->aircraft_id = $pirep->aircraft_id === null ? $aircraftId : (string) $pirep->aircraft_id;

        foreach ($this->customFields as $field) {
            $this->form->fields[$field['slug']] = $field['value'];
        }

        if ($this->isEditing) {
            // A saved PIREP fare is a snapshot with no link to the fare, so match it by code.
            $saved = $this->pirep->fares->pluck('count', 'code');

            foreach ($this->fares as $fare) {
                if ($saved->has($fare->code)) {
                    $seedFares[$fare->id] = (string) $saved->get($fare->code);
                }
            }
        }

        $this->form->syncFares($this->fares, $seedFares);

        $this->dptLabel = (string) $pirep->dpt_airport?->full_name;
        $this->arrLabel = (string) $pirep->arr_airport?->full_name;
    }

    #[Computed]
    public function isEditing(): bool
    {
        return $this->pirepId !== null;
    }

    #[Computed]
    public function pirep(): ?Pirep
    {
        return $this->pirepId === null ? null : $this->ownedPirep($this->pirepId);
    }

    /**
     * Accepted, rejected and cancelled PIREPs keep their flight data fixed.
     */
    #[Computed]
    public function readOnly(): bool
    {
        return (bool) $this->pirep?->read_only;
    }

    /**
     * The route is fixed when filing from a flight or SimBrief plan, and on a read-only PIREP.
     */
    #[Computed]
    public function routeLocked(): bool
    {
        return $this->isEditing ? $this->readOnly : $this->flightId !== null;
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function airlineOptions(): array
    {
        return Airline::selectList(addBlank: !$this->isEditing);
    }

    /**
     * The aircraft the pilot may pick, grouped by subfleet type. A SimBrief plan allows only its own aircraft.
     *
     * @return array<array-key, string|array<int, string>>
     */
    #[Computed]
    public function aircraftOptions(): array
    {
        $simbrief = $this->simbrief();

        if ($simbrief?->aircraft !== null) {
            $aircraft = $simbrief->aircraft;

            return [(string) $aircraft->subfleet->name => [$aircraft->id => $aircraft->name.' - '.$aircraft->registration]];
        }

        $options = app(ListFilingAircraft::class)->handle($this->pilot(), true);

        $current = $this->pirep?->aircraft;
        if ($current !== null) {
            $type = (string) $current->subfleet->type;
            $options[$type][$current->id] ??= $current->name.' - '.$current->registration;
        }

        return $options;
    }

    /**
     * The fares of the chosen aircraft's subfleet (a SimBrief plan brings its own).
     *
     * @return Collection<int, Fare>
     */
    #[Computed]
    public function fares(): Collection
    {
        $simbrief = $this->simbrief();

        if ($simbrief instanceof SimBrief && (string) $simbrief->aircraft_id === (string) $this->form->aircraft_id && filled($simbrief->fare_data)) {
            return $this->simbriefFares();
        }

        if (!filled($this->form->aircraft_id)) {
            return collect();
        }

        return Aircraft::with('subfleet.fares')->find($this->form->aircraft_id)?->subfleet->fares ?? collect();
    }

    /**
     * The custom fields to show: slug, name, required, description, value and whether ACARS owns it.
     *
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function customFields(): array
    {
        $pirep = $this->pirep;

        if ($pirep === null) {
            return PirepField::whereIn('pirep_source', [PirepFieldSource::MANUAL, PirepFieldSource::BOTH])
                ->get()
                ->map(fn (PirepField $field): array => [
                    'slug'        => (string) $field->slug,
                    'name'        => $field->name,
                    'required'    => (bool) $field->required,
                    'description' => $field->description,
                    'value'       => null,
                    'readOnly'    => false,
                ])
                ->values()
                ->all();
        }

        $definitions = PirepField::query()->get()->keyBy('slug');

        return collect($pirep->fields)->map(function ($value) use ($definitions): array {
            $slug = $value->slug ?? Str::slug($value->name);
            $definition = $definitions->get($slug);

            return [
                'slug'        => (string) $slug,
                'name'        => $value->name,
                'required'    => (bool) $definition?->required,
                'description' => $definition?->description,
                'value'       => $value->value === null ? null : (string) $value->value,
                'readOnly'    => (bool) $value->read_only,
            ];
        })->values()->all();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    #[Computed]
    public function dptResults(): array
    {
        return app(SearchAirports::class)->handle($this->dptSearch);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    #[Computed]
    public function arrResults(): array
    {
        return app(SearchAirports::class)->handle($this->arrSearch);
    }

    public function chooseAirport(string $side, string $airportId): void
    {
        abort_unless(in_array($side, ['dpt', 'arr'], true), 422);
        abort_if($this->routeLocked, 403);

        $match = collect($side === 'dpt' ? $this->dptResults : $this->arrResults)->firstWhere('value', $airportId);

        if ($match === null) {
            return;
        }

        if ($side === 'dpt') {
            $this->form->dpt_airport_id = $match['value'];
            $this->dptLabel = $match['label'];
            $this->dptSearch = '';
        } else {
            $this->form->arr_airport_id = $match['value'];
            $this->arrLabel = $match['label'];
            $this->arrSearch = '';
        }
    }

    /**
     * Pick a different aircraft: the fares change with it. Counts for fares the new aircraft also has stay.
     */
    public function updatedFormAircraftId(): void
    {
        unset($this->fares);

        $this->form->syncFares($this->fares);
    }

    public function save(): void
    {
        $this->persist('save');
    }

    public function submit(): void
    {
        $this->persist('submit');
    }

    public function delete(): void
    {
        $pirep = $this->pirep;

        abort_if($pirep === null || $pirep->read_only, 403);

        app(PirepService::class)->delete($pirep);

        Flash::success('PIREP deleted!');

        $this->redirectRoute('frontend.pireps.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.pireps.pirep-form');
    }

    private function persist(string $mode): void
    {
        $user = $this->pilot();
        $pirep = $this->pirep;

        abort_if($pirep === null && setting('pireps.disable_manual', false), 405, 'Manual PIREP filing is disabled.');
        abort_if($pirep !== null && $pirep->read_only, 403);

        $this->resetErrorBag('filing');
        $this->lockRoute($pirep);

        $fields = collect($this->customFields)->reject(fn (array $field): bool => $field['readOnly']);

        $this->form->validate($this->form->rulesFor(
            $mode === 'save',
            $fields->where('required', true)->pluck('slug')->all(),
            $fields->where('required', false)->pluck('slug')->all(),
        ));

        $input = $this->form->payload($fields->pluck('slug')->all());
        $input['flight_id'] = $this->flightId;

        try {
            if ($pirep !== null) {
                app(SaveManualPirep::class)->update($pirep, $input, $mode);
                $saved = $pirep;
            } else {
                $saved = app(SaveManualPirep::class)->create($user, $input, $mode, $this->simbriefId);
            }
        } catch (PirepFilingDenied $pirepFilingDenied) {
            $this->addError('filing', $pirepFilingDenied->getMessage());

            return;
        }

        Flash::success($mode === 'submit' ? 'PIREP submitted!' : 'PIREP saved successfully.');

        $this->redirectRoute('frontend.pireps.show', [$saved->id], navigate: true);
    }

    /**
     * Set the fields the pilot may not change back to the saved PIREP, or the flight / SimBrief plan.
     */
    private function lockRoute(?Pirep $pirep): void
    {
        if ($pirep instanceof Pirep) {
            if ($pirep->read_only) {
                $this->form->restoreLocked($pirep);
            }

            return;
        }

        if ($this->flightId === null) {
            return;
        }

        $flight = Flight::find($this->flightId);

        if ($flight !== null) {
            $this->form->dpt_airport_id = $flight->dpt_airport_id;
            $this->form->arr_airport_id = $flight->arr_airport_id;
        }
    }

    private function simbrief(): ?SimBrief
    {
        if ($this->simbriefId === null) {
            return null;
        }

        return SimBrief::with('aircraft.subfleet.fares')->where('user_id', $this->pilot()->id)->find($this->simbriefId);
    }

    /**
     * @return Collection<int, Fare>
     */
    private function simbriefFares(): Collection
    {
        $data = $this->simbrief()?->fare_data;

        if (!filled($data)) {
            return collect();
        }

        return collect(json_decode((string) $data, true) ?: [])
            ->map(function (array $fare): Fare {
                $model = new Fare($fare);
                // @phpstan-ignore property.notFound
                $model->count = $fare['count'] ?? null;

                return $model;
            })
            ->values();
    }

    private function ownedPirep(string $id): Pirep
    {
        $pirep = Pirep::with(['dpt_airport', 'arr_airport', 'alt_airport', 'fares', 'aircraft.subfleet.fares'])->find($id);

        abort_if($pirep === null, 404);
        abort_unless($pirep->user_id === $this->pilot()->id, 403, "Cannot edit someone else's PIREP!");

        return $pirep;
    }

    private function pilot(): User
    {
        $user = Auth::user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
