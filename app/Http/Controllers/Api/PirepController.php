<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Controller;
use App\Events\PirepUpdated;
use App\Exceptions\AircraftNotAtAirport;
use App\Exceptions\AircraftPermissionDenied;
use App\Exceptions\PirepCancelled;
use App\Exceptions\PirepError;
use App\Exceptions\UserNotAtAirport;
use App\Http\Requests\Acars\CommentRequest;
use App\Http\Requests\Acars\FieldsRequest;
use App\Http\Requests\Acars\FileRequest;
use App\Http\Requests\Acars\PrefileRequest;
use App\Http\Requests\Acars\RouteRequest;
use App\Http\Requests\Acars\UpdateRequest;
use App\Http\Resources\AcarsRoute as AcarsRouteResource;
use App\Http\Resources\JournalTransaction as JournalTransactionResource;
use App\Http\Resources\Pirep as PirepResource;
use App\Http\Resources\PirepComment as PirepCommentResource;
use App\Http\Resources\PirepFieldCollection;
use App\Models\Acars;
use App\Models\Enums\AcarsType;
use App\Models\Enums\PirepFieldSource;
use App\Models\Enums\PirepSource;
use App\Models\Pirep;
use App\Models\PirepComment;
use App\Models\PirepFare;
use App\Models\PirepFieldValue;
use App\Models\User;
use App\Repositories\JournalRepository;
use App\Repositories\PirepRepository;
use App\Services\Finance\PirepFinanceService;
use App\Services\PirepService;
use App\Services\UserService;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Prettus\Validator\Exceptions\ValidatorException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use UnexpectedValueException;

class PirepController extends Controller
{
    public function __construct(
        private readonly PirepFinanceService $financeSvc,
        private readonly JournalRepository $journalRepo,
        private readonly PirepRepository $pirepRepo,
        private readonly PirepService $pirepSvc,
        private readonly UserService $userSvc
    ) {}

    /**
     * Parse any PIREP added in
     */
    protected function parsePirep(Request $request): ?array
    {
        $attrs = $request->input();

        if (array_key_exists('created_at', $attrs)) {
            $attrs['created_at'] = Carbon::createFromTimeString($attrs['created_at']);
        }

        if (array_key_exists('submitted_at', $attrs)) {
            $attrs['submitted_at'] = Carbon::createFromTimeString($attrs['submitted_at']);
        }

        if (array_key_exists('updated_at', $attrs)) {
            $attrs['updated_at'] = Carbon::createFromTimeString($attrs['updated_at']);
        }

        return $attrs;
    }

    /**
     * Check if a PIREP is cancelled
     *
     *
     * @throws PirepCancelled
     */
    protected function checkCancelled(Pirep $pirep): void
    {
        if ($pirep->cancelled) {
            throw new PirepCancelled($pirep);
        }
    }

    /**
     * Check if a PIREP is cancelled
     *
     *
     * @throws PirepCancelled
     */
    protected function checkReadOnly(Pirep $pirep): void
    {
        if ($pirep->read_only) {
            throw new PirepError($pirep, 'PIREP is read-only');
        }
    }

    /**
     * @return ?PirepFieldValue[]
     */
    protected function getFields(Request $request): ?array
    {
        if (!$request->filled('fields')) {
            return [];
        }

        $pirep_fields = [];
        foreach ($request->input('fields') as $field_name => $field_value) {
            $pirep_fields[] = new PirepFieldValue([
                'name'   => $field_name,
                'value'  => $field_value,
                'source' => PirepFieldSource::ACARS,
            ]);
        }

        return $pirep_fields;
    }

    /**
     * Save the fares
     *
     *
     * @return ?PirepFare[]
     *
     * @throws Exception
     */
    protected function getFares(Request $request): ?array
    {
        if (!$request->filled('fares')) {
            return [];
        }

        $fares = [];
        foreach ($request->post('fares') as $fare) {
            $fares[] = new PirepFare([
                'fare_id' => $fare['id'],
                'count'   => $fare['count'],
            ]);
        }

        return $fares;
    }

    /**
     * @param string $id The PIREP ID
     */
    public function get(string $id): PirepResource
    {
        $with = [
            'aircraft',
            'arr_airport',
            'dpt_airport',
            'comments',
            'flight',
            'simbrief',
            'position',
            'user',
        ];

        $pirep = $this->pirepRepo
            ->with($with)
            ->find($id);

        return new PirepResource($pirep);
    }

