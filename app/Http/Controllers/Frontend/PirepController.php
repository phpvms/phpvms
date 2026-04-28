<?php

namespace App\Http\Controllers\Frontend;

use App\Contracts\Controller;
use App\Filament\Resources\Pireps\PirepResource;
use App\Http\Requests\CreatePirepRequest;
use App\Http\Requests\SearchPirepsRequest;
use App\Http\Requests\UpdatePirepRequest;
use App\Models\Aircraft;
use App\Models\Airline;
use App\Models\Enums\PirepFieldSource;
use App\Models\Enums\PirepSource;
use App\Models\Enums\PirepState;
use App\Models\Fare;
use App\Models\Flight;
use App\Models\Pirep;
use App\Models\PirepFare;
use App\Models\PirepField;
use App\Models\SimBrief;
use App\Models\User;
use App\Queries\PirepSearchQuery;
use App\Services\FareService;
use App\Services\GeoService;
use App\Services\PirepService;
use App\Services\SimBriefService;
use App\Services\UserService;
use App\Support\Units\Fuel;
use App\Support\Units\Time;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Laracasts\Flash\Flash;
use Prettus\Validator\Exceptions\ValidatorException;

class PirepController extends Controller
{
    public function __construct(
        private readonly FareService $fareSvc,
        private readonly GeoService $geoSvc,
        private readonly PirepSearchQuery $pirepSearchQuery,
        private readonly PirepService $pirepSvc,
        private readonly UserService $userSvc
    ) {}

    /**
     * Dropdown with aircraft grouped by subfleet
     */
    public function aircraftList(bool $add_blank = false): array
    {
        $user = Auth::user();
        $user_loc = filled(
            $user->curr_airport_id
        ) ? $user->curr_airport_id : $user->home_airport_id;
        $location_check = setting('pireps.only_aircraft_at_dpt_airport', false);

        $aircraft = [];
        $subfleets = $this->userSvc->getAllowableSubfleets($user);

        if ($add_blank) {
            $aircraft[''] = '';
        }

        $subfleets->loadMissing('aircraft');

        foreach ($subfleets as $subfleet) {
            $tmp = [];
            foreach ($subfleet->aircraft->when($location_check, function ($query) use ($user_loc) {
                return $query->where('airport_id', $user_loc);
            }) as $ac) {
                $tmp[$ac->id] = $ac['name'].' - '.$ac['registration'];
            }

            $aircraft[$subfleet->type] = $tmp;
        }

        return $aircraft;
    }

    /**
     * Save any custom fields found
     */
    protected function saveCustomFields(Request $request): array
    {
        $fields = [];
        $pirep_fields = PirepField::whereIn('pirep_source', [PirepFieldSource::MANUAL, PirepFieldSource::BOTH])->get();
        foreach ($pirep_fields as $field) {
            if (!$request->filled($field->slug)) {
                continue;
            }

            $fields[] = [
                'name'   => $field->name,
                'slug'   => $field->slug,
                'value'  => $request->input($field->slug),
                'source' => PirepSource::MANUAL,
            ];
        }

        Log::info('PIREP Custom Fields', $fields);

        return $fields;
    }

    /**
     * Save the fares that have been specified/saved
     *
     *
     * @throws Exception
     */
    protected function saveFares(Pirep $pirep, Request $request): void
    {
        $fares = [];
        if (!$pirep->aircraft) {
            return;
        }

        foreach ($pirep->aircraft->subfleet->fares as $fare) {
            $field_name = 'fare_'.$fare->id;
            $count = $request->filled($field_name) ? $request->input($field_name) : 0;

            $fares[] = new PirepFare([
                'fare_id' => $fare->id,
                'count'   => $count,
            ]);
        }

        $this->fareSvc->saveToPirep($pirep, $fares);
    }

    public function index(SearchPirepsRequest $request): View
    {
        $user = Auth::user();

        $where = [
            ['user_id', '=', $user->id],
            ['state', '<>', PirepState::CANCELLED],
        ];

        // Support retrieval of deleted relationships
        $with = [
            'aircraft' => function ($query) {
                return $query->withTrashed();
            },
            'airline' => function ($query) {
                return $query->withTrashed();
            },
            'arr_airport' => function ($query) {
                return $query->withTrashed();
            },
            'comments',
            'dpt_airport' => function ($query) {
                return $query->withTrashed();
            },
            'fares',
        ];

        $query = $this->pirepSearchQuery->build($request)->with($with);

        // Apply controller-owned filters (the previous $where array).
        foreach ($where as [$col, $op, $val]) {
            $query->where($col, $op, $val);
        }

        // Default ordering: legacy sortable() fallback was submitted_at desc.
        if (!$request->filled('orderBy')) {
            $query->orderBy('submitted_at', 'desc');
        }

        $perPage = $request->integer('limit') ?: config('repository.pagination.limit', 50);
        $pireps = $query->paginate($perPage);

        return view('pireps.index', [
            'user'   => $user,
            'pireps' => $pireps,
        ]);
    }

