<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\Controller;
use App\Events\UserStatsChanged;
use App\Http\Requests\CreateUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Rank;
use App\Models\User;
use App\Models\UserAward;
use App\Repositories\AirlineRepository;
use App\Repositories\AirportRepository;
use App\Repositories\PirepRepository;
use App\Repositories\RoleRepository;
use App\Repositories\TypeRatingRepository;
use App\Repositories\UserRepository;
use App\Services\UserService;
use App\Support\Timezonelist;
use App\Support\Utils;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Laracasts\Flash\Flash;
use League\ISO3166\ISO3166;
use Prettus\Repository\Exceptions\RepositoryException;

class UserController extends Controller
{
    /**
     * UserController constructor.
     *
     * @param AirlineRepository    $airlineRepo
     * @param AirportRepository    $airportRepo
     * @param PirepRepository      $pirepRepo
     * @param RoleRepository       $roleRepo
     * @param TypeRatingRepository $typeratingRepo
     * @param UserRepository       $userRepo
     * @param UserService          $userSvc
     */
    public function __construct(
        private readonly AirlineRepository $airlineRepo,
        private readonly AirportRepository $airportRepo,
        private readonly PirepRepository $pirepRepo,
        private readonly RoleRepository $roleRepo,
        private readonly TypeRatingRepository $typeratingRepo,
        private readonly UserRepository $userRepo,
        private readonly UserService $userSvc
    ) {
    }

    /**
     * @param Request $request
     *
     * @return View
     */
    public function index(Request $request): View
    {
        try {
            $users = $this->userRepo->searchCriteria($request, false)
                ->orderBy('created_at', 'desc')
                ->paginate();
        } catch (RepositoryException $e) {
        }

        return view('admin.users.index', [
            'users'   => $users,
            'country' => new ISO3166(),
        ]);
    }

    /**
     * Show the form for creating a new User.
     *
     * @return View
     */
    public function create(): View
    {
        $airlines = $this->airlineRepo->selectBoxList();
        $countries = collect((new ISO3166())->all())
            ->mapWithKeys(fn ($item, $key) => [strtolower($item['alpha2']) => $item['name']]);
        $roles = $this->roleRepo->selectBoxList(false, true);

        return view('admin.users.create', [
            'user'      => null,
            'pireps'    => null,
            'airlines'  => $airlines,
            'timezones' => Timezonelist::toArray(),
            'country'   => new ISO3166(),
            'countries' => $countries,
            'airports'  => [],
            'ranks'     => Rank::all()->pluck('name', 'id'),
            'roles'     => $roles,
        ]);
    }

    /**
     * Store a newly created User in storage.
     *
     * @param CreateUserRequest $request
     *
     * @throws \Prettus\Validator\Exceptions\ValidatorException
     *
     * @return RedirectResponse
     */
    public function store(CreateUserRequest $request): RedirectResponse
    {
        $input = $request->all();
        $user = $this->userRepo->create($input);

        Flash::success('User saved successfully.');
        return redirect(route('admin.users.index'));
    }

    /**
     * Display the specified User.
     *
     * @param int $id
     *
     * @throws RepositoryException
     *
     * @return View
     */
    public function show(int $id): View
    {
        return $this->edit($id);
    }

    /**
     * Show the form for editing the specified User.
     *
     * @param int $id
     *
     * @throws RepositoryException
     *
     * @return mixed
     */
    public function edit(int $id): View
    {
        /** @var User $user */
        $user = $this->userRepo
            ->with(['awards', 'fields', 'rank', 'typeratings', 'home_airport', 'location'])
            ->findWithoutFail($id);

        if (empty($user)) {
            Flash::error('User not found');
            return redirect(route('admin.users.index'));
        }

        $pireps = $this->pirepRepo
            ->whereOrder(['user_id' => $id], 'created_at', 'desc')
            ->paginate();

        $countries = collect((new ISO3166())->all())
            ->mapWithKeys(fn ($item, $key) => [strtolower($item['alpha2']) => $item['name']]);

        $airlines = $this->airlineRepo->selectBoxList();
        $roles = $this->roleRepo->selectBoxList(false, true);
        $avail_ratings = $this->getAvailTypeRatings($user);

        $airports = ['' => ''];
        if ($user->home_airport) {
            $airports[$user->home_airport->id] = $user->home_airport->description;
        }

        if ($user->location) {
            $airports[$user->location->id] = $user->location->description;
        }

        return view('admin.users.edit', [
            'user'          => $user,
            'pireps'        => $pireps,
            'country'       => new ISO3166(),
            'countries'     => $countries,
            'timezones'     => Timezonelist::toArray(),
            'airports'      => $airports,
            'airlines'      => $airlines,
            'ranks'         => Rank::all()->pluck('name', 'id'),
            'roles'         => $roles,
            'avail_ratings' => $avail_ratings,
        ]);
    }

