<?php

namespace App\Http\Controllers\Frontend;

use App\Contracts\Controller;
use App\Enums\PirepFieldSource;
use App\Enums\PirepSource;
use App\Enums\PirepState;
use App\Features\Hooks\Points\App\Slots\SlotPirepDetail;
use App\Features\Pireps\Actions\ListFilingAircraft;
use App\Features\Pireps\Actions\PirepFilingDenied;
use App\Features\Pireps\Actions\SaveManualPirep;
use App\Filament\Resources\Pireps\PirepResource;
use App\Http\Data\PirepData;
use App\Http\Data\PirepListItemData;
use App\Http\Requests\CreatePirepRequest;
use App\Http\Requests\SearchPirepsRequest;
use App\Http\Requests\UpdatePirepRequest;
use App\Models\Aircraft;
use App\Models\Airline;
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
use App\Support\Units\Time;
use Closure;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Inertia\Response as InertiaResponse;
use Laracasts\Flash\Flash;

class PirepController extends Controller
{
    public function __construct(
        private readonly FareService $fareSvc,
        private readonly GeoService $geoSvc,
        private readonly PirepSearchQuery $pirepSearchQuery,
        private readonly PirepService $pirepSvc,
        private readonly ListFilingAircraft $filingAircraft,
        private readonly SaveManualPirep $saveManualPirep,
    ) {
        $this->middleware(function (Request $request, Closure $next) {
            abort_if(setting('pireps.disable_manual', false), 405, 'Manual PIREP filing is disabled.');

            return $next($request);
        })->only(['create', 'store']);
    }

    /**
     * Dropdown with aircraft grouped by subfleet
     */
    public function aircraftList(bool $add_blank = false): array
    {
        return $this->filingAircraft->handle(Auth::user(), $add_blank);
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
        $pirep->loadMissing('aircraft.subfleet.fares');
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

    public function index(SearchPirepsRequest $request): View|InertiaResponse
    {
        $user = Auth::user();

        $where = [
            ['user_id', '=', $user->id],
            ['state', '<>', PirepState::CANCELLED],
        ];

        // Support retrieval of deleted relationships
        $with = [
            'aircraft'    => fn ($query) => $query->withTrashed(),
            'airline'     => fn ($query) => $query->withTrashed(),
            'arr_airport' => fn ($query) => $query->withTrashed(),
            'comments',
            'dpt_airport' => fn ($query) => $query->withTrashed(),
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

        $perPage = paginate_limit($request->integer('limit') ?: null);
        /** @var LengthAwarePaginator<int, Pirep> $pireps */
        $pireps = $query->paginate($perPage);

        // Blade gets the paginator (model-rich) verbatim; the SPA gets flat,
        // typed PirepListItemData built lazily from the same page of models.
        return response()->themed(
            'Pireps/Index',
            'pireps.index',
            bladeData: [
                'user'   => $user,
                'pireps' => $pireps,
            ],
            spa: fn (): array => [
                'pireps' => collect($pireps->items())
                    ->map(fn (Pirep $p): PirepListItemData => PirepListItemData::fromModel($p))
                    ->all(),
                'pagination' => [
                    'currentPage' => $pireps->currentPage(),
                    'lastPage'    => $pireps->lastPage(),
                    'total'       => $pireps->total(),
                    // UPagination derives its page count from total/perPage, so
                    // the page size has to travel with the rest of the metadata.
                    'perPage' => $pireps->perPage(),
                ],
            ],
        );
    }

    public function show(string $id): RedirectResponse|View|InertiaResponse
    {
        // Support retrieval of deleted relationships
        $with = [
            'acars_logs',
            'field_values', // the `fields` accessor (custom PIREP fields) needs this loaded
            'events',
            'aircraft'    => fn ($query) => $query->withTrashed()->with(['airline' => fn ($query) => $query->withTrashed()]),
            'airline'     => fn ($query) => $query->withTrashed()->with('journal'),
            'arr_airport' => fn ($query) => $query->withTrashed(),
            'comments',
            'dpt_airport' => fn ($query) => $query->withTrashed(),
            'fares',
            'simbrief',
            'transactions',
            'user' => fn ($query) => $query->withTrashed()->with(['rank' => fn ($query) => $query->withTrashed()]),
        ];

        $pirep = Pirep::with($with)->find($id);
        if (empty($pirep)) {
            Flash::error('Pirep not found');

            return redirect(route('frontend.pireps.index'));
        }

        $map_features = $this->geoSvc->pirepGeoJson($pirep);

        return response()->themed(
            'Pireps/Show',
            'pireps.show',
            bladeData: [
                'pirep'        => $pirep,
                'map_features' => $map_features,
                'user'         => Auth::user(),
            ],
            spa: fn (): array => ['pirep' => PirepData::fromModel($pirep)],
            renderHooks: fn (): array => [new SlotPirepDetail($pirep)],
        );
    }

    /**
     * Return the fares form for a given aircraft
     */
    public function fares(Request $request): View
    {
        $aircraft_id = $request->input('aircraft_id');
        $aircraft = Aircraft::with('subfleet.fares')->findOrFail($aircraft_id);

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
            $simbrief = SimBrief::with('aircraft.subfleet.fares')->where('user_id', Auth::id())->findOrFail($simbrief_id);
            $pirep = Pirep::fromSimBrief($simbrief);

            $aircraft = $simbrief->aircraft;
            $aircraft_list[$aircraft->subfleet->name] = [];
            $aircraft_list[$aircraft->subfleet->name][$aircraft->id] = $aircraft->name.' - '.$aircraft->registration;

            // Convert the fare data into the expected output format
            if (!empty($simbrief->fare_data)) {
                $fare_values = json_decode((string) $simbrief->fare_data, true);
                $fares = [];
                $fare_data = json_decode((string) $simbrief->fare_data, true);
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

        $submit = strtolower((string) $request->input('submit'));

        try {
            $pirep = $this->saveManualPirep->create($user, $request->post(), $submit, $request->input('sb_id'));
        } catch (PirepFilingDenied $pirepFilingDenied) {
            return $this->flashError($pirepFilingDenied->getMessage(), 'frontend.pireps.create');
        }

        if ($submit === 'save') {
            Flash::success('PIREP saved successfully.');
        } elseif ($submit === 'submit') {
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
        $pirep = Pirep::with(['dpt_airport', 'arr_airport', 'alt_airport', 'fares'])->find($id);

        if (!$pirep) {
            Flash::error('Pirep not found');

            return redirect(route('frontend.pireps.index'));
        }

        if ($pirep->user_id !== Auth::id()) {
            Flash::error("Cannot edit someone else's PIREP!");

            return redirect(PirepResource::getUrl());
        }

        if ($pirep->aircraft) {
            $pirep->aircraft->loadMissing('subfleet.fares');
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
            $field->slug ??= Str::slug($field->name);

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
            Flash::error("Cannot edit someone else's PIREP!");

            return redirect(PirepResource::getUrl());
        }

        $outcome = $this->saveManualPirep->update($pirep, $request->all(), (string) $request->input('submit'));

        if ($outcome === 'save') {
            Flash::success('PIREP saved successfully.');
        } elseif ($outcome === 'submit') {
            Flash::success('PIREP submitted!');
        } elseif ($outcome === 'delete' || $outcome === 'cancel') {
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
            Flash::error("Cannot edit someone else's PIREP!");

            return redirect(PirepResource::getUrl());
        }

        $this->pirepSvc->submit($pirep);

        return redirect(route('frontend.pireps.show', [$pirep->id]));
    }
}
