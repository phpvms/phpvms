<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Contracts\Resource;
use App\Models\User;
use Override;

/**
 * @mixin User
 */
class UserResource extends Resource
{
    #[Override]
    public function toArray($request)
    {
        $this->resource->loadMissing('identities');
        $identityIds = $this->resource->identities->pluck('provider_user_id', 'connection_id');

        $res = [
            'id'                => $this->id,
            'pilot_id'          => $this->pilot_id,
            'ident'             => $this->ident,
            'name'              => $this->name_private,
            'name_private'      => $this->name_private,
            'avatar'            => $this->resolveAvatarUrl(),
            'discord_id'        => $identityIds->get('discord'),
            'vatsim_id'         => $identityIds->get('vatsim'),
            'ivao_id'           => $identityIds->get('ivao'),
            'simbrief_username' => $this->simbrief_username,
            'rank_id'           => $this->rank_id,
            'home_airport'      => $this->home_airport_id,
            'curr_airport'      => $this->curr_airport_id,
            'last_pirep_id'     => $this->last_pirep_id,
            'flights'           => $this->flights,
            'flight_time'       => $this->flight_time,
            'transfer_time'     => $this->transfer_time,
            'total_time'        => $this->flight_time,
            'timezone'          => $this->timezone,
            'state'             => $this->state,
        ];

        $res['airline'] = AirlineResource::make($this->whenLoaded('airline'));
        $res['bids'] = UserBidResource::collection($this->whenLoaded('bids'));
        $res['rank'] = RankResource::make($this->whenLoaded('rank'));
        $res['subfleets'] = SubfleetResource::make($this->whenLoaded('subfleets'));

        // If the transfer hours count, then set the total time to reflect that
        if (setting('pilots.count_transfer_hours', false) === true) {
            $res['total_time'] = $this->flight_time + $this->transfer_time;
        }

        return $res;
    }
}