    public function show(string $id): RedirectResponse|View
    {
        // Support retrieval of deleted relationships
        $with = [
            'acars_logs',
            'aircraft' => function ($query) {
                return $query->withTrashed()->with(['airline' => function ($query) {
                    return $query->withTrashed();
                }]);
            },
            'airline' => function ($query) {
                return $query->withTrashed()->with('journal');
            },
            'arr_airport' => function ($query) {
                return $query->withTrashed();
            },
            'comments',
            'dpt_airport' => function ($query) {
                return $query->withTrashed();
            },
            'fares',
            'simbrief',
            'transactions',
            'user' => function ($query) {
                return $query->withTrashed()->with(['rank' => function ($query) {
                    return $query->withTrashed();
                }]);
            },
        ];

        $pirep = Pirep::with($with)->find($id);
        if (empty($pirep)) {
            Flash::error('Pirep not found');

            return redirect(route('frontend.pirep.index'));
        }

        $map_features = $this->geoSvc->pirepGeoJson($pirep);

        return view('pireps.show', [
            'pirep'        => $pirep,
            'map_features' => $map_features,
            'user'         => Auth::user(),
        ]);
    }

    /**
     * Return the fares form for a given aircraft
     */
    public function fares(Request $request): View
    {
        $aircraft_id = $request->input('aircraft_id');
        $aircraft = Aircraft::findOrFail($aircraft_id);

        return view('pireps.fares', [
            'aircraft'  => $aircraft,
            'read_only' => false,
        ]);
    }

    /**
     * Create a new flight report
     */
    public function create(Request $request): View
    {
        $pirep = null;

        // See if request has a ?flight_id, so we can pre-populate the fields from the flight
        // Makes filing easier, but we can also more easily find a bid and close it
        if ($request->has('flight_id')) {
            $flight = Flight::findOrFail($request->input('flight_id'));
            $pirep = Pirep::fromFlight($flight);
        }

        /**
         * They have a SimBrief ID, load that up and figure out the flight that it's from
         */
        $fare_values = [];
        $simbrief = null;
        $simbrief_id = null;
        $aircraft = null;
        if ($request->has('sb_id')) {
            $simbrief_id = $request->input('sb_id');
            $simbrief = SimBrief::find($simbrief_id);
            $pirep = Pirep::fromSimBrief($simbrief);

            $aircraft = $simbrief->aircraft;
            $aircraft_list[$aircraft->subfleet->name] = [];
            $aircraft_list[$aircraft->subfleet->name][$aircraft->id] = $aircraft->name.' - '.$aircraft->registration;

            // Convert the fare data into the expected output format
            if (!empty($simbrief->fare_data)) {
                $fare_values = json_decode($simbrief->fare_data, true);
                $fares = [];
                $fare_data = json_decode($simbrief->fare_data, true);
                foreach ($fare_data as $fare) {
                    $fares[] = new Fare($fare);
                }

                $aircraft->subfleet->fares = collect($fares);
            }
            // TODO: Set more fields from the Simbrief to the PIREP form
        } else {
            $aircraft_list = $this->aircraftList(true);
        }

        $pirep_source = filled(optional($pirep)->source) ? $pirep->source : PirepSource::MANUAL;
        $airports = ['' => ''];

        if ($pirep instanceof Pirep) {
            $airports[$pirep->arr_airport->id] = $pirep->arr_airport->full_name;
            $airports[$pirep->dpt_airport->id] = $pirep->dpt_airport->full_name;

            if ($pirep->alt_airport_id) {
                $airports[$pirep->alt_airport->id] = $pirep->alt_airport->full_name;
            }
        }

        return view('pireps.create', [
            'aircraft'      => $aircraft,
            'pirep'         => $pirep,
            'read_only'     => false,
            'airline_list'  => Airline::selectList(addBlank: true),
            'aircraft_list' => $aircraft_list,
            'airport_list'  => $airports,
            'pirep_fields'  => PirepField::whereIn('pirep_source', [$pirep_source, PirepFieldSource::BOTH])->get(),
            'field_values'  => [],
            'fare_values'   => $fare_values,
            'simbrief_id'   => $simbrief_id,
            'simbrief'      => $simbrief,
        ]);
    }

