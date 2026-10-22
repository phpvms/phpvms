<?php

declare(strict_types=1);

namespace App\Http\Data;

use App\Features\Tour\Models\UserTour;
use App\Models\Award;
use App\Models\User;
use App\Models\UserField;
use App\Support\Units\Time;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Flat SPA projection of the pilot profile. SPA-only; the Blade path keeps the
 * User model + userFields collection. Mirrors the retired ProfilePresenter.
 */
#[TypeScript]
final class ProfileData extends Data
{
    /**
     * @param list<AwardData>       $awards
     * @param list<TypeRatingData>  $typeRatings
     * @param list<UserFieldData>   $fields
     * @param list<ProfileTourData> $tours
     */
    public function __construct(
        public int $id,
        public string $name,
        public ?string $avatar,
        public ?AirlineRefData $airline,
        public ?RankData $rank,
        public ?AirportRefData $homeAirport,
        public ?AirportRefData $currentAirport,
        public int $flights,
        public string $flightTimeMinutes,
        public ?string $memberSince,
        public StateBadgeData $state,
        public array $awards,
        public array $typeRatings,
        public array $fields,
        public array $tours,
        public bool $acars,
        public bool $isOwnProfile,
    ) {}

    /**
     * @param Collection<int, UserField> $userFields resolved public UserField models (name + value)
     */
    public static function fromModel(User $u, Collection $userFields, bool $acars, bool $isOwnProfile): self
    {
        return new self(
            id: $u->id,
            // A stranger sees the GDPR-shortened name, same as the Blade path
            // (`$user->name_private`); the owner sees their own full name.
            name: $isOwnProfile ? $u->name : $u->name_private,
            avatar: $u->resolveAvatarUrl(),
            airline: $u->airline ? new AirlineRefData(icao: $u->airline->icao, name: $u->airline->name) : null,
            rank: $u->rank ? new RankData(name: $u->rank->name) : null,
            homeAirport: AirportRefData::fromModel($u->home_airport),
            currentAirport: AirportRefData::fromModel($u->current_airport),
            flights: (int) $u->flights,
            flightTimeMinutes: Time::minutesToTimeString((int) ($u->flight_time ?? 0)),
            memberSince: $u->created_at?->toIso8601String(),
            state: new StateBadgeData(
                label: $u->state->getLabel(),
                color: self::colorToken($u->state->getColor()),
            ),
            awards: $u->awards
                ->map(fn (Award $a): AwardData => AwardData::fromModel($a))
                ->values()
                ->all(),
            typeRatings: $u->typeratings
                ->map(fn ($t): TypeRatingData => new TypeRatingData(name: $t->name, type: $t->type))
                ->values()
                ->all(),
            fields: $userFields
                ->map(fn ($f): UserFieldData => new UserFieldData(name: $f->name, value: $f->value ?? null))
                ->values()
                ->all(),
            tours: $u->tours
                ->map(fn (UserTour $t): ProfileTourData => ProfileTourData::fromModel($t))
                ->values()
                ->all(),
            acars: $acars,
            isOwnProfile: $isOwnProfile,
        );
    }

    /** Coerce a Filament HasColor value (string|array|null) to a single token. */
    private static function colorToken(mixed $color): string
    {
        if (is_string($color)) {
            return $color;
        }

        return is_array($color) ? (string) (array_key_first($color) ?? 'gray') : 'gray';
    }
}
