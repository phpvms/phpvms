<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Controller;
use App\Exceptions\BidExistsForFlight;
use App\Exceptions\BidNotFound;
use App\Exceptions\Unauthorized;
use App\Exceptions\UserNotFound;
use App\Http\Requests\SearchPirepsRequest;
use App\Http\Resources\BidResource;
use App\Http\Resources\PirepResource;
use App\Http\Resources\SubfleetResource;
use App\Http\Resources\UserResource;
use App\Models\Aircraft;
use App\Models\Bid;
use App\Models\Enums\PirepState;
use App\Models\Flight;
use App\Models\User;
use App\Queries\PirepSearchQuery;
use App\Services\BidService;
use App\Services\UserService;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class UserController extends Controller
{
    public function __construct(
        private readonly BidService $bidSvc,
        private readonly UserService $userSvc
    ) {}

    protected function getUserId(Request $request): mixed
    {
        $id = $request->input('id');
        if ($id === null || $id === 'me') {
            return Auth::user()->id;
        }

        return $request->input('id');
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
     */
    public function get(int $id, bool $with_subfleets = true): UserResource
    {
        $user = $this->userSvc->getUser($id, $with_subfleets);
        if (!$user instanceof User) {
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
        if (!$user instanceof User) {
            throw new UserNotFound();
        }

        // Add a bid
        if ($request->isMethod('PUT') || $request->isMethod('POST')) {
            $flight_id = $request->input('flight_id');
            if (setting('bids.block_aircraft')) {
                $aircraft_id = $request->input('aircraft_id');
                $aircraft = Aircraft::find($aircraft_id);
            }
            $flight = Flight::findOrFail($flight_id);
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

            $flight = Flight::findOrFail($flight_id);
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
     */
    public function get_bid(int $bid_id, Request $request): BidResource
    {
        /** @var User $user */
        $user = Auth::user();

        // Return the current bid
        $bid = $this->bidSvc->getBid($user, $bid_id);
        if (!$bid instanceof Bid) {
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
        $user = User::find($this->getUserId($request));
        if ($user === null) {
            throw new UserNotFound();
        }

        $perPage = paginate_limit($request->integer('limit') ?: null);

        $subfleets = $this->userSvc->getAllowableSubfleets($user, true, $perPage)
            ->appends($request->except(['page', 'user']));

        return SubfleetResource::collection($subfleets);
    }

    public function pireps(SearchPirepsRequest $request, PirepSearchQuery $searchQuery): AnonymousResourceCollection
    {
        $query = $searchQuery->build($request)
            ->where('user_id', $this->getUserId($request));

        if (filled($request->query('state'))) {
            $query->where('state', $request->query('state'));
        } else {
            $query->where('state', '!=', PirepState::CANCELLED);
        }

        if (filled($request->query('status'))) {
            $query->where('status', $request->query('status'));
        }

        // Default ordering when no orderBy supplied — matches legacy behavior.
        if (!$request->filled('orderBy')) {
            $query->orderBy('created_at', 'desc');
        }

        $perPage = paginate_limit($request->integer('limit') ?: null);

        return PirepResource::collection($query->paginate($perPage));
    }

    /**
     * Update the SimBrief username for the currently authenticated user
     */
    public function simbrief_username(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'simbrief_username' => 'required|string',
        ]);

        // Now let's see if the simbrief username is valid
        $response = Http::timeout(20)->get(config('phpvms.simbrief_ofp_url'), [
            'username' => $validated['simbrief_username'],
            'json'     => 'v2',
        ]);

        if ($response->serverError() || $response->json('fetch.status') === 'Error: Unknown UserID') {
            return response()->json([
                'success' => false,
                'message' => 'Invalid SimBrief username provided.',
            ], 422);
        }

        // Finally, update the user's SimBrief username
        $user = Auth::user();
        $user->update([
            'simbrief_username' => $validated['simbrief_username'],
        ]);

        return response()->json(['success' => true]);
    }
}