    /**
     * @throws Exception
     */
    public function store(CreatePirepRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $pirep = new Pirep($request->post());
        $pirep->user_id = $user->id;

        $attrs = $request->all();
        $attrs['submit'] = strtolower($attrs['submit']);

        if ($attrs['submit'] === 'submit') {
            // Are they allowed at this airport?
            if (setting('pilots.only_flights_from_current')
                && $user->curr_airport_id !== $pirep->dpt_airport_id) {
                Log::info(
                    'Pilot '.$user->id
                    .' not at departure airport (curr='.$user->curr_airport_id
                    .', dpt='.$pirep->dpt_airport_id.')'
                );

                return $this->flashError(
                    'You are currently not at the departure airport!',
                    'frontend.pireps.create'
                );
            }

            // Can they fly this aircraft?
            if (setting('pireps.restrict_aircraft_to_rank', false)
                && !$this->userSvc->aircraftAllowed($user, $pirep->aircraft_id)) {
                Log::info('Pilot '.$user->id.' not allowed to fly aircraft');

                return $this->flashError(
                    'You are not allowed to fly this aircraft!',
                    'frontend.pireps.create'
                );
            }

            // is the aircraft in the right place?
            /* @noinspection NotOptimalIfConditionsInspection */
            // Get the aircraft
            $aircraft = Aircraft::find($pirep->aircraft_id);
            if ($aircraft === null) {
                Log::error('Aircraft for PIREP not found, id='.$pirep->aircraft_id);

                return $this->flashError(
                    'The aircraft for the PIREP hasn\'t been found',
                    'frontend.pireps.create'
                );
            }

            if (setting('pireps.only_aircraft_at_dpt_airport')
                && $aircraft->airport_id !== $pirep->dpt_airport_id
            ) {
                Log::info(
                    'Aircraft '.$pirep->aircraft_id.' not at departure airport (curr='.$pirep->aircraft->airport_id.', apt='.$pirep->dpt_airport_id.')'
                );

                return $this->flashError(
                    'This aircraft is not positioned at the departure airport!',
                    'frontend.pireps.create'
                );
            }

            // Make sure this isn't a duplicate
            $dupe_pirep = $this->pirepSvc->findDuplicate($pirep);
            if ($dupe_pirep !== false) {
                Log::info('Duplicate PIREP found');

                return $this->flashError(
                    'This PIREP has already been filed.',
                    'frontend.pireps.create'
                );
            }
        }

        // Any special fields
        $hours = (int) $request->input('hours', 0);
        $minutes = (int) $request->input('minutes', 0);
        $pirep->flight_time = Time::hoursToMinutes($hours) + $minutes;

        // Set the correct fuel units
        $pirep->block_fuel = Fuel::make(
            (float) $request->input('block_fuel'),
            setting('units.fuel')
        );
        $pirep->fuel_used = Fuel::make((float) $request->input('fuel_used'), setting('units.fuel'));

        // Put the time that this is currently submitted
        $attrs['submitted_at'] = Carbon::now('UTC');
        $pirep->submitted_at = Carbon::now('UTC');

        $fields = $this->saveCustomFields($request);
        $pirep = $this->pirepSvc->create($pirep, $fields);
        $this->saveFares($pirep, $request);
        $this->pirepSvc->saveRoute($pirep);

        if ($request->has('sb_id')) {
            $brief = SimBrief::find($request->input('sb_id'));
            if ($brief !== null) {
                /** @var SimBriefService $sbSvc */
                $sbSvc = app(SimBriefService::class);
                // Keep the flight_id with SimBrief depending on the button selected
                // Save = Keep the flight_id , Submit = Remove the flight_id
                if ($attrs['submit'] === 'save') {
                    $sbSvc->attachSimbriefToPirep($pirep, $brief, true);
                } elseif ($attrs['submit'] === 'submit') {
                    $sbSvc->attachSimbriefToPirep($pirep, $brief);
                }
            }
        }

        // Depending on the button they selected, set an initial state
        // Can be saved as a draft or just submitted
        if ($attrs['submit'] === 'save') {
            if (!$pirep->read_only) {
                $pirep->state = PirepState::DRAFT;
            }

            $pirep->save();
            Flash::success('PIREP saved successfully.');
        } elseif ($attrs['submit'] === 'submit') {
            $this->pirepSvc->submit($pirep);
            Flash::success('PIREP submitted!');
        }

        return redirect(route('frontend.pireps.show', [$pirep->id]));
    }