    /**
     * Create a new PIREP and place it in a "inprogress" and "prefile" state
     * Once ACARS updates are being processed, then it can go into an 'ENROUTE'
     * status, and whatever other statuses may be defined
     *
     *
     * @throws AircraftNotAtAirport
     * @throws UserNotAtAirport
     * @throws PirepCancelled
     * @throws AircraftPermissionDenied
     * @throws Exception
     */
    public function prefile(PrefileRequest $request): PirepResource
    {
        /**
         * @var \App\Models\User $user
         */
        $user = Auth::user();

        $attrs = $this->parsePirep($request);
        $attrs['source'] = PirepSource::ACARS;

        $fields = $this->getFields($request);
        $fares = $this->getFares($request);
        $pirep = $this->pirepSvc->prefile($user, $attrs, $fields, $fares);

        Log::info('PIREP Prefile, pirep_id: '.$pirep->id.', user_id: '.Auth::id());

        return $this->get($pirep->id);
    }

    /**
     * Create a new PIREP and place it in a "inprogress" and "prefile" state
     * Once ACARS updates are being processed, then it can go into an 'ENROUTE'
     * status, and whatever other statuses may be defined
     *
     *
     * @throws PirepCancelled
     * @throws AircraftPermissionDenied
     * @throws ValidatorException
     * @throws Exception
     */
    public function update(string $pirep_id, UpdateRequest $request): PirepResource
    {
        Log::debug('PIREP Update, pirep_id: '.$pirep_id.', user_id: '.Auth::id());

        /** @var User $user */
        $user = Auth::user();

        /** @var Pirep $pirep */
        $pirep = Pirep::find($pirep_id);
        $this->checkCancelled($pirep);
        $this->checkReadOnly($pirep);

        $attrs = $this->parsePirep($request);
        $attrs['user_id'] = Auth::id();

        // If aircraft is being changed, see if this user is allowed to fly this aircraft
        if (array_key_exists('aircraft_id', $attrs)
            && setting('pireps.restrict_aircraft_to_rank', false)
        ) {
            $can_use_ac = $this->userSvc->aircraftAllowed($user, $pirep->aircraft_id);
            if (!$can_use_ac) {
                throw new AircraftPermissionDenied($user, $pirep->aircraft);
            }
        }

        $fields = $this->getFields($request);
        $fares = $this->getFares($request);
        $pirep = $this->pirepSvc->update($pirep_id, $attrs, $fields, $fares);

        event(new PirepUpdated($pirep));

        return $this->get($pirep->id);
    }

    /**
     * File the PIREP
     *
     *
     * @throws PirepCancelled
     * @throws AircraftPermissionDenied
     * @throws ModelNotFoundException
     * @throws Exception
     */
    public function file(string $pirep_id, FileRequest $request): PirepResource
    {
        Log::info('PIREP File, pirep_id: '.$pirep_id.', user_id: '.Auth::id());

        /** @var User $user */
        $user = Auth::user();

        // Check if the status is cancelled...
        $pirep = Pirep::find($pirep_id);
        $this->checkCancelled($pirep);
        $this->checkReadOnly($pirep);

        $attrs = $this->parsePirep($request);

        // If aircraft is being changed, see if this user is allowed to fly this aircraft
        if (array_key_exists('aircraft_id', $attrs)
            && setting('pireps.restrict_aircraft_to_rank', false)
        ) {
            $can_use_ac = $this->userSvc->aircraftAllowed($user, $pirep->aircraft_id);
            if (!$can_use_ac) {
                throw new AircraftPermissionDenied($user, $pirep->aircraft);
            }
        }

        try {
            $fields = $this->getFields($request);
            $fares = $this->getFares($request);
            $pirep = $this->pirepSvc->file($pirep, $attrs, $fields, $fares);
        } catch (Exception $e) {
            Log::error($e);

            throw $e;
        }

        // See if there there is any route data posted
        // If there isn't, then just write the route data from the
        // route that's been posted from the PIREP
        $w = ['pirep_id' => $pirep->id, 'type' => AcarsType::ROUTE];
        $count = Acars::where($w)->count(['id']);
        if ($count === 0) {
            $this->pirepSvc->saveRoute($pirep);
        }

        $this->pirepSvc->submit($pirep);

        return $this->get($pirep->id);
    }

