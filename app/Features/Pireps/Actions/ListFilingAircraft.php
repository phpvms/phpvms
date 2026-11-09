<?php

declare(strict_types=1);

namespace App\Features\Pireps\Actions;

use App\Models\Subfleet;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * The aircraft a pilot may pick on the PIREP form, grouped by subfleet type
 * (`[type => [aircraft id => "name - registration"]]`). When
 * `pireps.only_aircraft_at_dpt_airport` is on, only aircraft at the pilot's
 * current airport (or home airport) are listed.
 */
final class ListFilingAircraft
{
    /**
     * @return array<array-key, string|array<int, string>>
     */
    public function handle(User $user, bool $addBlank = false): array
    {
        $location = filled($user->curr_airport_id) ? $user->curr_airport_id : $user->home_airport_id;
        $locationCheck = setting('operations.only_aircraft_at_dpt_airport', false);

        $aircraft = [];

        if ($addBlank) {
            $aircraft[''] = '';
        }

        /** @var Collection<int, Subfleet> $subfleets */
        $subfleets = $user->allowedSubfleets()
            ->with([
                'aircraft' => fn ($q) => $q->when(
                    $locationCheck,
                    fn ($q2) => $q2->where('airport_id', $location),
                ),
            ])
            ->get();

        foreach ($subfleets as $subfleet) {
            $group = [];
            foreach ($subfleet->aircraft as $ac) {
                $group[$ac->id] = $ac->name.' - '.$ac->registration;
            }

            $aircraft[$subfleet->type] = $group;
        }

        return $aircraft;
    }
}
