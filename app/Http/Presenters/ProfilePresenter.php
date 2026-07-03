<?php

namespace App\Http\Presenters;

use App\Models\User;
use App\Support\Units\Time;
use Illuminate\Support\Collection;

/**
 * ProfilePresenter — dual-projection presenter for the profile page.
 *
 * Mirrors DashboardPresenter (Decision D2): one data-gathering path, two shapes.
 *   - toBladeArray()   → legacy {user, userFields, acars} for profile.index.blade
 *   - toInertiaArray() → flat, JSON-serializable DTO for the SPA Profile.vue
 *
 * Data (user relations + userFields) is gathered by the controller and passed
 * in, since userFields needs UserService — the presenter only projects.
 */
class ProfilePresenter
{
    /**
     * @param User                    $user       user with profile relations eager-loaded
     * @param Collection<int, object> $userFields resolved public UserField models (name + value)
     * @param bool                    $acars      whether ACARS is enabled
     */
    public function __construct(
        protected User $user,
        protected Collection $userFields,
        protected bool $acars,
    ) {}

    /** Named constructor. */
    public static function from(User $user, Collection $userFields, bool $acars): static
    {
        return new static($user, $userFields, $acars);
    }

    /**
     * Legacy Blade shape — exactly what profile.index expects today.
     *
     * @return array{user: User, userFields: Collection<int, object>, acars: bool}
     */
    public function toBladeArray(): array
    {
        return [
            'user'       => $this->user,
            'userFields' => $this->userFields,
            'acars'      => $this->acars,
        ];
    }

    /**
     * Flat SPA DTO — no Eloquent instances, only scalars/arrays/nulls.
     *
     * @return array<string, mixed>
     */
    public function toInertiaArray(): array
    {
        $u = $this->user;

        return [
            'id'                => $u->id,
            'name'              => $u->name,
            'avatar'            => $u->resolveAvatarUrl(),
            'airline'           => $u->airline ? ['name' => $u->airline->name, 'icao' => $u->airline->icao] : null,
            'rank'              => $u->rank ? ['name' => $u->rank->name] : null,
            'homeAirport'       => $this->airport($u->home_airport),
            'currentAirport'    => $this->airport($u->current_airport),
            'flights'           => (int) $u->flights,
            'flightTimeMinutes' => Time::minutesToTimeString((int) ($u->flight_time ?? 0)),
            'memberSince'       => $u->created_at?->toIso8601String(),
            'state'             => [
                'label' => $u->state->getLabel(),
                'color' => $u->state->getColor(),
            ],
            'awards'            => $u->awards->map(fn ($a) => [
                'name'        => $a->name,
                'description' => $a->description,
                'image'       => $a->image_url,
            ])->values()->all(),
            'typeRatings'       => $u->typeratings->map(fn ($t) => [
                'name' => $t->name,
                'type' => $t->type,
            ])->values()->all(),
            'fields'            => $this->userFields
                ->map(fn ($f) => ['name' => $f->name, 'value' => $f->value ?? null])
                ->values()
                ->all(),
            'acars'             => $this->acars,
        ];
    }

    /**
     * @return array{icao: string, name: string}|null
     */
    protected function airport(?object $airport): ?array
    {
        return $airport ? ['icao' => $airport->icao, 'name' => $airport->name] : null;
    }
}
