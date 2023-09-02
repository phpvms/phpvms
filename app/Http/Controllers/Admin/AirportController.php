<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\Controller;
use App\Http\Controllers\Admin\Traits\Importable;
use App\Http\Requests\CreateAirportRequest;
use App\Http\Requests\UpdateAirportRequest;
use App\Models\Airport;
use App\Models\Enums\ImportExportType;
use App\Models\Expense;
use App\Repositories\AirportRepository;
use App\Repositories\Criteria\WhereCriteria;
use App\Services\ExportService;
use App\Services\FileService;
use App\Services\ImportService;
use App\Support\Timezonelist;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Laracasts\Flash\Flash;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AirportController extends Controller
{
    use Importable;

    /**
     * @param AirportRepository $airportRepo
     * @param FileService       $fileSvc
     * @param ImportService     $importSvc
     */
    public function __construct(
        private readonly AirportRepository $airportRepo,
        private readonly FileService $fileSvc,
        private readonly ImportService $importSvc,
    ) {
    }

    /**
     * Display a listing of the Airport.
     *
     * @param Request $request
     *
     * @throws \Prettus\Repository\Exceptions\RepositoryException
     *
     * @return View
     */
    public function index(Request $request): View
    {
        $where = [];
        if ($request->has('icao')) {
            $where['icao'] = $request->get('icao');
        }

        $this->airportRepo->pushCriteria(new WhereCriteria($request, $where));
        $airports = $this->airportRepo
            ->orderBy('icao', 'asc')
            ->paginate();

        return view('admin.airports.index', [
            'airports' => $airports,
        ]);
    }

    /**
     * Show the form for creating a new Airport.
     *
     * @return View
     */
    public function create(): View
    {
        return view('admin.airports.create', [
            'timezones' => Timezonelist::toArray(),
        ]);
    }

    /**
     * Store a newly created Airport in storage.
     *
     * @param CreateAirportRequest $request
     *
     * @throws \Prettus\Validator\Exceptions\ValidatorException
     *
     * @return RedirectResponse
     */
    public function store(CreateAirportRequest $request): RedirectResponse
    {
        $input = $request->all();
        $input['hub'] = get_truth_state($input['hub']);

        $this->airportRepo->create($input);

        Flash::success('Airport saved successfully.');
        return redirect(route('admin.airports.index'));
    }

    /**
     * Display the specified Airport.
     *
     * @param string $id
     *
     * @return View
     */
    public function show(string $id): View
    {
        $airport = $this->airportRepo->findWithoutFail($id);

        if (empty($airport)) {
            Flash::error('Airport not found');
            return redirect(route('admin.airports.index'));
        }

        return view('admin.airports.show', [
            'airport' => $airport,
        ]);
    }

    /**
     * Show the form for editing the specified Airport.
     *
     * @param string $id
     *
     * @return View
     */
    public function edit(string $id): View
    {
        $airport = $this->airportRepo->findWithoutFail($id);

        if (empty($airport)) {
            Flash::error('Airport not found');
            return redirect(route('admin.airports.index'));
        }

        return view('admin.airports.edit', [
            'timezones' => Timezonelist::toArray(),
            'airport'   => $airport,
        ]);
    }

    /**
     * Update the specified Airport in storage.
     *
     * @param string               $id
     * @param UpdateAirportRequest $request
     *
     * @throws \Prettus\Validator\Exceptions\ValidatorException
     *
     * @return RedirectResponse
     */
    public function update(string $id, UpdateAirportRequest $request): RedirectResponse
    {
        $airport = $this->airportRepo->findWithoutFail($id);

        if (empty($airport)) {
            Flash::error('Airport not found');
            return redirect(route('admin.airports.index'));
        }

        $attrs = $request->all();
        $attrs['hub'] = get_truth_state($attrs['hub']);

        $this->airportRepo->update($attrs, $id);

        Flash::success('Airport updated successfully.');
        return redirect(route('admin.airports.index'));
    }

    /**
     * Remove the specified Airport from storage.
     *
     * @param string $id
     *
     * @return RedirectResponse
     */
    public function destroy(string $id): RedirectResponse
    {
        $airport = $this->airportRepo->findWithoutFail($id);

        if (empty($airport)) {
            Flash::error('Airport not found');
            return redirect(route('admin.airports.index'));
        }

        foreach ($airport->files as $file) {
            $this->fileSvc->removeFile($file);
        }

        $this->airportRepo->delete($id);

        Flash::success('Airport deleted successfully.');
        return redirect(route('admin.airports.index'));
    }

    /**
     * Run the airport exporter
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
        $airports = $this->airportRepo->all();

        $path = $exporter->exportAirports($airports);
        return response()
            ->download($path, 'airports.csv', [
                'content-type' => 'text/csv',
            ])
            ->deleteFileAfterSend(true);
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
            $logs = $this->importFile($request, ImportExportType::AIRPORT);
        }

        return view('admin.airports.import', [
            'logs' => $logs,
        ]);
    }

    /**
     * @param Airport $airport
     *
     * @return View
     */
    protected function return_expenses_view(Airport $airport): View
    {
        $airport->refresh();
        return view('admin.airports.expenses', [
            'airport' => $airport,
        ]);
    }

    /**
     * Operations for associating ranks to the subfleet
     *
     * @param string  $id
     * @param Request $request
     *
     * @throws \Exception
     *
     * @return View
     */
    public function expenses(string $id, Request $request): View
    {
        $airport = $this->airportRepo->findWithoutFail($id);
        if (empty($airport)) {
            return $this->return_expenses_view($airport);
        }

        if ($request->isMethod('get')) {
            return $this->return_expenses_view($airport);
        }

        if ($request->isMethod('post')) {
            $expense = new Expense($request->post());
            $expense->ref_model = Airport::class;
            $expense->ref_model_id = $airport->id;
            $expense->save();
        } elseif ($request->isMethod('put')) {
            $expense = Expense::findOrFail($request->input('expense_id'));
            $expense->{$request->name} = $request->value;
            $expense->save();
        } // dissassociate fare from teh aircraft
        elseif ($request->isMethod('delete')) {
            $expense = Expense::findOrFail($request->input('expense_id'));
            $expense->delete();
        }

        return $this->return_expenses_view($airport);
    }

    /**
     * Set fuel prices for this airport
     *
     * @param Request $request
     *
     * @return RedirectResponse|null
     */
    public function fuel(Request $request): RedirectResponse|null
    {
        $id = $request->id;

        $airport = $this->airportRepo->findWithoutFail($id);
        if (empty($airport)) {
            Flash::error('Flight not found');

            return redirect(route('admin.flights.index'));
        }

        // add aircraft to flight
        if ($request->isMethod('put')) {
            $airport->{$request->name} = $request->value;
        }

        $airport->save();
        return null;
    }
}
