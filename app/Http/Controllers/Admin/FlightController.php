<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\Controller;
use App\Http\Controllers\Admin\Traits\Importable;
use App\Http\Requests\CreateFlightRequest;
use App\Http\Requests\UpdateFlightRequest;
use App\Models\Enums\FlightType;
use App\Models\Enums\ImportExportType;
use App\Models\Flight;
use App\Models\FlightField;
use App\Models\FlightFieldValue;
use App\Repositories\AirlineRepository;
use App\Repositories\AirportRepository;
use App\Repositories\FareRepository;
use App\Repositories\FlightFieldRepository;
use App\Repositories\FlightRepository;
use App\Repositories\SubfleetRepository;
use App\Services\ExportService;
use App\Services\FareService;
use App\Services\FleetService;
use App\Services\FlightService;
use App\Services\ImportService;
use App\Support\Units\Time;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Laracasts\Flash\Flash;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FlightController extends Controller
{
    use Importable;

    /**
     * FlightController constructor.
     *
     * @param AirlineRepository     $airlineRepo
     * @param AirportRepository     $airportRepo
     * @param FareRepository        $fareRepo
     * @param FlightRepository      $flightRepo
     * @param FlightFieldRepository $flightFieldRepo
     * @param FareService           $fareSvc
     * @param FlightService         $flightSvc
     * @param ImportService         $importSvc
     * @param SubfleetRepository    $subfleetRepo
     */
    public function __construct(
        private readonly AirlineRepository $airlineRepo,
        private readonly AirportRepository $airportRepo,
        private readonly FareRepository $fareRepo,
        private readonly FlightRepository $flightRepo,
        private readonly FlightFieldRepository $flightFieldRepo,
        private readonly FareService $fareSvc,
        private readonly FlightService $flightSvc,
        private readonly ImportService $importSvc,
        private readonly SubfleetRepository $subfleetRepo
    ) {
    }

    /**
     * Save any custom fields found
     *
     * @param Flight  $flight
     * @param Request $request
     */
    protected function saveCustomFields(Flight $flight, Request $request): void
    {
        $custom_fields = [];
        $flight_fields = FlightField::all();
        foreach ($flight_fields as $field) {
            if (!$request->filled($field->slug)) {
                continue;
            }

            $custom_fields[] = [
                'name'  => $field->name,
                'value' => $request->input($field->slug),
            ];
        }

        Log::info('PIREP Custom Fields', $custom_fields);
        $this->flightSvc->updateCustomFields($flight, $custom_fields);
    }

    /**
     * @param $flight
     *
     * @return array
     */
    protected function getAvailSubfleets(Flight $flight): array
    {
        $retval = [];

        $flight->refresh();
        $all_aircraft = $this->subfleetRepo->all();
        $avail_fleets = $all_aircraft->except($flight->subfleets->modelKeys());

        foreach ($avail_fleets as $ac) {
            $retval[$ac->id] = '['.$ac->airline->icao.']&nbsp;'.$ac->type.' - '.$ac->name;
        }

        return $retval;
    }

    /**
     * @param Request $request
     *
     * @throws \Prettus\Repository\Exceptions\RepositoryException
     *
     * @return View
     */
    public function index(Request $request): View
    {
        $flights = $this->flightRepo
            ->with(['dpt_airport', 'arr_airport', 'alt_airport', 'airline', 'subfleets'])
            ->withCount(['subfleets', 'fares'])
            ->searchCriteria($request, false)
            ->whereNull('owner_type')
            ->sortable('flight_number')->orderBy('route_code')->orderBy('route_leg')
            ->paginate();

        return view('admin.flights.index', [
            'flights'  => $flights,
            'airlines' => $this->airlineRepo->selectBoxList(true),
            'airports' => [],
        ]);
    }

    /**
     * Show the form for creating a new Flight.
     *
     * @return View
     */
    public function create(): View
    {
        return view('admin.flights.create', [
            'flight'        => null,
            'days'          => 0,
            'flight_fields' => $this->flightFieldRepo->all(),
            'airlines'      => $this->airlineRepo->selectBoxList(),
            'airports'      => [],
            'alt_airports'  => [],
            'flight_types'  => FlightType::select(false),
        ]);
    }

    /**
     * @param CreateFlightRequest $request
     *
     * @throws \Prettus\Validator\Exceptions\ValidatorException
     *
     * @return RedirectResponse
     */
    public function store(CreateFlightRequest $request): RedirectResponse
    {
        try {
            $fields = $request->all();
            $flight = $this->flightSvc->createFlight($fields);
            Flash::success('Flight saved successfully.');

            return redirect(route('admin.flights.edit', $flight->id));
        } catch (\Exception $e) {
            Log::error($e);
            Flash::error($e->getMessage());
            return redirect()->back()->withInput($request->all());
        }
    }

    /**
     * @param string $id
     *
     * @return RedirectResponse|View
     */
    public function show(string $id): RedirectResponse|View
    {
        $flight = $this->flightRepo->findWithoutFail($id);

        if (empty($flight)) {
            Flash::error('Flight not found');
            return redirect(route('admin.flights.index'));
        }

        $avail_subfleets = $this->getAvailSubfleets($flight);

        return view('admin.flights.show', [
            'flight'          => $flight,
            'flight_fields'   => $this->flightFieldRepo->all(),
            'avail_subfleets' => $avail_subfleets,
        ]);
    }

    /**
     * @param string $id
     *
     * @return RedirectResponse|View
     */
    public function edit(string $id): RedirectResponse|View
    {
        /** @var Flight $flight */
        $flight = $this->flightRepo
            ->with(['dpt_airport', 'arr_airport', 'alt_airport'])
            ->findWithoutFail($id);

        if (empty($flight)) {
            Flash::error('Flight not found');
            return redirect(route('admin.flights.index'));
        }

        $airports = [
            ['' => ''],
            [$flight->arr_airport->id => $flight->arr_airport->full_name],
            [$flight->dpt_airport->id => $flight->dpt_airport->full_name],
        ];

        if ($flight->alt_airport) {
            $airports[] = [$flight->alt_airport->id => $flight->alt_airport->full_name];
        }

        $time = new Time($flight->flight_time);

        $flight->hours = $time->hours;
        $flight->minutes = $time->minutes;

        return view('admin.flights.edit', [
            'flight'          => $flight,
            'days'            => $flight->days,
            'flight_fields'   => $this->flightFieldRepo->all(),
            'airlines'        => $this->airlineRepo->selectBoxList(),
            'airports'        => $airports,
            'alt_airports'    => $airports,
            'avail_fares'     => $this->getAvailFares($flight),
            'avail_subfleets' => $this->getAvailSubfleets($flight),
            'flight_types'    => FlightType::select(true),
        ]);
    }

    /**
     * @param string              $id
     * @param UpdateFlightRequest $request
     *
     * @throws \Prettus\Validator\Exceptions\ValidatorException
     *
     * @return RedirectResponse
     */
    public function update(string $id, UpdateFlightRequest $request): RedirectResponse
    {
        $flight = $this->flightRepo->findWithoutFail($id);

        if (empty($flight)) {
            Flash::error('Flight not found');
            return redirect(route('admin.flights.index'));
        }

        try {
            $this->flightSvc->updateFlight($flight, $request->all());
            Flash::success('Flight updated successfully.');

            return redirect(route('admin.flights.index'));
        } catch (\Exception $e) {
            Log::error($e);
            Flash::error($e->getMessage());
            return redirect()->back()->withInput($request->all());
        }
    }

    /**
     * @param string $id
     *
     * @throws \Exception
     *
     * @return RedirectResponse
     */
    public function destroy(string $id): RedirectResponse
    {
        $flight = $this->flightRepo->findWithoutFail($id);

        if (empty($flight)) {
            Flash::error('Flight not found');
            return redirect(route('admin.flights.index'));
        }

        $this->flightSvc->deleteFlight($flight);

        Flash::success('Flight deleted successfully.');
        return redirect(route('admin.flights.index'));
    }

    /**
     * Run the flight exporter
     *
     * @param Request $request
     *
     * @throws \League\Csv\Exception
     *
     * @return BinaryFileResponse
     */
    public function export(Request $request): BinaryFileResponse
    {
        $exporter = app(ExportService::class);

        $where = [];
        $file_name = 'flights.csv';
        if ($request->input('airline_id')) {
            $airline_id = $request->input('airline_id');
            $where['airline_id'] = $airline_id;
            $file_name = 'flights-'.$airline_id.'.csv';
        }
        $flights = $this->flightRepo->where($where)->orderBy('airline_id')->orderBy('flight_number')->orderBy('route_code')->orderBy('route_leg')->get();

        $path = $exporter->exportFlights($flights);
        return response()->download($path, $file_name, ['content-type' => 'text/csv'])->deleteFileAfterSend(true);
    }

    /**
     * @param Request $request
     *
     * @throws \Illuminate\Validation\ValidationException
     *
     * @return View
     */
    public function import(Request $request): View
    {
        $logs = [
            'success' => [],
            'errors'  => [],
        ];

        if ($request->isMethod('post')) {
            $logs = $this->importFile($request, ImportExportType::FLIGHTS);
        }

        return view('admin.flights.import', [
            'logs' => $logs,
        ]);
    }

    /**
     * @param Flight $flight
     *
     * @return View
     */
    protected function return_fields_view(Flight $flight): View
    {
        $flight->refresh();
        return view('admin.flights.flight_fields', [
            'flight'        => $flight,
            'flight_fields' => $this->flightFieldRepo->all(),
        ]);
    }

    /**
     * @param string  $flight_id
     * @param Request $request
     *
     * @return RedirectResponse|View
     */
    public function field_values(string $flight_id, Request $request): RedirectResponse|View
    {
        $flight = $this->flightRepo->findWithoutFail($flight_id);
        if (empty($flight)) {
            Flash::error('Flight not found');
            return redirect(route('admin.flights.index'));
        }

        // add custom field to flight
        if ($request->isMethod('post')) {
            Log::info('Adding new flight field, flight: '.$flight_id, $request->input());

            $field = new FlightFieldValue();
            $field->flight_id = $flight_id;
            $field->name = $request->input('name');
            $field->value = $request->input('value');
            $field->save();
        } elseif ($request->isMethod('put')) {
            Log::info('Updating flight field, flight: '.$flight_id, $request->input());
            $field = FlightFieldValue::where([
                'name'      => $request->input('name'),
                'flight_id' => $flight_id,
            ])->first();

            if (!$field) {
                Log::info('Field not found, creating new');
                $field = new FlightFieldValue();
                $field->name = $request->input('name');
            }

            $field->flight_id = $flight_id;
            $field->value = $request->input('value');
            $field->save();
        // update the field value
        } // remove custom field from flight
        elseif ($request->isMethod('delete')) {
            Log::info('Deleting flight field, flight: '.$flight_id, $request->input());
            if ($flight_id && $request->input('field_id')) {
                FlightFieldValue::destroy($request->input('field_id'));
            }
        }

        return $this->return_fields_view($flight);
    }

    /**
     * @param Flight $flight
     *
     * @return View
     */
    protected function return_subfleet_view(Flight $flight): View
    {
        $avail_subfleets = $this->getAvailSubfleets($flight);

        return view('admin.flights.subfleets', [
            'flight'          => $flight,
            'avail_subfleets' => $avail_subfleets,
        ]);
    }

    /**
     * @param string  $id
     * @param Request $request
     *
     * @return RedirectResponse|View
     */
    public function subfleets(string $id, Request $request): RedirectResponse|View
    {
        $flight = $this->flightRepo->findWithoutFail($id);
        if (empty($flight)) {
            Flash::error('Flight not found');
            return redirect(route('admin.flights.index'));
        }

        $fleetSvc = app(FleetService::class);

        if ($request->isMethod('post') && filled($request->subfleet_ids)) {
            // Add selected subfleets to flight
            foreach ($request->subfleet_ids as $sf) {
                $subfleet = $this->subfleetRepo->findWithoutFail($sf);
                if ($subfleet) {
                    $fleetSvc->addSubfleetToFlight($subfleet, $flight);
                }
            }
        } elseif ($request->isMethod('delete')) {
            // Delete the subfleet from flight
            $subfleet = $this->subfleetRepo->findWithoutFail($request->subfleet_id);
            if ($subfleet) {
                $fleetSvc->removeSubfleetFromFlight($subfleet, $flight);
            }
        }

        return $this->return_subfleet_view($flight);
    }

    /**
     * Get all the fares that haven't been assigned to a given subfleet
     *
     * @param Flight $flight
     *
     * @return array
     */
    protected function getAvailFares(Flight $flight): array
    {
        $retval = [];
        $all_fares = $this->fareRepo->all();
        $avail_fares = $all_fares->except($flight->fares->modelKeys());
        foreach ($avail_fares as $fare) {
            $retval[$fare->id] = $fare->name.' (base price: '.$fare->price.')';
        }

        return $retval;
    }

    /**
     * @param Flight $flight
     *
     * @return View
     */
    protected function return_fares_view(Flight $flight): View
    {
        $flight->refresh();

        return view('admin.flights.fares', [
            'flight'      => $flight,
            'avail_fares' => $this->getAvailFares($flight),
        ]);
    }

    /**
     * @param Request $request
     *
     * @return View
     */
    public function fares(Request $request): View
    {
        $id = $request->id;

        $flight = $this->flightRepo->findWithoutFail($id);
        if (empty($flight)) {
            return $this->return_fares_view($flight);
        }

        if ($request->isMethod('get')) {
            return $this->return_fares_view($flight);
        }

        if ($request->isMethod('delete')) {
            $fare = $this->fareRepo->findWithoutFail($request->fare_id);
            $this->fareSvc->delFareFromFlight($flight, $fare);

            return $this->return_fares_view($flight);
        }

        $this->validate($request, [
            'value' => 'nullable',  // regex:/([\d%]*)/
        ]);

        /*
         * update specific fare data
         */
        if ($request->isMethod('post')) {
            $fare = $this->fareRepo->findWithoutFail($request->fare_id);
            $this->fareSvc->setForFlight($flight, $fare);
        } // update the pivot table with overrides for the fares
        elseif ($request->isMethod('put')) {
            $override = [];
            $fare = $this->fareRepo->findWithoutFail($request->fare_id);
            $override[$request->name] = $request->value;
            $this->fareSvc->setForFlight($flight, $fare, $override);
        } // dissassociate fare from teh aircraft

        return $this->return_fares_view($flight);
    }
}
