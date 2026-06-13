<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Flight;
use App\Models\User;
use DB;
use Illuminate\Database\Eloquent\Builder;

final readonly class SubfleetAccessPolicy
{
    public bool $rankRestricted;

    public bool $typeRatingRestricted;

    public bool $restrictToDepartureAirport;

    public bool $blockBookedAircraft;

    public function __construct(
        public User $user,
        public ?Flight $flight = null,
    ) {
        $this->rankRestricted = (bool) setting('pireps.restrict_aircraft_to_rank', true);
        $this->typeRatingRestricted = (bool) setting('pireps.restrict_aircraft_to_typerating', false);
        $this->restrictToDepartureAirport = (bool) setting('pireps.only_aircraft_at_dpt_airport', false);
        $this->blockBookedAircraft = (bool) setting('bids.block_aircraft', false);
    }

    /**
     * Apply the rank + type-rating intersection to a Subfleet query.
     * No constraints are added when both restriction settings are off.
     */
    public function applyToSubfleets(Builder $query): Builder
    {
        if ($this->rankRestricted) {
            $query->whereExists(function ($sub): void {
                $sub->select(DB::raw(1))
                    ->from('subfleet_rank')
                    ->whereColumn('subfleet_rank.subfleet_id', 'subfleets.id')
                    ->where('subfleet_rank.rank_id', $this->user->rank_id);
            });
        }

        if ($this->typeRatingRestricted) {
            $query->whereExists(function ($sub): void {
                $sub->select(DB::raw(1))
                    ->from('typerating_subfleet')
                    ->join(
                        'typerating_user',
                        'typerating_user.typerating_id',
                        '=',
                        'typerating_subfleet.typerating_id'
                    )
                    ->whereColumn('typerating_subfleet.subfleet_id', 'subfleets.id')
                    ->where('typerating_user.user_id', $this->user->id);
            });
        }

        return $query;
    }

    /**
     * Apply subfleet, departure-airport, and bid-block constraints to an Aircraft query.
     */
    public function applyToAircraft(Builder $query): Builder
    {
        $query->whereHas('subfleet', fn (Builder $sub): Builder => $this->applyToSubfleets($sub));

        if ($this->restrictToDepartureAirport && $this->flight instanceof Flight) {
            $query->where('aircraft.airport_id', $this->flight->dpt_airport_id);
        }

        if ($this->blockBookedAircraft) {
            $query->whereNotExists(function ($sub): void {
                $sub->select(DB::raw(1))
                    ->from('bids')
                    ->whereColumn('bids.aircraft_id', 'aircraft.id')
                    ->where('bids.user_id', '!=', $this->user->id);
            });
        }

        return $query;
    }
}
