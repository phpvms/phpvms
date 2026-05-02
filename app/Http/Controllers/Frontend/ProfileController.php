<?php

namespace App\Http\Controllers\Frontend;

use App\Contracts\Controller;
use App\Events\ProfileUpdated;
use App\Models\Airline;
use App\Models\User;
use App\Models\UserField;
use App\Models\UserFieldValue;
use App\Services\UserService;
use App\Support\Countries;
use App\Support\Timezonelist;
use App\Support\Utils;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
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
        private readonly UserService $userSvc,
    ) {}

    /**
     * Return whether the vmsACARS module is enabled or not
     */
    private function acarsEnabled(): bool
    {
        // Is the ACARS module enabled?
        $acars_enabled = false;
        /** @var ?\Nwidart\Modules\Module $acars */
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
        /** @var ?User $user */
        $user = User::with($with)->where('id', $id)->first();

        if (!$user) {
            Flash::error('User not found!');

            return redirect(route('frontend.dashboard.index'));
        }

        $userFields = $this->userSvc->getUserFields($user, true);

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
     * @throws Exception
     */
    public function edit(Request $request): RedirectResponse|View
    {
        /** @var ?User $user */
        $user = User::with('fields.field', 'home_airport')->where('id', Auth::id())->first();

        if (empty($user)) {
            Flash::error('User not found!');

            return redirect(route('frontend.dashboard.index'));
        }

        $airports = $user->home_airport ? [$user->home_airport->id => $user->home_airport->description] : ['' => ''];

        $airlines = Airline::selectList();
        $userFields = $this->userSvc->getUserFields($user);

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

    public function update(Request $request): RedirectResponse
    {
        $id = Auth::user()->id;
        $user = User::find($id);

        if (!$user) {
            Flash::error('User not found!');

            return redirect(route('frontend.dashboard.index'));
        }

        $rules = [
            'name'              => 'required',
            'email'             => 'required|unique:users,email,'.$id,
            'airline_id'        => 'required|exists:airlines,id',
            'password'          => ['string', 'nullable', 'confirmed', Password::default()],
            'avatar'            => 'nullable|mimes:jpeg,png,jpg',
            'simbrief_username' => 'nullable|string',
            'country'           => 'nullable|string',
            'timezone'          => 'nullable|string',
            'home_airport_id'   => 'nullable|exists:airports,id',
        ];

        $userFields = UserField::where(
            ['show_on_registration' => true, 'required' => true, 'internal' => false]
        )->get();
        foreach ($userFields as $field) {
            $rules['field_'.$field->slug] = 'required';
        }

        $validated = $request->validate($rules);

        if (array_key_exists('password', $validated) && $validated['password'] !== null) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->fill($validated);

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

            $user->setAttribute('avatar', $path);
        }

        // User needs to verify their new email address
        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();
        $user->refresh();

        if ($user->email !== $request->input('email')) {
            $user->sendEmailVerificationNotification();
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
