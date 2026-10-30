<?php

declare(strict_types=1);

namespace App\Features\Profile\Livewire\Forms;

use App\Features\Profile\Actions\UpdateProfile;
use App\Http\Data\ProfileEditData;
use App\Models\User;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Form;

/**
 * The "Edit profile" fields. Validates with the same rules as the PUT
 * `profile.update` route and saves through the same `UpdateProfile` action.
 *
 * Custom user fields live in `$fields`, keyed `field_{slug}` like the form post.
 */
class ProfileEditForm extends Form
{
    public string $name = '';

    public string $email = '';

    public string $airline_id = '';

    public ?string $home_airport_id = null;

    public ?string $country = null;

    public ?string $timezone = null;

    public ?string $simbrief_username = null;

    public bool $opt_in = false;

    public ?TemporaryUploadedFile $avatar = null;

    public ?string $password = null;

    public ?string $password_confirmation = null;

    /** @var array<string, string|null> */
    public array $fields = [];

    public function fillFrom(ProfileEditData $edit): void
    {
        $this->name = $edit->name;
        $this->email = $edit->email;
        $this->airline_id = $edit->airlineId;
        $this->home_airport_id = $edit->homeAirport?->value;
        $this->country = $edit->country;
        $this->timezone = $edit->timezone;
        $this->simbrief_username = $edit->simbriefUsername;
        $this->opt_in = $edit->optIn;
        $this->fields = collect($edit->fields)
            ->mapWithKeys(fn ($field): array => ['field_'.$field->slug => $field->value ?? ''])
            ->all();
    }

    /**
     * Validate and save. Throws a validation exception, with errors under
     * `form.*`, when the input is not valid.
     */
    public function save(User $user): User
    {
        $this->normalize();

        $this->validate($this->rulesFor($user));

        $payload = [
            'name'              => $this->name,
            'email'             => $this->email,
            'airline_id'        => $this->airline_id,
            'home_airport_id'   => $this->home_airport_id,
            'country'           => $this->country,
            'timezone'          => $this->timezone,
            'simbrief_username' => $this->simbrief_username,
            'opt_in'            => $this->opt_in,
        ];

        if ($this->password !== null) {
            $payload['password'] = $this->password;
        }

        $updated = app(UpdateProfile::class)->handle($user, $payload, $this->avatar, $this->fields);

        $this->reset('password', 'password_confirmation', 'avatar');

        return $updated;
    }

    /**
     * The controller lowercases the email, coerces `opt_in` and receives blank
     * inputs as null; do the same here.
     */
    private function normalize(): void
    {
        $this->email = mb_strtolower(trim($this->email));

        foreach (['home_airport_id', 'country', 'timezone', 'simbrief_username', 'password', 'password_confirmation'] as $property) {
            if ($this->{$property} === '') {
                $this->{$property} = null;
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function rulesFor(User $user): array
    {
        $rules = [];

        foreach (UpdateProfile::rules($user) as $key => $rule) {
            $rules[str_starts_with($key, 'field_') ? 'fields.'.$key : $key] = $rule;
        }

        return $rules;
    }
}
