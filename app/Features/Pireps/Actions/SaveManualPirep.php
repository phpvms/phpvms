<?php

declare(strict_types=1);

namespace App\Features\Pireps\Actions;

use App\Enums\PirepFieldSource;
use App\Enums\PirepState;
use App\Models\Aircraft;
use App\Models\Pirep;
use App\Models\PirepFare;
use App\Models\PirepField;
use App\Models\SimBrief;
use App\Models\User;
use App\Services\FareService;
use App\Services\PirepService;
use App\Services\SimBriefService;
use App\Support\PirepLevelNormalizer;
use App\Support\Units\Fuel;
use App\Support\Units\Time;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

/**
 * Create or update a manually filed PIREP.
 *
 * The one write path for the web PIREP form, shared by `PirepController`
 * (store/update) and the `PirepForm` Livewire component. Input is the form
 * payload: PIREP columns, `hours` and `minutes`, custom fields under their
 * slug and fares as `fare_{id}`.
 */
final readonly class SaveManualPirep
{
    public function __construct(
        private FareService $fares,
        private PirepService $pireps,
        private SimBriefService $simbrief,
    ) {}

    /**
     * Columns a pilot may never set from the form. The state moves only through
     * submit/accept/reject; the key and owner come from the server; and the
     * source, timestamps and flight-quality values decide auto-approval and
     * awards, so they are never taken from a manual report.
     *
     * @var list<string>
     */
    private const array PROTECTED_ATTRIBUTES = [
        'id', 'state', 'user_id', 'source', 'source_name', 'status', 'score', 'landing_rate',
        'submitted_at', 'block_off_time', 'block_on_time', 'created_at', 'updated_at',
    ];

    /**
     * @param array<string, mixed> $input
     *
     * @throws PirepFilingDenied when a submitted PIREP breaks a filing rule
     * @throws Exception
     */
    public function create(User $user, array $input, string $submit, ?string $simbriefId = null): Pirep
    {
        $submit = strtolower($submit);
        $input = Arr::except($input, self::PROTECTED_ATTRIBUTES);

        $pirep = new Pirep(PirepLevelNormalizer::normalize($input));
        $pirep->user_id = $user->id;

        if ($submit === 'submit') {
            $this->assertMayFile($user, $pirep);
        }

        // Any special fields
        $hours = (int) ($input['hours'] ?? 0);
        $minutes = (int) ($input['minutes'] ?? 0);
        $pirep->flight_time = Time::hoursToMinutes($hours) + $minutes;

        // Set the correct fuel units
        $pirep->block_fuel = Fuel::make((float) ($input['block_fuel'] ?? 0), setting('units.fuel'));
        $pirep->fuel_used = Fuel::make((float) ($input['fuel_used'] ?? 0), setting('units.fuel'));

        // Put the time that this is currently submitted
        $pirep->submitted_at = Carbon::now('UTC');

        $pirep = $this->pireps->create($pirep, $this->customFields($input));
        $this->saveFares($pirep, $input);
        $this->pireps->saveRoute($pirep);

        if (filled($simbriefId)) {
            $brief = SimBrief::where('user_id', $user->id)->find($simbriefId);
            if ($brief !== null) {
                // Keep the flight_id with SimBrief depending on the button selected
                // Save = Keep the flight_id , Submit = Remove the flight_id
                if ($submit === 'save') {
                    $this->simbrief->attachSimbriefToPirep($pirep, $brief, true);
                } elseif ($submit === 'submit') {
                    $this->simbrief->attachSimbriefToPirep($pirep, $brief);
                }
            }
        }

        // Depending on the button they selected, set an initial state
        // Can be saved as a draft or just submitted
        if ($submit === 'save') {
            if (!$pirep->read_only) {
                $pirep->state = PirepState::DRAFT;
            }

            $pirep->save();
        } elseif ($submit === 'submit') {
            $this->pireps->submit($pirep);
        }

        return $pirep;
    }

    /**
     * Update the PIREP, then save, submit or delete it. Returns the lower-cased
     * `$submit` mode, so the caller can word its message (`delete` and
     * `cancel` both delete the PIREP).
     *
     * @param array<string, mixed> $input
     *
     * @throws PirepFilingDenied when a submitted PIREP breaks a filing rule
     * @throws Exception
     */
    public function update(Pirep $pirep, array $input, string $submit): string
    {
        $submit = strtolower($submit);
        $input = Arr::except($input, self::PROTECTED_ATTRIBUTES);
        $origRoute = $pirep->route;

        // Fix the time
        $input['flight_time'] = Time::init($input['minutes'], $input['hours'])->getMinutes();

        // Fix the fuel
        $input['block_fuel'] = Fuel::make((float) $input['block_fuel'], setting('units.fuel'));
        $input['fuel_used'] = Fuel::make((float) $input['fuel_used'], setting('units.fuel'));

        $pirep->update(PirepLevelNormalizer::normalize($input));
        $pirep->refresh();

        // A route change in the PIREP, so update the saved points in the ACARS table
        if ($pirep->route !== $origRoute) {
            $this->pireps->saveRoute($pirep);
        }

        // @phpstan-ignore argument.type (the service documents models but reads the array rows built here)
        $this->pireps->updateCustomFields($pirep->id, $this->customFields($input));
        $this->saveFares($pirep, $input);

        if ($submit === 'submit') {
            $this->assertMayFile($pirep->user, $pirep);
            $this->pireps->submit($pirep);
        } elseif ($submit === 'delete' || $submit === 'cancel') {
            $this->pireps->delete($pirep);
        }

        return $submit;
    }

    /**
     * @throws PirepFilingDenied
     */
    private function assertMayFile(User $user, Pirep $pirep): void
    {
        // Are they allowed at this airport?
        if (setting('pilots.only_flights_from_current')
            && $user->curr_airport_id !== $pirep->dpt_airport_id) {
            Log::info(
                'Pilot '.$user->id
                .' not at departure airport (curr='.$user->curr_airport_id
                .', dpt='.$pirep->dpt_airport_id.')'
            );

            throw new PirepFilingDenied('You are currently not at the departure airport!');
        }

        // Can they fly this aircraft?
        if (setting('operations.restrict_aircraft_to_rank', false)
            && !$user->allowedAircraft()->whereKey($pirep->aircraft_id)->exists()) {
            Log::info('Pilot '.$user->id.' not allowed to fly aircraft');

            throw new PirepFilingDenied('You are not allowed to fly this aircraft!');
        }

        // is the aircraft in the right place?
        $aircraft = Aircraft::find($pirep->aircraft_id);
        if ($aircraft === null) {
            Log::error('Aircraft for PIREP not found, id='.$pirep->aircraft_id);

            throw new PirepFilingDenied("The aircraft for the PIREP hasn't been found");
        }

        if (setting('operations.only_aircraft_at_dpt_airport')
            && $aircraft->airport_id !== $pirep->dpt_airport_id
        ) {
            Log::info(
                'Aircraft '.$pirep->aircraft_id.' not at departure airport (curr='.$aircraft->airport_id.', apt='.$pirep->dpt_airport_id.')'
            );

            throw new PirepFilingDenied('This aircraft is not positioned at the departure airport!');
        }

        // Make sure this isn't a duplicate
        if ($this->pireps->findDuplicate($pirep, excludeSelf: true) !== false) {
            Log::info('Duplicate PIREP found');

            throw new PirepFilingDenied('This PIREP has already been filed.');
        }
    }

    /**
     * The custom field values found in the input, keyed by field slug.
     *
     * @param  array<string, mixed>                                                            $input
     * @return list<array{name: string, slug: string, value: mixed, source: PirepFieldSource}>
     */
    private function customFields(array $input): array
    {
        $fields = [];
        $pirepFields = PirepField::whereIn('pirep_source', [PirepFieldSource::MANUAL, PirepFieldSource::BOTH])->get();
        foreach ($pirepFields as $field) {
            if (!filled($input[$field->slug] ?? null)) {
                continue;
            }

            $fields[] = [
                'name'   => $field->name,
                'slug'   => $field->slug,
                'value'  => $input[$field->slug],
                'source' => PirepFieldSource::MANUAL,
            ];
        }

        Log::info('PIREP Custom Fields', $fields);

        return $fields;
    }

    /**
     * Save the fares that have been specified/saved
     *
     * @param array<string, mixed> $input
     *
     * @throws Exception
     */
    private function saveFares(Pirep $pirep, array $input): void
    {
        $fares = [];
        $pirep->loadMissing('aircraft.subfleet.fares');
        if (!$pirep->aircraft) {
            return;
        }

        foreach ($pirep->aircraft->subfleet->fares as $fare) {
            $fieldName = 'fare_'.$fare->id;

            $fares[] = new PirepFare([
                'fare_id' => $fare->id,
                'count'   => filled($input[$fieldName] ?? null) ? $input[$fieldName] : 0,
            ]);
        }

        $this->fares->saveToPirep($pirep, $fares);
    }
}
