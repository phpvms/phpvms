<?php

namespace App\Http\Controllers\Frontend;

use App\Addons\AddonRegistry;
use App\Contracts\Controller;
use App\Events\ProfileUpdated;
use App\Models\Airline;
use App\Models\User;
use App\Models\UserField;
use App\Models\UserFieldValue;
use App\Services\UserService;
use App\Support\ApiScope;
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
        $acars = app(AddonRegistry::class)->find('VMSAcars');
        if ($acars) {
            return $acars->isEnabled();
        }

        return false;
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

        $request->merge([
            'email' => mb_strtolower(trim((string) $request->input('email'))),
        ]);

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
            $path = 'avatars/'.$file_name;

            // Create the avatar, resizing it and keeping the aspect ratio.
            // https://stackoverflow.com/a/26892028
            $w = config('phpvms.avatar.width');
            $h = config('phpvms.avatar.height');

            $canvas = Image::canvas($w, $h);
            $image = Image::make($avatar)->resize($w, $h, static function ($constraint): void {
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
     * Show the API connections page: authorized OAuth applications and the
     * user's personal access tokens.
     */
    public function connections(): View
    {
        /** @var User $user */
        $user = Auth::user();

        $tokens = $user->tokens()
            ->where('revoked', false)
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->with('client')
            ->latest('created_at')
            ->get()
            ->filter(fn ($token): bool => $token->client !== null);

        // Personal access tokens vs. third-party authorized applications.
        $personalTokens = $tokens->filter(fn ($token): bool => $token->client->hasGrantType('personal_access'));
        $authorizedApps = $tokens
            ->reject(fn ($token): bool => $token->client->hasGrantType('personal_access'))
            ->groupBy('client_id');

        return view('profile.connections', [
            'user'           => $user,
            'personalTokens' => $personalTokens,
            'authorizedApps' => $authorizedApps,
            'scopes'         => ApiScope::catalog(),
        ]);
    }

    /**
     * Create a personal access token with the selected scopes. The plaintext
     * token is flashed to the session for a one-time display.
     */
    public function store_token(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'scopes'   => 'nullable|array',
            'scopes.*' => 'string',
        ]);

        /** @var User $user */
        $user = Auth::user();

        // Only allow scopes that exist in the catalog (never the wildcard).
        $scopes = array_values(array_intersect(
            $validated['scopes'] ?? [],
            array_keys(ApiScope::catalog())
        ));

        $token = $user->createToken($validated['name'], $scopes);

        return redirect(route('frontend.profile.connections'))
            ->with('plain_text_token', $token->accessToken);
    }

    /**
     * Revoke one of the user's personal access tokens.
     */
    public function destroy_token(string $token_id): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $token = $user->tokens()->where('id', $token_id)->first();
        $token?->revoke();

        flash('Token revoked.')->success();

        return redirect(route('frontend.profile.connections'));
    }

    /**
     * Revoke every token an authorized application holds for this user.
     */
    public function destroy_connection(string $client_id): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $user->tokens()
            ->where('client_id', $client_id)
            ->get()
            ->each(fn ($token) => $token->revoke());

        flash('Application access revoked.')->success();

        return redirect(route('frontend.profile.connections'));
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