    /**
     * Show the form for editing the specified Pirep.
     */
    public function edit(string $id): RedirectResponse|View
    {
        /** @var ?Pirep $pirep */
        $pirep = Pirep::with(['dpt_airport', 'arr_airport', 'alt_airport'])->find($id);

        if (!$pirep) {
            Flash::error('Pirep not found');

            return redirect(route('frontend.pireps.index'));
        }

        if ($pirep->user_id !== Auth::id()) {
            Flash::error('Cannot edit someone else\'s PIREP!');

            return redirect(PirepResource::getUrl());
        }

        // Eager load the subfleet and fares under it
        if ($pirep->aircraft) {
            $pirep->aircraft->load('subfleet.fares');
        }

        $simbrief_id = null;
        if ($pirep->simbrief) {
            $simbrief_id = $pirep->simbrief->id;
        }

        $time = new Time($pirep->flight_time);
        $pirep->hours = $time->hours;
        $pirep->minutes = $time->minutes;

        // set the custom fields
        foreach ($pirep->fields as $field) {
            if ($field->slug === null) {
                $field->slug = Str::slug($field->name);
            }

            $field_name = 'field_'.$field->slug;
            $pirep->{$field_name} = $field->value;
        }

        // set the fares
        foreach ($pirep->fares as $fare) {
            $field_name = 'fare_'.$fare->fare_id;
            $pirep->{$field_name} = $fare->count;
        }

        $airports = [
            ''                      => '',
            $pirep->arr_airport->id => $pirep->arr_airport->full_name,
            $pirep->dpt_airport->id => $pirep->dpt_airport->full_name,
        ];

        if ($pirep->alt_airport) {
            $airports[$pirep->alt_airport->id] = $pirep->alt_airport->full_name;
        }

        return view('pireps.edit', [
            'pirep'         => $pirep,
            'aircraft'      => $pirep->aircraft,
            'aircraft_list' => $this->aircraftList(true),
            'airline_list'  => Airline::selectList(),
            'airport_list'  => $airports,
            'pirep_fields'  => PirepField::whereIn('pirep_source', [$pirep->source, PirepFieldSource::BOTH])->get(),
            'simbrief_id'   => $simbrief_id,
        ]);
    }

    /**
     * @throws Exception
     * @throws ValidatorException
     */
    public function update(string $id, UpdatePirepRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        /** @var ?Pirep $pirep */
        $pirep = Pirep::find($id);
        if (!$pirep) {
            Flash::error('Pirep not found');

            return redirect(PirepResource::getUrl());
        }

        if ($user->id !== $pirep->user_id) {
            Flash::error('Cannot edit someone else\'s PIREP!');

            return redirect(PirepResource::getUrl());
        }

        $orig_route = $pirep->route;
        $attrs = $request->all();
        $attrs['submit'] = strtolower($attrs['submit']);

        // Fix the time
        $attrs['flight_time'] = Time::init($attrs['minutes'], $attrs['hours'])->getMinutes();

        // Fix the fuel
        $attrs['block_fuel'] = Fuel::make((float) $attrs['block_fuel'], setting('units.fuel'));
        $attrs['fuel_used'] = Fuel::make((float) $attrs['fuel_used'], setting('units.fuel'));

        $pirep->update($attrs);
        $pirep->refresh();

        // A route change in the PIREP, so update the saved points in the ACARS table
        if ($pirep->route !== $orig_route) {
            $this->pirepSvc->saveRoute($pirep);
        }

        $fields = $this->saveCustomFields($request);
        $this->pirepSvc->updateCustomFields($pirep->id, $fields);
        $this->saveFares($pirep, $request);

        if ($attrs['submit'] === 'save') {
            Flash::success('PIREP saved successfully.');
        } elseif ($attrs['submit'] === 'submit') {
            $this->pirepSvc->submit($pirep);
            Flash::success('PIREP submitted!');
        } elseif ($attrs['submit'] === 'delete' || $attrs['submit'] === 'cancel') {
            $this->pirepSvc->delete($pirep);
            Flash::success('PIREP deleted!');

            return redirect(route('frontend.pireps.index'));
        }

        return redirect(route('frontend.pireps.show', [$pirep->id]));
    }

    /**
     * Submit the PIREP
     *
     *
     * @throws Exception
     */
    public function submit(string $id, Request $request): RedirectResponse
    {
        $pirep = Pirep::find($id);
        if (empty($pirep)) {
            Flash::error('PIREP not found');

            return redirect(PirepResource::getUrl());
        }

        if ($pirep->user_id !== Auth::id()) {
            Flash::error('Cannot edit someone else\'s PIREP!');

            return redirect(PirepResource::getUrl());
        }

        $this->pirepSvc->submit($pirep);

        return redirect(route('frontend.pireps.show', [$pirep->id]));
    }
}
