<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\Controller;
use App\Http\Requests\CreateAwardRequest;
use App\Http\Requests\UpdateAwardRequest;
use App\Models\UserAward;
use App\Repositories\AwardRepository;
use App\Services\AwardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Laracasts\Flash\Flash;
use Prettus\Repository\Criteria\RequestCriteria;

class AwardController extends Controller
{
    /**
     * AwardController constructor.
     */
    public function __construct(
        private readonly AwardRepository $awardRepo,
        private readonly AwardService $awardSvc
    ) {}

    protected function getAwardClassesAndDescriptions(): array
    {
        $awards = [
            '' => '',
        ];

        $descriptions = [];

        $award_classes = $this->awardSvc->findAllAwardClasses();
        foreach ($award_classes as $class_ref => $award) {
            $awards[$class_ref] = $award->name;
            $descriptions[$class_ref] = $award->param_description;
        }

        return [
            'awards'       => $awards,
            'descriptions' => $descriptions,
        ];
    }

    /**
     * Display a listing of the Fare.
     *
     *
     * @throws \Prettus\Repository\Exceptions\RepositoryException
     */
    public function index(Request $request): View
    {
        $this->awardRepo->pushCriteria(new RequestCriteria($request));
        $awards = $this->awardRepo->sortable('name')->get();

        $counts = [];

        foreach ($awards as $aw) {
            $counts[$aw->id] = UserAward::where('award_id', $aw->id)->count();
        }

        return view('admin.awards.index', [
            'awards' => $awards,
            'counts' => $counts,
        ]);
    }

    /**
     * Show the form for creating a new Fare.
     */
    public function create(): View
    {
        $class_refs = $this->getAwardClassesAndDescriptions();

        return view('admin.awards.create', [
            'award_classes'      => $class_refs['awards'],
            'award_descriptions' => $class_refs['descriptions'],
        ]);
    }

    /**
     * Store a newly created Fare in storage.
     *
     *
     * @throws \Prettus\Validator\Exceptions\ValidatorException
     */
    public function store(CreateAwardRequest $request): RedirectResponse
    {
        $input = $request->all();
        $award = $this->awardRepo->create($input);
        Flash::success('Award saved successfully.');

        return redirect(route('admin.awards.index'));
    }

    /**
     * Display the specified Fare.
     */
    public function show(int $id): View
    {
        $award = $this->awardRepo->findWithoutFail($id);
        if (empty($award)) {
            Flash::error('Award not found');

            return redirect(route('admin.awards.index'));
        }

        return view('admin.awards.show', [
            'award' => $award,
        ]);
    }

    /**
     * Show the form for editing the specified award.
     */
    public function edit(int $id): RedirectResponse|View
    {
        $award = $this->awardRepo->findWithoutFail($id);
        if (empty($award)) {
            Flash::error('Award not found');

            return redirect(route('admin.awards.index'));
        }

        $class_refs = $this->getAwardClassesAndDescriptions();

        $owners = UserAward::with('user')->where('award_id', $id)->sortable(['created_at' => 'desc'])->get();

        return view('admin.awards.edit', [
            'award'              => $award,
            'award_classes'      => $class_refs['awards'],
            'award_descriptions' => $class_refs['descriptions'],
            'owners'             => $owners,
        ]);
    }

    /**
     * Update the specified award in storage.
     *
     *
     * @throws \Prettus\Validator\Exceptions\ValidatorException
     */
    public function update(int $id, UpdateAwardRequest $request): RedirectResponse
    {
        $award = $this->awardRepo->findWithoutFail($id);
        if (empty($award)) {
            Flash::error('Award not found');

            return redirect(route('admin.awards.index'));
        }

        $award = $this->awardRepo->update($request->all(), $id);
        Flash::success('Award updated successfully.');

        return redirect(route('admin.awards.index'));
    }

    /**
     * Remove the specified Fare from storage.
     */
    public function destroy(int $id): RedirectResponse
    {
        $award = $this->awardRepo->findWithoutFail($id);
        if (empty($award)) {
            Flash::error('Award not found');

            return redirect(route('admin.awards.index'));
        }

        $this->awardRepo->delete($id);
        Flash::success('Award deleted successfully.');

        return redirect(route('admin.awards.index'));
    }
}