    /**
     * Update the specified User in storage.
     *
     * @param int               $id
     * @param UpdateUserRequest $request
     *
     * @throws \Prettus\Validator\Exceptions\ValidatorException
     *
     * @return RedirectResponse
     */
    public function update(int $id, UpdateUserRequest $request): RedirectResponse
    {
        $user = $this->userRepo->findWithoutFail($id);

        if (empty($user)) {
            Flash::error('User not found');

            return redirect(route('admin.users.index'));
        }

        $req_data = $request->all();
        if (!$request->filled('password')) {
            unset($req_data['password']);
        } else {
            $req_data['password'] = Hash::make($req_data['password']);
        }

        if ($request->filled('avatar_upload')) {
            /**
             * @var $file \Illuminate\Http\UploadedFile
             */
            $file = $request->file('avatar_upload');
            $file_path = $file->storeAs(
                'avatars',
                str_slug($file->getClientOriginalName()),
                config('filesystems.public_files')
            );

            $user->avatar = $file_path;
        }

        $original_user_state = $user->state;

        // Convert transferred hours to minutes
        $req_data['transfer_time'] *= 60;

        $original_user_rank = $user->rank_id;

        $user = $this->userRepo->update($req_data, $id);

        if ($original_user_state !== $user->state) {
            $this->userSvc->changeUserState($user, $original_user_state);
        }

        if ($original_user_rank != $user->rank_id) {
            event(new UserStatsChanged($user, 'rank', $user->rank_id));
        }

        // Delete all of the roles and then re-attach the valid ones
        if (!empty($request->input('roles'))) {
            DB::table('role_user')->where('user_id', $id)->delete();
            foreach ($request->input('roles') as $key => $value) {
                $user->assignRole($value);
            }
        }

        Flash::success('User updated successfully.');

        return redirect(route('admin.users.index'));
    }

    /**
     * Remove the specified User from storage.
     *
     * @param int $id
     *
     * @throws \Exception
     *
     * @return RedirectResponse
     */
    public function destroy(int $id): RedirectResponse
    {
        $user = $this->userRepo->findWithoutFail($id);
        if (empty($user)) {
            Flash::error('User not found');

            return redirect(route('admin.users.index'));
        }

        $this->userSvc->removeUser($user);
        Flash::success('User deleted successfully.');

        return redirect(route('admin.users.index'));
    }

    /**
     * Remove the award from a user
     *
     * @param \Illuminate\Http\Request $request
     * @param int                      $id
     * @param int                      $award_id
     *
     * @return RedirectResponse
     */
    public function destroy_user_award(int $id, int $award_id, Request $request): RedirectResponse
    {
        $userAward = UserAward::where(['user_id' => $id, 'award_id' => $award_id]);
        if (empty($userAward)) {
            Flash::error('The user award could not be found');

            return redirect()->back();
        }

        $userAward->delete();

        return redirect()->back();
    }

    /**
     * Regenerate the user's API key
     *
     * @param int     $id
     * @param Request $request
     *
     * @return RedirectResponse
     */
    public function regen_apikey(int $id, Request $request): RedirectResponse
    {
        $user = User::find($id);
        Log::info('Regenerating API key "'.$user->ident.'"');

        $user->api_key = Utils::generateApiKey();
        $user->save();

        flash('New API key generated!')->success();

        return redirect(route('admin.users.edit', [$id]));
    }

    public function verify_email(int $id, Request $request): RedirectResponse
    {
        $user = $this->userRepo->findWithoutFail($id);

        if (empty($user)) {
            Flash::error('User not found');
            return back();
        }

        if ($user->hasVerifiedEmail()) {
            Flash::error('User email already verified');
            return back();
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        Flash::success('User email verified successfully');
        return back();
    }

    public function request_email_verification(int $id, Request $request): RedirectResponse
    {
        $user = $this->userRepo->findWithoutFail($id);

        if (empty($user)) {
            Flash::error('User not found');
            return back();
        }

        if (!$user->hasVerifiedEmail()) {
            Flash::error('User email already not verified');
            return back();
        }

        $user->update([
            'email_verified_at' => null,
        ]);

        $user->sendEmailVerificationNotification();

        Flash::success('User email verification requested successfully');
        return back();
    }

    /**
     * Get the type ratings that are available to the user
     *
     * @param User $user
     *
     * @return array
     */
    protected function getAvailTypeRatings(User $user): array
    {
        $retval = [];
        $all_ratings = $this->typeratingRepo->all();
        $avail_ratings = $all_ratings->except($user->typeratings->modelKeys());
        foreach ($avail_ratings as $tr) {
            $retval[$tr->id] = $tr->name.' ('.$tr->type.')';
        }

        return $retval;
    }

    /**
     * @param User $user
     *
     * @return View
     */
    protected function return_typeratings_view(?User $user): View
    {
        $user->refresh();

        $avail_ratings = $this->getAvailTypeRatings($user);
        return view('admin.users.type_ratings', [
            'user'          => $user,
            'avail_ratings' => $avail_ratings,
        ]);
    }

    /**
     * Operations for associating type ratings to the user
     *
     * @param int     $id
     * @param Request $request
     *
     * @return View
     */
    public function typeratings(int $id, Request $request): View
    {
        $user = $this->userRepo->findWithoutFail($id);
        if (empty($user)) {
            return $this->return_typeratings_view($user);
        }

        if ($request->isMethod('get')) {
            return $this->return_typeratings_view($user);
        }

        // associate user with type rating
        if ($request->isMethod('post')) {
            $typerating = $this->typeratingRepo->find($request->input('typerating_id'));
            $this->userSvc->addUserToTypeRating($user, $typerating);
        } // dissassociate user from the type rating
        elseif ($request->isMethod('delete')) {
            $typerating = $this->typeratingRepo->find($request->input('typerating_id'));
            $this->userSvc->removeUserFromTypeRating($user, $typerating);
        }

        $user->save();

        return $this->return_typeratings_view($user);
    }
}
