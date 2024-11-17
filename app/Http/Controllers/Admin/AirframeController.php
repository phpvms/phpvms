<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\Controller;
use App\Http\Requests\CreateAirframeRequest;
use App\Http\Requests\UpdateAirframeRequest;
use App\Models\Aircraft;
use App\Repositories\AirframeRepository;
use App\Services\SimBriefService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Laracasts\Flash\Flash;
use Prettus\Repository\Criteria\RequestCriteria;
use Prettus\Repository\Exceptions\RepositoryException;
use Prettus\Validator\Exceptions\ValidatorException;

class AirframeController extends Controller
{
     /**
     * @param AirframeRepository $airframeRepo
     */
    public function __construct(
        private readonly AirframeRepository $airframeRepo
    ) {
    }

    /**
     * @param Request $request
     *
     * @throws RepositoryException
     *
     * @return View
     */
    public function index(Request $request): View
    {
        $this->airframeRepo->pushCriteria(new RequestCriteria($request));
        $airframes = $this->airframeRepo->where('source', 'Custom')->orderby('icao', 'asc')->orderby('name', 'asc')->get();

        return view('admin.airframes.index', [
            'airframes' => $airframes,
        ]);
    }

    /**
     * @return View
     */
    public function create(): View
    {

        return view('admin.airframes.create', [
            'icao_codes' => Aircraft::whereNotNull('icao')->groupBy('icao')->pluck('icao')->toArray(),
        ]);
    }

    /**
     * @param CreateAirframeRequest $request
     *
     * @throws ValidatorException
     *
     * @return RedirectResponse
     */
    public function store(CreateAirframeRequest $request): RedirectResponse
    {
        $input = $request->all();

        $model = $this->airframeRepo->create($input);
        Flash::success('Airframe saved successfully.');

        return redirect(route('admin.airframes.index'));
    }

    /**
     * @param int $id
     *
     * @return RedirectResponse|View
     */
    public function show(int $id): RedirectResponse|View
    {
        $airframe = $this->airframeRepo->findWithoutFail($id);

        if (empty($airframe)) {
            Flash::error('SimBrief Airframe not found');

            return redirect(route('admin.airframes.index'));
        }

        return view('admin.airframes.show', [
            'airframe' => $airframe,
        ]);
    }

    /**
     * @param int $id
     *
     * @return RedirectResponse|View
     */
    public function edit(int $id): RedirectResponse|View
    {
        $airframe = $this->airframeRepo->findWithoutFail($id);

        if (empty($airframe)) {
            Flash::error('SimBrief Airframe not found');

            return redirect(route('admin.airframes.index'));
        }

        return view('admin.airframes.edit', [
            'airframe'   => $airframe,
            'icao_codes' => Aircraft::whereNotNull('icao')->groupBy('icao')->pluck('icao')->toArray(),
        ]);
    }

    /**
     * @param int                     $id
     * @param UpdateAirframeRequest $request
     *
     * @throws ValidatorException
     *
     * @return RedirectResponse
     */
    public function update(int $id, UpdateAirframeRequest $request): RedirectResponse
    {
        $airframe = $this->airframeRepo->findWithoutFail($id);

        if (empty($airframe)) {
            Flash::error('SimBrief Airframe not found');

            return redirect(route('admin.airframes.index'));
        }

        $airframe = $this->airframeRepo->update($request->all(), $id);
        Flash::success('SimBrief Airport updated successfully.');

        return redirect(route('admin.airframes.index'));
    }

    /**
     * @param int $id
     *
     * @return RedirectResponse
     */
    public function destroy(int $id): RedirectResponse
    {
        $airframe = $this->airframeRepo->findWithoutFail($id);

        if (empty($airframe)) {
            Flash::error('SimBrief Airframe not found');

            return redirect(route('admin.airframes.index'));
        }

        $this->airframeRepo->delete($id);

        Flash::success('SimBrief Airframe deleted successfully.');

        return redirect(route('admin.airframes.index'));
    }

    // Manually trigger update of SimBrief Airframe and Layouts
    public function updateSimbriefData()
    {

        Log::debug('Manually Updating SimBrief Support Data');
        $SimBriefSVC = app(SimBriefService::class);
        $SimBriefSVC->getAircraftAndAirframes();
        $SimBriefSVC->GetBriefingLayouts();

        Flash::success('SimBrief Airframe and Layouts updated successfully.');

        return redirect(route('admin.airframes.index'));
    }
}
