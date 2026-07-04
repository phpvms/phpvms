<?php

declare(strict_types=1);

namespace App\Http\Data;

use App\Models\User;
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
     * @param list<AwardData>      $awards
     * @param list<TypeRatingData> $typeRatings
     * @param list<UserFieldData>  $fields
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
        public bool $acars,
    ) {}

    /**
     * @param Collection<int, object> $userFields resolved public UserField models (name + value)
     */
    public static function fromModel(User $u, Collection $userFields, bool $acars): self
    {
        return new self(
            id: $u->id,
            name: $u->name,
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
                ->map(fn ($a): AwardData => new AwardData(name: $a->name, description: $a->description, image: $a->image_url))
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
            acars: $acars,
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
