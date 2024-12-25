<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\Controller;
use App\Http\Controllers\Admin\Traits\Importable;
use App\Http\Requests\CreateSubfleetRequest;
use App\Http\Requests\UpdateSubfleetRequest;
use App\Models\Airline;
use App\Models\Airport;
use App\Models\Enums\FareType;
use App\Models\Enums\FuelType;
use App\Models\Enums\ImportExportType;
use App\Models\Expense;
use App\Models\Subfleet;
use App\Repositories\AircraftRepository;
use App\Repositories\FareRepository;
use App\Repositories\RankRepository;
use App\Repositories\SubfleetRepository;
use App\Repositories\TypeRatingRepository;
use App\Services\ExportService;
use App\Services\FareService;
use App\Services\FileService;
use App\Services\FinanceService;
use App\Services\FleetService;
use App\Services\ImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Laracasts\Flash\Flash;
use Prettus\Repository\Criteria\RequestCriteria;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SubfleetController extends Controller
{
    use Importable;

    public function __construct(
        private readonly AircraftRepository $aircraftRepo,
        private readonly FareRepository $fareRepo,
        private readonly FareService $fareSvc,
        private readonly FileService $fileSvc,
        private readonly FleetService $fleetSvc,
        private readonly ImportService $importSvc,
        private readonly RankRepository $rankRepo,
        private readonly SubfleetRepository $subfleetRepo,
        private readonly TypeRatingRepository $typeratingRepo,
        private readonly FinanceService $financeSvc,
    ) {
    }

    /**
     * Display a listing of the Subfleet.
     *
     * @param Request $request
     *
     * @throws \Prettus\Repository\Exceptions\RepositoryException
     *
     * @return View
     */
    public function index(Request $request): View
    {
        $this->subfleetRepo->with(['airline'])->pushCriteria(new RequestCriteria($request));
        $subfleets = $this->subfleetRepo->sortable('name')->get();
        $trashed = $this->subfleetRepo->onlyTrashed()->orderBy('deleted_at', 'desc')->get();

        return view('admin.subfleets.index', [
            'subfleets' => $subfleets,
            'trashed'   => $trashed,
        ]);
    }

    /**
     * Recycle Bin operations, either restore or permanently delete the object
     */
    public function trashbin(Request $request)
    {
        $object_id = (isset($request->object_id)) ? $request->object_id : null;

        $subfleet = Subfleet::onlyTrashed()->where('id', $object_id)->first();
        $duplicate_check = Subfleet::where('type', $subfleet->type)->count();

        if ($object_id && $request->action === 'restore') {
            // Change the type id if it is used
            if ($duplicate_check > 0) {
                $subfleet->type = $subfleet->type.'_RESTORED';
                $subfleet->save();
            }
            $subfleet->restore();
            Flash::success('Subfleet RESTORED successfully.');
        } elseif ($object_id && $request->action === 'delete') {
            $subfleet->forceDelete();
            Flash::error('Subfleet DELETED PERMANENTLY.');
        } else {
            Flash::info('Nothing done!');
        }

        return back();
    }

    /**
     * Show the form for creating a new Subfleet.
     *
     * @return View
     */
    public function create(): View
    {
        return view('admin.subfleets.create', [
            'airlines'   => Airline::all()->pluck('name', 'id'),
            'airports'   => [],
            'hubs'       => Airport::where('hub', 1)->pluck('name', 'id'),
            'fuel_types' => FuelType::labels(),
        ]);
    }

    /**
     * Store a newly created Subfleet in storage.
     *
     * @param CreateSubfleetRequest $request
     *
     * @throws \Prettus\Validator\Exceptions\ValidatorException
     *
     * @return RedirectResponse
     */
    public function store(CreateSubfleetRequest $request): RedirectResponse
    {
        $input = $request->all();
        $subfleet = $this->subfleetRepo->create($input);
        Flash::success('Subfleet saved successfully.');

        return redirect(route('admin.subfleets.edit', [$subfleet->id]));
    }

    /**
     * Display the specified Subfleet.
     *
     * @param int $id
     *
     * @return RedirectResponse|View
     */
    public function show(int $id): RedirectResponse|View
    {
        $subfleet = $this->subfleetRepo
            ->with(['fares'])
            ->findWithoutFail($id);

        if (empty($subfleet)) {
            Flash::error('Subfleet not found');

            return redirect(route('admin.subfleets.index'));
        }

        $avail_fares = $this->getAvailFares($subfleet);

        return view('admin.subfleets.show', [
            'subfleet'    => $subfleet,
            'avail_fares' => $avail_fares,
        ]);
    }

    /**
     * Show the form for editing the specified Subfleet.
     *
     * @param int $id
     *
     * @return RedirectResponse|View
     */
    public function edit(int $id): RedirectResponse|View
    {
        /** @var Subfleet $subfleet */
        $subfleet = $this->subfleetRepo
            ->with(['home', 'fares', 'ranks', 'typeratings'])
            ->findWithoutFail($id);

        if (empty($subfleet)) {
            Flash::error('Subfleet not found');

            return redirect(route('admin.subfleets.index'));
        }

        $avail_fares = $this->getAvailFares($subfleet);
        $avail_ranks = $this->getAvailRanks($subfleet);
        $avail_ratings = $this->getAvailTypeRatings($subfleet);

        $airports = [];
        if ($subfleet->home) {
            $airports[$subfleet->home->id] = $subfleet->home->description;
        }

        return view('admin.subfleets.edit', [
            'airlines'      => Airline::all()->pluck('name', 'id'),
            'airports'      => $airports,
            'fuel_types'    => FuelType::labels(),
            'avail_fares'   => $avail_fares,
            'avail_ranks'   => $avail_ranks,
            'avail_ratings' => $avail_ratings,
            'subfleet'      => $subfleet,
        ]);
    }

    /**
     * Update the specified Subfleet in storage.
     *
     * @param int                   $id
     * @param UpdateSubfleetRequest $request
     *
     * @throws \Prettus\Validator\Exceptions\ValidatorException
     *
     * @return RedirectResponse
     */
    public function update(int $id, UpdateSubfleetRequest $request): RedirectResponse
    {
        $subfleet = $this->subfleetRepo->findWithoutFail($id);

        if (empty($subfleet)) {
            Flash::error('Subfleet not found');

            return redirect(route('admin.subfleets.index'));
        }

        $this->subfleetRepo->update($request->all(), $id);
        Flash::success('Subfleet updated successfully.');

        return redirect(route('admin.subfleets.index'));
    }

    /**
     * Remove the specified Subfleet from storage.
     *
     * @param int $id
     *
     * @return RedirectResponse
     */
    public function destroy(int $id): RedirectResponse
    {
        $subfleet = $this->subfleetRepo->findWithoutFail($id);

        if (empty($subfleet)) {
            Flash::error('Subfleet not found');

            return redirect(route('admin.subfleets.index'));
        }

        // Make sure no aircraft are assigned to this subfleet
        // before trying to delete it, or else things might go boom
        $aircraft = $this->aircraftRepo->findWhere(['subfleet_id' => $id], ['id']);
        if ($aircraft->count() > 0) {
            Flash::error('There are aircraft still assigned to this subfleet, you can\'t delete it!')->important();

            return redirect(route('admin.subfleets.index'));
        }

        foreach ($subfleet->files as $file) {
            $this->fileSvc->removeFile($file);
        }

        $this->subfleetRepo->delete($id);
        Flash::success('Subfleet deleted successfully.');

        return redirect(route('admin.subfleets.index'));
    }

    /**
     * Run the subfleet exporter
     *
     * @param Request $request
     *
     * @return BinaryFileResponse
     */
    public function export(Request $request): BinaryFileResponse
    {
        $exporter = app(ExportService::class);
        $subfleets = $this->subfleetRepo->all();

        $path = $exporter->exportSubfleets($subfleets);

        return response()->download($path, 'subfleets.csv', ['content-type' => 'text/csv'])->deleteFileAfterSend(true);
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
            $logs = $this->importFile($request, ImportExportType::SUBFLEETS);
        }

        return view('admin.subfleets.import', [
            'logs' => $logs,
        ]);
    }

    /**
     * Get all the fares that haven't been assigned to a given subfleet
     *
     * @param Subfleet $subfleet
     *
     * @return array
     */
    protected function getAvailFares(Subfleet $subfleet): array
    {
        $retval = [];
        $all_fares = $this->fareRepo->all();
        $avail_fares = $all_fares->except($subfleet->fares->modelKeys());
        foreach ($avail_fares as $fare) {
            $retval[$fare->id] = $fare->name.
                ' (price: '.$fare->price.
                ', type: '.FareType::label($fare->type).
                ', cost: '.$fare->cost.
                ', capacity: '.$fare->capacity.')';
        }

        return $retval;
    }

    /**
     * Get the ranks that are available to the subfleet
     *
     * @param Subfleet $subfleet
     *
     * @return array
     */
    protected function getAvailRanks(Subfleet $subfleet): array
    {
        $retval = [];
        $all_ranks = $this->rankRepo->all();
        $avail_ranks = $all_ranks->except($subfleet->ranks->modelKeys());
        foreach ($avail_ranks as $rank) {
            $retval[$rank->id] = $rank->name;
        }

        return $retval;
    }

    /**
     * Get the type ratings that are available to the subfleet
     *
     * @param Subfleet $subfleet
     *
     * @return array
     */
    protected function getAvailTypeRatings(Subfleet $subfleet): array
    {
        $retval = [];
        $all_ratings = $this->typeratingRepo->all();
        $avail_ratings = $all_ratings->except($subfleet->typeratings->modelKeys());
        foreach ($avail_ratings as $tr) {
            $retval[$tr->id] = $tr->name.' ('.$tr->type.')';
        }

        return $retval;
    }

    /**
     * @param ?Subfleet $subfleet
     *
     * @return mixed
     */
    protected function return_expenses_view(?Subfleet $subfleet): View
    {
        $subfleet->refresh();
        return view('admin.subfleets.expenses', [
            'subfleet' => $subfleet,
        ]);
    }

    /**
     * @param ?Subfleet $subfleet
     *
     * @return View
     */
    protected function return_fares_view(?Subfleet $subfleet): View
    {
        $subfleet->refresh();
        $avail_fares = $this->getAvailFares($subfleet);

        return view('admin.subfleets.fares', [
            'subfleet'    => $subfleet,
            'avail_fares' => $avail_fares,
        ]);
    }

    /**
     * @param ?Subfleet $subfleet
     *
     * @return View
     */
    protected function return_ranks_view(?Subfleet $subfleet): View
    {
        $subfleet->refresh();
        $avail_ranks = $this->getAvailRanks($subfleet);

        return view('admin.subfleets.ranks', [
            'subfleet'    => $subfleet,
            'avail_ranks' => $avail_ranks,
        ]);
    }

    /**
     * @param ?Subfleet $subfleet
     *
     * @return View
     */
    protected function return_typeratings_view(?Subfleet $subfleet): View
    {
        $subfleet->refresh();
        $avail_ratings = $this->getAvailTypeRatings($subfleet);

        return view('admin.subfleets.type_ratings', [
            'subfleet'      => $subfleet,
            'avail_ratings' => $avail_ratings,
        ]);
    }

    /**
     * Operations for associating ranks to the subfleet
     *
     * @param int     $id
     * @param Request $request
     *
     * @throws \Exception
     *
     * @return View
     */
    public function expenses(int $id, Request $request): View
    {
        /** @var Subfleet $subfleet */
        $subfleet = $this->subfleetRepo->findWithoutFail($id);
        if (empty($subfleet)) {
            return $this->return_expenses_view($subfleet);
        }

        if ($request->isMethod('get')) {
            return $this->return_expenses_view($subfleet);
        }

        /*
         * update specific rank data
         */
        if ($request->isMethod('post')) {
            $this->financeSvc->addExpense(
                $request->post(),
                $subfleet,
                $subfleet->airline_id
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

        return $this->return_expenses_view($subfleet);
    }

    /**
     * Operations on fares to the subfleet
     *
     * @param int     $id
     * @param Request $request
     *
     * @return View
     */
    public function fares(int $id, Request $request): View
    {
        $subfleet = $this->subfleetRepo->findWithoutFail($id);
        if (empty($subfleet)) {
            return $this->return_fares_view($subfleet);
        }

        if ($request->isMethod('get')) {
            return $this->return_fares_view($subfleet);
        }

        /*
         * update specific fare data
         */
        if ($request->isMethod('post')) {
            $fare = $this->fareRepo->find($request->fare_id);
            $this->fareSvc->setForSubfleet($subfleet, $fare);
        } // update the pivot table with overrides for the fares
        elseif ($request->isMethod('put')) {
            $override = [];
            $fare = $this->fareRepo->find($request->fare_id);
            $override[$request->name] = $request->value;
            $this->fareSvc->setForSubfleet($subfleet, $fare, $override);
        } // dissassociate fare from teh aircraft
        elseif ($request->isMethod('delete')) {
            $fare = $this->fareRepo->find($request->fare_id);
            $this->fareSvc->delFareFromSubfleet($subfleet, $fare);
        }

        return $this->return_fares_view($subfleet);
    }

    /**
     * Operations for associating ranks to the subfleet
     *
     * @param int     $id
     * @param Request $request
     *
     * @return View
     */
    public function ranks(int $id, Request $request): View
    {
        $subfleet = $this->subfleetRepo->findWithoutFail($id);
        if (empty($subfleet)) {
            return $this->return_ranks_view($subfleet);
        }

        if ($request->isMethod('get')) {
            return $this->return_ranks_view($subfleet);
        }

        // associate rank with the subfleet
        if ($request->isMethod('post')) {
            foreach ($request->input('rank_ids') as $rank_id) {
                $rank = $this->rankRepo->find($rank_id);
                $this->fleetSvc->addSubfleetToRank($subfleet, $rank);
            }
        } // override definitions
        elseif ($request->isMethod('put')) {
            $override = [];
            $rank = $this->rankRepo->find($request->input('rank_id'));
            $override[$request->name] = $request->value;

            $this->fleetSvc->addSubfleetToRank($subfleet, $rank, $override);
        } // dissassociate rank from the subfleet
        elseif ($request->isMethod('delete')) {
            $rank = $this->rankRepo->find($request->input('rank_id'));
            $this->fleetSvc->removeSubfleetFromRank($subfleet, $rank);
        }

        $subfleet->save();

        return $this->return_ranks_view($subfleet);
    }

    /**
     * Operations for associating type ratings to the subfleet
     *
     * @param int     $id
     * @param Request $request
     *
     * @return View
     */
    public function typeratings(int $id, Request $request): View
    {
        $subfleet = $this->subfleetRepo->findWithoutFail($id);
        if (empty($subfleet)) {
            return $this->return_typeratings_view($subfleet);
        }

        if ($request->isMethod('get')) {
            return $this->return_typeratings_view($subfleet);
        }

        // associate subfleet with type rating
        if ($request->isMethod('post')) {
            $typerating = $this->typeratingRepo->find($request->input('typerating_id'));
            $this->fleetSvc->addSubfleetToTypeRating($subfleet, $typerating);
        } // dissassociate subfleet from the type rating
        elseif ($request->isMethod('delete')) {
            $typerating = $this->typeratingRepo->find($request->input('typerating_id'));
            $this->fleetSvc->removeSubfleetFromTypeRating($subfleet, $typerating);
        }

        $subfleet->save();

        return $this->return_typeratings_view($subfleet);
    }
}
