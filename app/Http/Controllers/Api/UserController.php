<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Controller;
use App\Exceptions\BidExistsForFlight;
use App\Exceptions\BidNotFound;
use App\Exceptions\Unauthorized;
use App\Exceptions\UserNotFound;
use App\Http\Resources\Bid as BidResource;
use App\Http\Resources\Pirep as PirepResource;
use App\Http\Resources\Subfleet as SubfleetResource;
use App\Http\Resources\User as UserResource;
use App\Models\Bid;
use App\Models\Enums\PirepState;
use App\Repositories\AircraftRepository;
use App\Repositories\Criteria\WhereCriteria;
use App\Repositories\FlightRepository;
use App\Repositories\PirepRepository;
use App\Repositories\UserRepository;
use App\Services\BidService;
use App\Services\UserService;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Prettus\Repository\Criteria\RequestCriteria;
use Prettus\Repository\Exceptions\RepositoryException;

class UserController extends Controller
{
    public function __construct(
        private readonly BidService $bidSvc,
        private readonly FlightRepository $flightRepo,
        private readonly PirepRepository $pirepRepo,
        private readonly UserRepository $userRepo,
        private readonly UserService $userSvc
    ) {}

    protected function getUserId(Request $request): mixed
    {
        $id = $request->get('id');
        if ($id === null || $id === 'me') {
            return Auth::user()->id;
        }

        return $request->get('id');
    }

    /**
     * Return the profile for the currently auth'd user
     */
    public function index(Request $request): UserResource
    {
        $with_subfleets = (!$request->has('with') || str_contains($request->input('with', ''), 'subfleets'));

        return $this->get(Auth::user()->id, $with_subfleets);
    }

    /**
     * Get the profile for the passed-in user
     *
     * @param Request $request
     */
    public function get(int $id, bool $with_subfleets = true): UserResource
    {
        $user = $this->userSvc->getUser($id, $with_subfleets);
        if (!$user instanceof \App\Models\User) {
            throw new UserNotFound();
        }

        return new UserResource($user);
    }

    /**
     * Return all of the bids for the passed-in user
     *
     *
     * @return mixed
     *
     * @throws ModelNotFoundException
     * @throws BidExistsForFlight
     */
    public function bids(Request $request)
    {
        $user_id = $this->getUserId($request);
        $user = $this->userSvc->getUser($user_id, false);
        if (!$user instanceof \App\Models\User) {
            throw new UserNotFound();
        }

        // Add a bid
        if ($request->isMethod('PUT') || $request->isMethod('POST')) {
            $flight_id = $request->input('flight_id');
            if (setting('bids.block_aircraft')) {
                $aircraft_id = $request->input('aircraft_id');
                $aircraft = app(AircraftRepository::class)->findWithoutFail($aircraft_id);
            }
            $flight = $this->flightRepo->find($flight_id);
            $bid = $this->bidSvc->addBid($flight, $user, $aircraft ?? null);

            return new BidResource($bid);
        }

        if ($request->isMethod('DELETE')) {
            if ($request->filled('bid_id')) {
                $bid = Bid::findOrFail($request->input('bid_id'));
                $flight_id = $bid->flight_id;
            } else {
                $flight_id = $request->input('flight_id');
            }

            $flight = $this->flightRepo->find($flight_id);
            $this->bidSvc->removeBid($flight, $user);
        }

        $relations = [
            'subfleets',
            'simbrief_aircraft',
        ];

        if ($request->has('with')) {
            $relations = explode(',', $request->input('with', ''));
        }

        // Return the flights they currently have bids on
        $bids = $this->bidSvc->findBidsForUser($user, $relations);

        return BidResource::collection($bids);
    }

    /**
     * Get a particular bid for a user
     *
     *
     * @return Bid
     */
    public function get_bid(int $bid_id, Request $request): BidResource
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Return the current bid
        $bid = $this->bidSvc->getBid($user, $bid_id);
        if (!$bid instanceof \App\Models\Bid) {
            throw new BidNotFound($bid_id);
        }

        if ($bid->user_id !== $user->id) {
            throw new Unauthorized(new Exception('Bid not not belong to authenticated user'));
        }

        return new BidResource($bid);
    }

    /**
     * Return the fleet that this user is allowed to
     */
    public function fleet(Request $request): AnonymousResourceCollection
    {
        $user = $this->userRepo->find($this->getUserId($request));
        if ($user === null) {
            throw new UserNotFound();
        }

        $subfleets = $this->userSvc->getAllowableSubfleets($user, true);

        return SubfleetResource::collection($subfleets);
    }

    /**
     * @throws RepositoryException
     */
    public function pireps(Request $request): AnonymousResourceCollection
    {
        $this->pirepRepo->pushCriteria(new RequestCriteria($request));

        $where = [
            'user_id' => $this->getUserId($request),
        ];

        if (filled($request->query('state'))) {
            $where['state'] = $request->query('state');
        } else {
            $where[] = ['state', '!=', PirepState::CANCELLED];
        }

        $this->pirepRepo->pushCriteria(new WhereCriteria($request, $where));

        $pireps = $this->pirepRepo
            ->with(['airline', 'dpt_airport', 'arr_airport'])
            ->orderBy('created_at', 'desc')
            ->paginate();

        return PirepResource::collection($pireps);
    }
}