    /**
     * Cancel the PIREP
     *
     *
     * @return mixed
     *
     * @throws ValidatorException
     */
    public function cancel(string $pirep_id, Request $request)
    {
        Log::info('PIREP Cancel, pirep_id: '.$pirep_id.', user_id: '.Auth::id());

        $pirep = Pirep::find($pirep_id);
        if (!empty($pirep)) {
            $this->pirepSvc->cancel($pirep);
        }

        return $this->message('PIREP '.$pirep_id.' cancelled');
    }

    /**
     * Add a new comment
     */
    public function comments_get(string $id): AnonymousResourceCollection
    {
        $pirep = Pirep::find($id);

        return PirepCommentResource::collection($pirep->comments);
    }

    /**
     * Add a new comment
     *
     *
     * @throws PirepCancelled
     */
    public function comments_post(string $id, CommentRequest $request): PirepCommentResource
    {
        $pirep = Pirep::find($id);
        $this->checkCancelled($pirep);

        Log::debug('Posting comment, PIREP: '.$id, $request->post());

        // Add it
        $comment = new PirepComment($request->post());
        $comment->pirep_id = $id;
        $comment->user_id = Auth::id();
        $comment->save();

        return new PirepCommentResource($comment);
    }

    /**
     * Get all of the fields for a PIREP
     */
    public function fields_get(string $pirep_id): PirepFieldCollection
    {
        $pirep = Pirep::find($pirep_id);

        return new PirepFieldCollection($pirep->fields);
    }

    /**
     * Set any fields for a PIREP
     */
    public function fields_post(string $pirep_id, FieldsRequest $request): PirepFieldCollection
    {
        $pirep = Pirep::find($pirep_id);
        $this->checkCancelled($pirep);

        $fields = $this->getFields($request);
        $this->pirepSvc->updateCustomFields($pirep_id, $fields);

        return new PirepFieldCollection($pirep->fields);
    }

    /**
     * @throws UnexpectedValueException
     * @throws InvalidArgumentException
     */
    public function finances_get(string $id): AnonymousResourceCollection
    {
        $pirep = Pirep::find($id);
        $transactions = $this->journalRepo->getAllForObject($pirep);

        return JournalTransactionResource::collection($transactions['transactions']);
    }

    /**
     * @throws UnexpectedValueException
     * @throws InvalidArgumentException
     * @throws Exception
     * @throws ValidatorException
     */
    public function finances_recalculate(string $id, Request $request): AnonymousResourceCollection
    {
        $pirep = Pirep::find($id);
        $this->financeSvc->processFinancesForPirep($pirep);

        $pirep->refresh();

        $transactions = $this->journalRepo->getAllForObject($pirep);

        return JournalTransactionResource::collection($transactions['transactions']);
    }

    public function route_get(string $id, Request $request): AnonymousResourceCollection
    {
        $pirep = Pirep::find($id);

        return AcarsRouteResource::collection(Acars::with('pirep')->where([
            'pirep_id' => $id,
            'type'     => AcarsType::ROUTE,
        ])->orderBy('order', 'asc')->get());
    }

    /**
     * Post the ROUTE for a PIREP, can be done from the ACARS log
     *
     *
     * @throws PirepCancelled
     * @throws BadRequestHttpException
     * @throws Exception
     */
    public function route_post(string $id, RouteRequest $request): JsonResponse
    {
        // Check if the status is cancelled...
        $pirep = Pirep::find($id);
        $this->checkCancelled($pirep);

        Log::info('Posting ROUTE, pirep_id: '.$id);

        // Delete the route before posting a new one
        Acars::where([
            'pirep_id' => $id,
            'type'     => AcarsType::ROUTE,
        ])->delete();

        $count = 0;
        $route = $request->post('route', []);
        if (\count($route) === 0) {
            return $this->message('No points to add');
        }

        foreach ($route as $position) {
            $position['pirep_id'] = $id;
            $position['type'] = AcarsType::ROUTE;

            if (isset($position['id'])) {
                Acars::updateOrInsert(['id' => $position['id']], $position);
            } else {
                $acars = Acars::create($position);
                $acars->save();
            }

            $count++;
        }

        return $this->message($count.' points added', $count);
    }

    /**
     * @throws Exception
     */
    public function route_delete(string $id, Request $request): JsonResponse
    {
        $pirep = Pirep::find($id);

        Acars::where([
            'pirep_id' => $id,
            'type'     => AcarsType::ROUTE,
        ])->delete();

        return $this->message('Route deleted');
    }
}
