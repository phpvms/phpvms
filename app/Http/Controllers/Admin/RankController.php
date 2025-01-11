<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\Controller;
use App\Http\Requests\CreateRankRequest;
use App\Http\Requests\UpdateRankRequest;
use App\Models\Rank;
use App\Repositories\RankRepository;
use App\Repositories\SubfleetRepository;
use App\Repositories\UserRepository;
use App\Services\FleetService;
use Cache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Laracasts\Flash\Flash;
use Prettus\Repository\Criteria\RequestCriteria;

class RankController extends Controller
{
    /**
     * RankController constructor.
     */
    public function __construct(
        private readonly FleetService $fleetSvc,
        private readonly RankRepository $rankRepo,
        private readonly SubfleetRepository $subfleetRepo,
        private readonly UserRepository $userRepo
    ) {}

    /**
     * Get the available subfleets for a rank
     */
    protected function getAvailSubfleets(Rank $rank): array
    {
        $retval = [];
        $all_subfleets = $this->subfleetRepo->all();
        $avail_subfleets = $all_subfleets->except($rank->subfleets->modelKeys());
        foreach ($avail_subfleets as $subfleet) {
            $retval[$subfleet->id] = $subfleet->name.
                ' (airline: '.$subfleet->airline->code.')';
        }

        return $retval;
    }

    /**
     * Display a listing of the Ranking.
     *
     *
     * @throws \Prettus\Repository\Exceptions\RepositoryException
     */
    public function index(Request $request): View
    {
        $this->rankRepo->pushCriteria(new RequestCriteria($request));
        $ranks = $this->rankRepo->all();

        return view('admin.ranks.index', [
            'ranks' => $ranks,
        ]);
    }

    /**
     * Show the form for creating a new Ranking.
     */
    public function create(): View
    {
        return view('admin.ranks.create');
    }

    /**
     * Store a newly created Ranking in storage.
     *
     *
     * @throws \Prettus\Validator\Exceptions\ValidatorException
     */
    public function store(CreateRankRequest $request): RedirectResponse
    {
        $input = $request->all();

        $model = $this->rankRepo->create($input);
        Flash::success('Ranking saved successfully.');

        Cache::forget(config('cache.keys.RANKS_PILOT_LIST.key'));

        return redirect(route('admin.ranks.edit', [$model->id]));
    }

    /**
     * Display the specified Ranking.
     */
    public function show(int $id): RedirectResponse|View
    {
        $rank = $this->rankRepo->findWithoutFail($id);

        if (empty($rank)) {
            Flash::error('Ranking not found');

            return redirect(route('admin.ranks.index'));
        }

        return view('admin.ranks.show', [
            'rank' => $rank,
        ]);
    }

    /**
     * Show the form for editing the specified Ranking.
     */
    public function edit(int $id): RedirectResponse|View
    {
        $rank = $this->rankRepo->findWithoutFail($id);

        if (empty($rank)) {
            Flash::error('Ranking not found');

            return redirect(route('admin.ranks.index'));
        }

        $avail_subfleets = $this->getAvailSubfleets($rank);

        return view('admin.ranks.edit', [
            'rank'            => $rank,
            'avail_subfleets' => $avail_subfleets,
        ]);
    }

    /**
     * Update the specified Ranking in storage.
     *
     *
     * @throws \Prettus\Validator\Exceptions\ValidatorException
     */
    public function update(int $id, UpdateRankRequest $request): RedirectResponse
    {
        $rank = $this->rankRepo->findWithoutFail($id);

        if (empty($rank)) {
            Flash::error('Ranking not found');

            return redirect(route('admin.ranks.index'));
        }

        $rank = $this->rankRepo->update($request->all(), $id);
        Cache::forget(config('cache.keys.RANKS_PILOT_LIST.key'));

        Flash::success('Ranking updated successfully.');

        return redirect(route('admin.ranks.index'));
    }

    /**
     * Remove the specified Ranking from storage.
     */
    public function destroy(int $id): RedirectResponse
    {
        $rank_in_use = $this->userRepo->findWhere(['rank_id' => $id])->count();
        if ($rank_in_use > 0) {
            Flash::error('Rank cannot be deleted since it\'s already assigned to one or more pilots!');

            return redirect(route('admin.ranks.index'));
        }

        if ($this->rankRepo->count() === 1) {
            Flash::error('Rank cannot be deleted since it\'s the only remaining rank in your database!');

            return redirect(route('admin.ranks.index'));
        }

        $rank = $this->rankRepo->findWithoutFail($id);

        if (empty($rank)) {
            Flash::error('Ranking not found');

            return redirect(route('admin.ranks.index'));
        }

        $this->rankRepo->delete($id);

        Flash::success('Ranking deleted successfully.');

        return redirect(route('admin.ranks.index'));
    }

    protected function return_subfleet_view(Rank $rank): View
    {
        $avail_subfleets = $this->getAvailSubfleets($rank);

        return view('admin.ranks.subfleets', [
            'rank'            => $rank,
            'avail_subfleets' => $avail_subfleets,
        ]);
    }

    /**
     * Subfleet operations on a rank
     */
    public function subfleets(int $id, Request $request): RedirectResponse|View
    {
        $rank = $this->rankRepo->findWithoutFail($id);
        if (empty($rank)) {
            Flash::error('Rank not found!');

            return redirect(route('admin.ranks.index'));
        }

        // add aircraft to flight
        if ($request->isMethod('post')) {
            foreach ($request->input('subfleet_ids') as $subfleet_id) {
                $subfleet = $this->subfleetRepo->find($subfleet_id);
                $this->fleetSvc->addSubfleetToRank($subfleet, $rank);
            }
        } elseif ($request->isMethod('put')) {
            $override = [];
            $override[$request->name] = $request->value;
            $subfleet = $this->subfleetRepo->find($request->input('subfleet_id'));

            $this->fleetSvc->addSubfleetToRank($subfleet, $rank);
        } // remove aircraft from flight
        elseif ($request->isMethod('delete')) {
            $subfleet = $this->subfleetRepo->find($request->input('subfleet_id'));
            $this->fleetSvc->removeSubfleetFromRank($subfleet, $rank);
        }

        return $this->return_subfleet_view($rank);
    }
}
