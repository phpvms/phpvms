<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\FareType;
use App\Enums\FlightType;
use App\Models\Aircraft;
use App\Models\Fare;
use App\Models\Flight;
use App\Models\SimBrief;
use App\Models\SimBriefAttempt;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class SkylightSimBriefService
{
    public function __construct(
        private BidService $bidService,
        private FareService $fareService,
        private SimBriefService $simBriefService,
    ) {}

    public function begin(User $user, Flight $flight, ?int $aircraftId = null): SimBriefAttempt|SimBrief
    {
        $this->assertAvailable($user, $flight);

        $existing = $this->existingBriefingFor($user, $flight);
        if ($existing instanceof SimBrief) {
            return $existing;
        }

        $aircraftId ??= $user->bids()->where('flight_id', $flight->id)->value('aircraft_id');
        if ($aircraftId === null) {
            throw ValidationException::withMessages([
                'aircraftId' => 'Select an eligible aircraft before planning a SimBrief flight.',
            ]);
        }

        $aircraft = $this->bidService->eligibleAircraftQuery($flight, $user)
            ->whereKey($aircraftId)
            ->with(['subfleet.fares', 'sbaircraft', 'sbairframes'])
            ->first();
        if (!$aircraft instanceof Aircraft) {
            throw ValidationException::withMessages([
                'aircraftId' => 'This aircraft is no longer eligible. Refresh the aircraft list.',
            ]);
        }

        $fares = $this->fareService->getFareWithOverrides($aircraft->subfleet->fares, $flight->fares);

        return SimBriefAttempt::query()->create([
            'static_id'   => Str::nanoid(),
            'user_id'     => $user->id,
            'flight_id'   => $flight->id,
            'aircraft_id' => $aircraft->id,
            'fare_data'   => $this->planningFares($flight, $fares),
            'expires_at'  => Carbon::now('UTC')->addHours(2),
        ]);
    }

    public function existingBriefingFor(User $user, Flight $flight): ?SimBrief
    {
        return SimBrief::query()
            ->where('user_id', $user->id)
            ->where('flight_id', $flight->id)
            ->first();
    }

    public function attemptFor(User $user, string $staticId): SimBriefAttempt
    {
        return SimBriefAttempt::query()
            ->where('static_id', $staticId)
            ->where('user_id', $user->id)
            ->where('expires_at', '>', Carbon::now('UTC'))
            ->firstOrFail();
    }

    public function download(User $user, SimBriefAttempt $attempt): ?SimBrief
    {
        $simbrief = $this->simBriefService->downloadOfp(
            $user,
            $attempt->static_id,
            $attempt->static_id,
            $attempt->flight_id,
            (string) $attempt->aircraft_id,
            $attempt->fare_data ?? [],
        );

        if ($simbrief instanceof SimBrief) {
            $simbrief->static_id = $attempt->static_id;
            $simbrief->save();
            $attempt->delete();
        }

        return $simbrief;
    }

    public function briefingFor(User $user, string $id): SimBrief
    {
        return SimBrief::query()->whereKey($id)->where('user_id', $user->id)->firstOrFail();
    }

    public function cancel(User $user, string $id): SimBrief
    {
        $briefing = $this->briefingFor($user, $id);
        if ($briefing->pirep_id !== null) {
            throw ValidationException::withMessages(['briefing' => 'A briefing attached to a PIREP cannot be cancelled.']);
        }

        $briefing->delete();

        return $briefing;
    }

    public function regenerate(User $user, string $id): SimBrief
    {
        $briefing = $this->briefingFor($user, $id);
        if ($briefing->pirep_id !== null) {
            throw ValidationException::withMessages(['briefing' => 'A briefing attached to a PIREP cannot be regenerated.']);
        }

        $briefing->delete();

        return $briefing;
    }

    public function syncEdited(User $user, string $id): ?SimBrief
    {
        $briefing = $this->briefingFor($user, $id);
        if (!filled($briefing->static_id)) {
            throw ValidationException::withMessages(['briefing' => 'This briefing cannot be refreshed from SimBrief.']);
        }

        $fares = $briefing->fare_data === null ? [] : json_decode($briefing->fare_data, true);

        $updated = $this->simBriefService->downloadOfp(
            $user,
            $briefing->static_id,
            $briefing->id,
            (string) $briefing->flight_id,
            (string) $briefing->aircraft_id,
            is_array($fares) ? $fares : [],
        );

        if ($updated instanceof SimBrief) {
            $updated->static_id = $briefing->static_id;
            $updated->save();
        }

        return $updated;
    }

    public function assertAvailable(User $user, Flight $flight): void
    {
        if (!filled(setting('simbrief.api_key'))) {
            throw ValidationException::withMessages(['simbrief' => 'SimBrief is not configured.']);
        }

        if ((bool) setting('simbrief.only_bids', false)
            && !$user->bids()->where('flight_id', $flight->id)->exists()) {
            throw ValidationException::withMessages(['simbrief' => 'Place a bid before planning a SimBrief flight.']);
        }
    }

    /**
     * The legacy form generated its operational load sheet before opening the
     * provider. Persist that sheet on the server-owned attempt so polling and
     * a later PIREP handoff use the same selected loads.
     *
     * @param  Collection<int, Fare>                                                                        $fares
     * @return array<int, array{id: int, code: string, name: string, type: int, capacity: int, count: int}>
     */
    private function planningFares(Flight $flight, Collection $fares): array
    {
        $baggageWeight = $flight->flight_type === FlightType::CHARTER_PAX_ONLY
            ? setting('simbrief.charter_baggage_weight', 28)
            : setting('simbrief.noncharter_baggage_weight', 35);
        $loadFactor = $flight->load_factor ?? setting('flights.default_load_factor');
        $loadFactorVariance = $flight->load_factor_variance ?? setting('flights.load_factor_variance');
        $passengerMinimum = max((int) ($loadFactor - $loadFactorVariance), 0);
        $passengerMaximum = min((int) ($loadFactor + $loadFactorVariance), 100);
        $passengerMaximum = $passengerMaximum === 0 ? 100 : $passengerMaximum;

        $cargoMinimum = $passengerMinimum;
        $cargoMaximum = $passengerMaximum;
        if ((bool) setting('flights.use_cargo_load_factor', false)) {
            $cargoLoadFactor = $flight->load_factor ?? setting('flights.default_cargo_load_factor');
            $cargoLoadFactorVariance = $flight->load_factor_variance ?? setting('flights.cargo_load_factor_variance');
            $cargoMinimum = max((int) ($cargoLoadFactor - $cargoLoadFactorVariance), 0);
            $cargoMaximum = min((int) ($cargoLoadFactor + $cargoLoadFactorVariance), 100);
            $cargoMaximum = $cargoMaximum === 0 ? 100 : $cargoMaximum;
        }

        $passengerFares = $fares->filter(fn ($fare): bool => $fare->type === FareType::PASSENGER && filled($fare->capacity));
        $passengers = $passengerFares->map(fn ($fare): array => [
            'id'       => $fare->id,
            'code'     => $fare->code,
            'name'     => $fare->name,
            'type'     => FareType::PASSENGER->value,
            'capacity' => (int) $fare->capacity,
            'count'    => (int) floor(($fare->capacity * random_int($passengerMinimum, $passengerMaximum)) / 100),
        ]);
        $passengerCount = (int) $passengers->sum('count');
        $baggageLoad = setting('units.weight') === 'kg'
            ? (int) round(($baggageWeight * $passengerCount) / 2.205)
            : (int) round($baggageWeight * $passengerCount);

        $cargo = $fares
            ->filter(fn ($fare): bool => $fare->type === FareType::CARGO && filled($fare->capacity))
            ->map(fn ($fare): array => [
                'id'       => $fare->id,
                'code'     => $fare->code,
                'name'     => $fare->name,
                'type'     => FareType::CARGO->value,
                'capacity' => (int) $fare->capacity,
                'count'    => (int) ceil((($fare->capacity - $baggageLoad) * random_int($cargoMinimum, $cargoMaximum)) / 100),
            ]);

        return $passengers->concat($cargo)->values()->all();
    }
}
