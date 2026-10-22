<?php

declare(strict_types=1);

namespace App\Http\Data;

use App\Models\Airline;
use App\Models\User;
use App\Models\UserField;
use App\Support\Countries;
use App\Support\Timezonelist;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Current values plus option lists for the inline "Edit profile" card on the
 * SPA profile page. Only ever attached for the pilot's own profile
 * (ProfileController::show), so it is never a leak of another pilot's email.
 *
 * Mirrors the field set of the Blade form at
 * resources/views/layouts/seven/profile/fields.blade.php, and posts back to the
 * same ProfileController::update() route with the same input names — the SPA is
 * a second front end on one controller, not a second write path.
 *
 * Home airports are deliberately NOT shipped as an option list: the Blade form
 * resolves them through an async lookup too, and the airports table is
 * unbounded. The card searches `GET /api/airports/search`, which is public
 * (routes/api.php:53). `homeAirport` carries just the currently-selected one so
 * the control has something to show before the pilot types.
 */
#[TypeScript]
final class ProfileEditData extends Data
{
    /**
     * @param list<SelectOptionData>      $airlines
     * @param list<SelectOptionData>      $countries
     * @param list<SelectOptionData>      $timezones
     * @param list<ProfileEditFieldData>  $fields
     * @param list<ProfileConnectionData> $connections
     */
    public function __construct(
        public string $name,
        public string $email,
        public string $airlineId,
        public ?SelectOptionData $homeAirport,
        public ?string $country,
        public ?string $timezone,
        public ?string $simbriefUsername,
        public bool $optIn,
        public bool $hubsOnly,
        /** Own-profile only, same as the rest of this DTO. */
        public ?string $apiKey,
        public int $avatarWidth,
        public int $avatarHeight,
        public array $airlines,
        public array $countries,
        public array $timezones,
        public array $fields,
        public array $connections,
    ) {}

    /**
     * @param Collection<int, UserField>  $userFields  resolved via UserService::getUserFields()
     * @param list<ProfileConnectionData> $connections resolved by the caller, which owns the
     *                                                 OAuth services this DTO does not
     */
    public static function fromModel(User $user, Collection $userFields, array $connections): self
    {
        return new self(
            name: (string) $user->name,
            email: (string) $user->email,
            airlineId: (string) $user->airline_id,
            homeAirport: $user->home_airport === null ? null : new SelectOptionData(
                value: (string) $user->home_airport->id,
                label: (string) $user->home_airport->description,
            ),
            country: $user->country,
            timezone: $user->timezone,
            simbriefUsername: $user->simbrief_username,
            optIn: (bool) $user->opt_in,
            hubsOnly: (bool) setting('pilots.home_hubs_only'),
            apiKey: $user->api_key,
            avatarWidth: (int) config('phpvms.avatar.width'),
            avatarHeight: (int) config('phpvms.avatar.height'),
            airlines: SelectOptionData::fromMap(Airline::selectList()),
            countries: SelectOptionData::fromMap(Countries::getSelectList()),
            timezones: self::timezoneOptions(),
            fields: $userFields->map(ProfileEditFieldData::fromModel(...))->values()->all(),
            connections: $connections,
        );
    }

    /**
     * Timezonelist groups by region and pads its labels with `&nbsp;` entities
     * for Blade's raw echo. Vue interpolation escapes, so those would show up
     * literally — decode and collapse them, and fold the groups into one flat
     * searchable list.
     *
     * @return list<SelectOptionData>
     */
    private static function timezoneOptions(): array
    {
        return collect(Timezonelist::toArray())
            ->flatMap(fn (array $zones): array => $zones)
            ->map(fn (string $label): string => trim((string) preg_replace(
                '/\s+/u',
                ' ',
                html_entity_decode($label, ENT_QUOTES | ENT_HTML5),
            )))
            ->pipe(fn (Collection $labels): array => SelectOptionData::fromMap($labels->all()));
    }
}
