<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\Controller;
use App\Http\Controllers\Admin\Traits\Importable;
use App\Http\Requests\CreateAircraftRequest;
use App\Http\Requests\UpdateAircraftRequest;
use App\Models\Aircraft;
use App\Models\Enums\AircraftStatus;
use App\Models\Enums\ImportExportType;
use App\Models\Expense;
use App\Models\Subfleet;
use App\Repositories\AircraftRepository;
use App\Repositories\AirportRepository;
use App\Services\ExportService;
use App\Services\FileService;
use App\Services\FinanceService;
use App\Services\ImportService;
use App\Support\Units\Mass;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Laracasts\Flash\Flash;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AircraftController extends Controller
{
    use Importable;

    public function __construct(
        private readonly AirportRepository $airportRepo,
        private readonly AircraftRepository $aircraftRepo,
        private readonly FileService $fileSvc,
        private readonly ImportService $importSvc,
        private readonly FinanceService $financeSvc,
    ) {
    }

    /**
     * Display a listing of the Aircraft.
     *
     * @param Request $request
     *
     * @return View
     */
    public function index(Request $request): View
    {
        // If subfleet ID is passed part of the query string, then only
        // show the aircraft that are in that subfleet
        $w = [];
        if ($request->filled('subfleet')) {
            $w['subfleet_id'] = $request->input('subfleet');
        }

        $aircraft = $this->aircraftRepo->with(['subfleet'])->where($w)->sortable('registration')->get();
        $trashed = $this->aircraftRepo->onlyTrashed()->orderBy('deleted_at', 'desc')->get();

        return view('admin.aircraft.index', [
            'aircraft'    => $aircraft,
            'subfleet_id' => $request->input('subfleet'),
            'trashed'     => $trashed,
        ]);
    }

    /**
     * Recycle Bin operations, either restore or permanently delete the object
     */
    public function trashbin(Request $request)
    {
        $object_id = (isset($request->object_id)) ? $request->object_id : null;

        $aircraft = Aircraft::onlyTrashed()->withCount('pireps')->where('id', $object_id)->first();

        if ($object_id && $request->action === 'restore') {
            $aircraft->restore();
            Flash::success('Aircraft RESTORED successfully.');
        } elseif ($object_id && $request->action === 'delete') {
            // Check if the aircraft is used or not
            if ($aircraft->pireps_count > 0) {
                Flash::info('Can not delete aircraft, it is used in pireps');
            } else {
                $aircraft->forceDelete();
                Flash::error('Aircraft DELETED PERMANENTLY.');
            }
        } else {
            Flash::info('Nothing done!');
        }

        return back();
    }

    /**
     * Show the form for creating a new Aircraft.
     *
     * @param Request $request
     *
     * @return View
     */
    public function create(Request $request): View
    {
        return view('admin.aircraft.create', [
            'airports'    => [],
            'hubs'        => [],
            'subfleets'   => Subfleet::all()->pluck('name', 'id'),
            'statuses'    => AircraftStatus::select(false),
            'subfleet_id' => $request->query('subfleet'),
        ]);
    }

    /**
     * Store a newly created Aircraft in storage.
     *
     * @param \App\Http\Requests\CreateAircraftRequest $request
     *
     * @throws \Prettus\Validator\Exceptions\ValidatorException
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(CreateAircraftRequest $request): RedirectResponse
    {
        $attrs = $request->all();
        // Set the correct mass units
        $attrs['dow'] = (filled($attrs['dow']) && $attrs['dow'] > 0) ? Mass::make((float) $request->input('dow'), setting('units.weight')) : null;
        $attrs['zfw'] = (filled($attrs['zfw']) && $attrs['zfw'] > 0) ? Mass::make((float) $request->input('zfw'), setting('units.weight')) : null;
        $attrs['mtow'] = (filled($attrs['mtow']) && $attrs['mtow'] > 0) ? Mass::make((float) $request->input('mtow'), setting('units.weight')) : null;
        $attrs['mlw'] = (filled($attrs['mlw']) &&  $attrs['mlw'] > 0) ? Mass::make((float) $request->input('mlw'), setting('units.weight')) : null;

        $aircraft = $this->aircraftRepo->create($attrs);

        Flash::success('Aircraft saved successfully.');
        return redirect(route('admin.aircraft.edit', [$aircraft->id]));
    }

    /**
     * Display the specified Aircraft.
     *
     * @param mixed $id
     *
     * @return View
     */
    public function show($id): View
    {
        $aircraft = $this->aircraftRepo->findWithoutFail($id);

        if (empty($aircraft)) {
            Flash::error('Aircraft not found');
            return redirect(route('admin.aircraft.index'));
        }

        return view('admin.aircraft.show', [
            'aircraft' => $aircraft,
        ]);
    }

    /**
     * Show the form for editing the specified Aircraft.
     *
     * @param int $id
     *
     * @return View|RedirectResponse
     */
    public function edit(int $id): View|RedirectResponse
    {
        /** @var Aircraft $aircraft */
        $aircraft = $this->aircraftRepo
            ->with(['airport', 'hub'])
            ->findWithoutFail($id);

        if (empty($aircraft)) {
            Flash::error('Aircraft not found');
            return redirect(route('admin.aircraft.index'));
        }

        $airports = ['' => ''];
        if ($aircraft->airport) {
            $airports[$aircraft->airport->id] = $aircraft->airport->description;
        }

        if ($aircraft->hub) {
            $airports[$aircraft->hub->id] = $aircraft->hub->description;
        }

        return view('admin.aircraft.edit', [
            'aircraft'  => $aircraft,
            'airports'  => $airports,
            'hubs'      => $airports,
            'subfleets' => Subfleet::all()->pluck('name', 'id'),
            'statuses'  => AircraftStatus::select(false),
        ]);
    }

    /**
     * Update the specified Aircraft in storage.
     *
     * @param int                   $id
     * @param UpdateAircraftRequest $request
     *
     * @throws \Prettus\Validator\Exceptions\ValidatorException
     *
     * @return RedirectResponse
     */
    public function update(int $id, UpdateAircraftRequest $request): RedirectResponse
    {
        /** @var \App\Models\Aircraft $aircraft */
        $aircraft = $this->aircraftRepo->findWithoutFail($id);

        if (empty($aircraft)) {
            Flash::error('Aircraft not found');
            return redirect(route('admin.aircraft.index'));
        }

        $attrs = $request->all();
        // Set the correct mass units
        $attrs['dow'] = (filled($attrs['dow']) && $attrs['dow'] > 0) ? Mass::make((float) $request->input('dow'), setting('units.weight')) : null;
        $attrs['zfw'] = (filled($attrs['zfw']) && $attrs['zfw'] > 0) ? Mass::make((float) $request->input('zfw'), setting('units.weight')) : null;
        $attrs['mtow'] = (filled($attrs['mtow']) && $attrs['mtow'] > 0) ? Mass::make((float) $request->input('mtow'), setting('units.weight')) : null;
        $attrs['mlw'] = (filled($attrs['mlw']) &&  $attrs['mlw'] > 0) ? Mass::make((float) $request->input('mlw'), setting('units.weight')) : null;

        $this->aircraftRepo->update($attrs, $id);

        Flash::success('Aircraft updated successfully.');
        return redirect(route('admin.aircraft.index').'?subfleet='.$aircraft->subfleet_id);
    }

    /**
     * Remove the specified Aircraft from storage.
     *
     * @param int $id
     *
     * @return RedirectResponse
     */
    public function destroy(int $id): RedirectResponse
    {
        /** @var \App\Models\Aircraft $aircraft */
        $aircraft = $this->aircraftRepo->findWithoutFail($id);

        if (empty($aircraft)) {
            Flash::error('Aircraft not found');
            return redirect(route('admin.aircraft.index'));
        }

        foreach ($aircraft->files as $file) {
            $this->fileSvc->removeFile($file);
        }

        $this->aircraftRepo->delete($id);

        Flash::success('Aircraft deleted successfully.');
        return redirect(route('admin.aircraft.index'));
    }

    /**
     * Run the aircraft exporter
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
        $file_name = 'aircraft.csv';
        if ($request->input('subfleet')) {
            $subfleet_id = $request->input('subfleet');
            $where['subfleet_id'] = $subfleet_id;
            $file_name = 'aircraft-'.$subfleet_id.'.csv';
        }

        $aircraft = $this->aircraftRepo->where($where)->orderBy('registration')->get();

        $path = $exporter->exportAircraft($aircraft);
        return response()->download($path, $file_name, ['content-type' => 'text/csv'])->deleteFileAfterSend(true);
    }

    /**
     * @param Request $request
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
            $logs = $this->importFile($request, ImportExportType::AIRCRAFT);
        }

        return view('admin.aircraft.import', [
            'logs' => $logs,
        ]);
    }

    /**
     * @param Aircraft $aircraft
     *
     * @return View
     */
    protected function return_expenses_view(Aircraft $aircraft): View
    {
        $aircraft->refresh();

        return view('admin.aircraft.expenses', [
            'aircraft' => $aircraft,
        ]);
    }

    /**
     * Operations for associating ranks to the subfleet
     *
     * @param int     $id
     * @param Request $request
     *
     * @throws Exception
     *
     * @return View
     */
    public function expenses(int $id, Request $request): View
    {
        /** @var Aircraft $aircraft */
        $aircraft = $this->aircraftRepo->with('airline')->findWithoutFail($id);
        if (empty($aircraft)) {
            return $this->return_expenses_view($aircraft);
        }

        if ($request->isMethod('get')) {
            return $this->return_expenses_view($aircraft);
        }

        if ($request->isMethod('post')) {
            $this->financeSvc->addExpense(
                $request->post(),
                $aircraft,
                $aircraft->airline->id
            );
        } elseif ($request->isMethod('put')) {
            $expense = Expense::findOrFail($request->input('expense_id'));
            $expense->{$request->name} = $request->value;
            $expense->save();
        } // dissassociate fare from teh aircraft
        elseif ($request->isMethod('delete')) {
            $expense = Expense::findOrFail($request->input('expense_id'));
            $expense->delete();
        }

        return $this->return_expenses_view($aircraft);
    }
}
