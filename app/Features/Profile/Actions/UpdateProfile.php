<?php

declare(strict_types=1);

namespace App\Features\Profile\Actions;

use App\Events\ProfileUpdated;
use App\Models\User;
use App\Models\UserField;
use App\Models\UserFieldValue;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Intervention\Image\ImageManager;

/**
 * Save a pilot's own profile: details, optional new password, optional avatar,
 * and the custom user fields.
 */
final readonly class UpdateProfile
{
    /**
     * Intervention's manager is injected by class: its `Image` facade resolves the
     * container's `image` key, which Laravel 13 binds to its own image manager.
     */
    public function __construct(private ImageManager $images) {}

    /**
     * Validation rules for the profile form, including the required custom fields.
     *
     * @return array<string, mixed>
     */
    public static function rules(User $user): array
    {
        $rules = [
            'name'              => 'required',
            'email'             => 'required|unique:users,email,'.$user->id,
            'airline_id'        => 'required|exists:airlines,id',
            'password'          => ['string', 'nullable', 'confirmed', Password::default()],
            'avatar'            => 'nullable|mimes:jpeg,png,jpg',
            'simbrief_username' => 'nullable|string',
            'country'           => 'nullable|string',
            'timezone'          => 'nullable|string',
            'home_airport_id'   => 'nullable|exists:airports,id',
            'opt_in'            => 'boolean',
        ];

        $required = UserField::where(['show_on_registration' => true, 'required' => true, 'internal' => false])->get();
        foreach ($required as $field) {
            $rules['field_'.$field->slug] = 'required';
        }

        return $rules;
    }

    /**
     * @param array<string, mixed> $validated   output of rules(), email lowercased and opt_in a bool
     * @param array<string, mixed> $fieldValues `field_{slug}` => value for the custom fields
     */
    public function handle(User $user, array $validated, ?UploadedFile $avatar, array $fieldValues): User
    {
        unset($validated['avatar']);

        if (array_key_exists('password', $validated) && $validated['password'] !== null) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->fill($validated);

        if ($avatar instanceof UploadedFile) {
            $this->storeAvatar($user, $avatar);
        }

        // User needs to verify their new email address
        $emailChanged = $user->isDirty('email');
        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();
        $user->refresh();

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }

        foreach (UserField::where('internal', false)->get() as $field) {
            UserFieldValue::updateOrCreate([
                'user_field_id' => $field->id,
                'user_id'       => $user->id,
            ], ['value' => $fieldValues['field_'.$field->slug] ?? null]);
        }

        ProfileUpdated::dispatch($user, $avatar instanceof UploadedFile);

        return $user;
    }

    private function storeAvatar(User $user, UploadedFile $avatar): void
    {
        // Avatars live on the public_files disk, where the `avatar` accessor's File
        // model builds their URL. The accessor wraps the path, so read the raw one.
        $disk = Storage::disk(config('filesystems.public_files'));

        $previous = $user->getRawOriginal('avatar');
        if (is_string($previous) && $previous !== '') {
            $disk->delete($previous);
        }

        $extension = $avatar->getClientOriginalExtension();
        $path = 'avatars/'.$user->ident.'.'.$extension;

        // Create the avatar, resizing it and keeping the aspect ratio.
        // https://stackoverflow.com/a/26892028
        $w = config('phpvms.avatar.width');
        $h = config('phpvms.avatar.height');

        $canvas = $this->images->canvas($w, $h);
        $image = $this->images->make($avatar)->resize($w, $h, static function ($constraint): void {
            $constraint->aspectRatio();
        });

        $canvas->insert($image);
        Log::info('Uploading avatar to '.$path);
        $disk->put($path, (string) $canvas->encode(strtolower($extension)), 'public');

        $user->setAttribute('avatar', $path);
    }
}
