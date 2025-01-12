<?php

namespace App\Http\Controllers\Frontend;

use App\Contracts\Controller;
use App\Events\ProfileUpdated;
use App\Models\User;
use App\Models\UserField;
use App\Models\UserFieldValue;
use App\Repositories\AirlineRepository;
use App\Repositories\AirportRepository;
use App\Repositories\UserRepository;
use App\Support\Countries;
use App\Support\Timezonelist;
use App\Support\Utils;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Intervention\Image\Facades\Image;
use Laracasts\Flash\Flash;
use Nwidart\Modules\Facades\Module;

class ProfileController extends Controller
{
    /**
     * ProfileController constructor.
     */
    public function __construct(
        private readonly AirlineRepository $airlineRepo,
        private readonly AirportRepository $airportRepo,
        private readonly UserRepository $userRepo
    ) {}

    /**
     * Return whether the vmsACARS module is enabled or not
     */
    private function acarsEnabled(): bool
    {
        // Is the ACARS module enabled?
        $acars_enabled = false;
        $acars = Module::find('VMSAcars');
        if ($acars) {
            $acars_enabled = $acars->isEnabled();
        }

        return $acars_enabled;
    }

    /**
     * Redirect to show() since only a single page gets shown and the template controls
     * the other items that are/aren't shown
     */
    public function index(): View
    {
        return $this->show(Auth::user()->id);
    }

    public function show(int $id): RedirectResponse|View
    {
        $with = [
            'airline',
            'awards',
            'current_airport',
            'fields.field',
            'home_airport',
            'last_pirep',
            'rank',
            'typeratings',
        ];
        /** @var \App\Models\User $user */
        $user = User::with($with)->where('id', $id)->first();

        if (empty($user)) {
            Flash::error('User not found!');

            return redirect(route('frontend.dashboard.index'));
        }

        $userFields = $this->userRepo->getUserFields($user, true);

        return view('profile.index', [
            'user'       => $user,
            'userFields' => $userFields,
            'acars'      => $this->acarsEnabled(),
        ]);
    }

    /**
     * Show the edit for form the user's profile
     *
     *
     * @throws \Exception
     */
    public function edit(Request $request): RedirectResponse|View
    {
        /** @var \App\Models\User $user */
        $user = User::with('fields.field', 'home_airport')->where('id', Auth::id())->first();

        if (empty($user)) {
            Flash::error('User not found!');

            return redirect(route('frontend.dashboard.index'));
        }

        if ($user->home_airport) {
            $airports = [$user->home_airport->id => $user->home_airport->description];
        } else {
            $airports = ['' => ''];
        }

        $airlines = $this->airlineRepo->selectBoxList();
        $userFields = $this->userRepo->getUserFields($user);

        return view('profile.edit', [
            'user'       => $user,
            'airlines'   => $airlines,
            'airports'   => $airports,
            'hubs_only'  => setting('pilots.home_hubs_only'),
            'countries'  => Countries::getSelectList(),
            'timezones'  => Timezonelist::toArray(),
            'userFields' => $userFields,
        ]);
    }

    /**
     * @throws \Prettus\Validator\Exceptions\ValidatorException
     */
    public function update(Request $request): RedirectResponse
    {
        $id = Auth::user()->id;
        $user = $this->userRepo->findWithoutFail($id);

        $rules = [
            'name'       => 'required',
            'email'      => 'required|unique:users,email,'.$id,
            'airline_id' => 'required',
            'password'   => 'confirmed',
            'avatar'     => 'nullable|mimes:jpeg,png,jpg',
        ];

        $userFields = UserField::where(
            ['show_on_registration' => true, 'required' => true, 'internal' => false]
        )->get();
        foreach ($userFields as $field) {
            $rules['field_'.$field->slug] = 'required';
        }

        $validator = Validator::make($request->toArray(), $rules);

        if ($validator->fails()) {
            Log::info('validator failed for user '.$user->ident);
            Log::info($validator->errors()->toArray());

            return redirect(route('frontend.profile.edit', $id))
                ->withErrors($validator)
                ->withInput();
        }

        $req_data = $request->all();
        if (!$request->filled('password')) {
            unset($req_data['password']);
        } else {
            $req_data['password'] = Hash::make($req_data['password']);
        }

        if ($request->hasFile('avatar')) {
            if ($user->avatar !== null) {
                Storage::delete($user->avatar);
            }

            $avatar = $request->file('avatar');
            $file_name = $user->ident.'.'.$avatar->getClientOriginalExtension();
            $path = "avatars/$file_name";

            // Create the avatar, resizing it and keeping the aspect ratio.
            // https://stackoverflow.com/a/26892028
            $w = config('phpvms.avatar.width');
            $h = config('phpvms.avatar.height');

            $canvas = Image::canvas($w, $h);
            $image = Image::make($avatar)->resize($w, $h, static function ($constraint) {
                $constraint->aspectRatio();
            });

            $canvas->insert($image);
            Log::info('Uploading avatar into folder '.public_path('uploads/avatars'));
            $canvas->save(public_path('uploads/avatars/'.$file_name));

            $req_data['avatar'] = $path;
        }

        // User needs to verify their new email address
        if ($user->email != $request->input('email')) {
            $req_data['email_verified_at'] = null;
        }

        $this->userRepo->update($req_data, $id);

        // We need to get a new instance of the user in order to send the verification email to the new email address
        if ($user->email != $request->input('email')) {
            $newUser = $this->userRepo->findWithoutFail($user->id);
            $newUser->sendEmailVerificationNotification();
        }

        // Save all of the user fields
        $userFields = UserField::where('internal', false)->get();
        foreach ($userFields as $field) {
            $field_name = 'field_'.$field->slug;
            UserFieldValue::updateOrCreate([
                'user_field_id' => $field->id,
                'user_id'       => $id,
            ], ['value' => $request->get($field_name)]);
        }

        // Dispatch event including whether an avatar has been updated
        ProfileUpdated::dispatch($user, $request->hasFile('avatar'));

        Flash::success('Profile updated successfully!');

        return redirect(route('frontend.profile.index'));
    }

    /**
     * Regenerate the user's API key
     */
    public function regen_apikey(Request $request): RedirectResponse
    {
        $user = User::find(Auth::user()->id);
        Log::info('Regenerating API key "'.$user->ident.'"');

        $user->api_key = Utils::generateApiKey();
        $user->save();

        flash('New API key generated!')->success();

        return redirect(route('frontend.profile.index'));
    }

    /**
     * Generate the ACARS config and send it to download
     */
    public function acars(Request $request): Response
    {
        /** @var User $user */
        $user = Auth::user();
        $domain = Utils::getRootDomain(config('app.url'));

        $config = json_encode([
            'ApiKey' => $user->api_key,
            'Domain' => $domain,
            'Name'   => config('app.name'),
            'Url'    => config('app.url'),
        ], JSON_PRETTY_PRINT);

        return response($config)->withHeaders([
            'Content-Type'        => 'application/json',
            'Content-Length'      => strlen($config),
            'Cache-Control'       => 'no-store, no-cache',
            'Content-Disposition' => 'attachment; filename="'.$domain.'.json"',
        ]);
    }
}
