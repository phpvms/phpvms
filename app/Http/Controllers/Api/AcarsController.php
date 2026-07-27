<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Controller;
use App\Enums\AcarsType;
use App\Enums\PirepState;
use App\Events\AcarsUpdate;
use App\Exceptions\PirepCancelled;
use App\Exceptions\PirepNotFound;
use App\Http\Requests\Acars\EventRequest;
use App\Http\Requests\Acars\LogRequest;
use App\Http\Requests\Acars\PositionRequest;
use App\Http\Resources\AcarsRouteResource;
use App\Http\Resources\PirepResource;
use App\Models\Acars;
use App\Models\Pirep;
use App\Models\PirepPosition;
use App\Services\GeoService;
use Carbon\Carbon;
use DateTime;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class AcarsController extends Controller
{
    /**
     * AcarsController constructor.
     */
    public function __construct(
        private readonly GeoService $geoSvc
    ) {}

    /**
     * Check if a PIREP is cancelled
     *
     *
     * @throws PirepCancelled
     */
    protected function checkCancelled(Pirep $pirep): void
    {
        if ($pirep->cancelled) {
            throw new PirepCancelled($pirep);
        }
    }

    protected function findPirepOrFail(string $id): Pirep
    {
        $pirep = Pirep::find($id);

        if (empty($pirep)) {
            throw new PirepNotFound($id);
        }

        return $pirep;
    }

    /**
     * Get all the active PIREPs
     *
     * @return mixed
     */
    public function live_flights()
    {
        // No filtering: a row in `pirep_positions` is what puts a flight on the
        // map, so the join is the whole membership test. The null-position
        // filter that used to be here is gone with the source it guarded — a
        // position row always carries real coordinates, seeded from the
        // departure airport at prefile.
        $pireps = Pirep::onLiveMap()->get();

        return PirepResource::collection($pireps);
    }

    /**
     * Return all of the flights (as points) in GeoJSON format
     */
    public function pireps_geojson(Request $request): JsonResponse
    {
        $pireps = Pirep::onLiveMap()->get();
        $positions = $this->geoSvc->getFeatureForLiveFlights($pireps);

        return response()->json([
            'data' => $positions,
        ]);
    }

    /**
     * Return the GeoJSON for the ACARS line
     */
    public function acars_geojson(string $pirep_id, Request $request): JsonResponse
    {
        $pirep = $this->findPirepOrFail($pirep_id);

        $geodata = $this->geoSvc->getFeatureFromAcars($pirep);

        return response()->json([
            'data' => $geodata,
        ]);
    }

    /**
     * Return the routes for the ACARS line
     */
    public function acars_get(string $id, Request $request): AcarsRouteResource
    {
        $this->findPirepOrFail($id);

        $acars = Acars::query()
            ->with('pirep')
            ->forPirep($id)
            ->flightPath()
            ->orderedBySimTime()
            ->get();

        return new AcarsRouteResource($acars);
    }

    /**
     * Post ACARS updates for a PIREP
     *
     *
     * @throws PirepCancelled
     * @throws BadRequestHttpException
     */
    public function acars_store(string $id, PositionRequest $request): JsonResponse
    {
        // Check if the status is cancelled...
        $pirep = $this->findPirepOrFail($id);

        $this->checkCancelled($pirep);

        /*Log::debug(
            'Posting ACARS update (user: '.Auth::user()->ident.', pirep id :'.$id.'): ',
            $request->post()
        );*/

        $count = 0;
        $positions = $request->post('positions');
        foreach ($positions as $position) {
            $position['pirep_id'] = $id;
            $position['type'] = AcarsType::FLIGHT_PATH;

            if (isset($position['altitude'])) {
                if (!isset($position['altitude_agl'])) {
                    $position['altitude_agl'] = $position['altitude'];
                }

                if (!isset($position['altitude_msl'])) {
                    $position['altitude_msl'] = $position['altitude'];
                }

                unset($position['altitude']);
            }

            if (isset($position['sim_time'])) {
                if ($position['sim_time'] instanceof DateTime) {
                    $position['sim_time'] = Carbon::instance($position['sim_time']);
                } else {
                    $position['sim_time'] = Carbon::createFromTimeString($position['sim_time']);
                }
            }

            if (isset($position['created_at'])) {
                if ($position['created_at'] instanceof DateTime) {
                    $position['created_at'] = Carbon::instance($position['created_at']);
                } else {
                    $position['created_at'] = Carbon::createFromTimeString($position['created_at']);
                }
            }

            try {
                DB::transaction(function () use ($position): void {
                    if (!empty($position['id'])) {
                        Acars::updateOrInsert(
                            ['id' => $position['id']],
                            $position
                        );
                    } else {
                        Acars::create($position);
                    }
                });

                $count++;
            } catch (QueryException $ex) {
                Log::info('Error on adding ACARS position: '.$ex->getMessage());
            }
        }

        // Change the PIREP status if it's as SCHEDULED before
        /*if ($pirep->status === PirepPhase::INITIATED) {
            $pirep->status = PirepPhase::AIRBORNE;
        }*/

        $pirep->save();

        $latest = $this->syncPosition($pirep);

        // Post a new update for this ACARS position. Still the `acars` row, not
        // the position row: this event's payload is not this change's to alter.
        event(new AcarsUpdate($pirep, $latest));

        return $this->message($count.' positions added', $count);
    }

    /**
     * Bring the PIREP's `pirep_positions` row up to date with the newest
     * breadcrumb it has.
     *
     * Nothing about what the batch writes to `acars` or to `pireps` changes;
     * this is purely additional. Together with prefiling, it is the only path
     * that writes a position row — the PIREP update and file endpoints are
     * deliberately untouched, so position data arrives at whatever cadence
     * clients post batches and the map is no fresher than it was.
     *
     * The newest point is resolved from `acars` server-side rather than taken
     * from the batch, which is what keeps an out-of-order or replayed batch from
     * moving the aircraft backwards: `acars`.`created_at` is when the client
     * collected the point, so the newest row for the PIREP is the newest
     * position however the batches carrying it happened to arrive. `order` is
     * the tiebreaker for points collected within the same second, since the
     * timestamp only resolves to one.
     *
     * Only an in-progress or pending PIREP gets a position row. Pending counts
     * because filing moves a PIREP there while its client may still be posting
     * the tail of the flight, and refusing those would freeze the marker short
     * of where the aircraft actually stopped — the flight is still drawn for
     * `livemap.live_time` after it finishes, so the position it is drawn at
     * should be its last one. Every other state is refused, so a cancelled,
     * rejected, accepted or already-evicted flight cannot be returned to the map
     * by a late batch.
     *
     * A refused batch still writes its `acars` rows: that endpoint's contract is
     * not this change's to alter, and only the position upsert is skipped.
     */
    private function syncPosition(Pirep $pirep): ?Acars
    {
        /** @var ?Acars $latest */
        $latest = Acars::query()
            ->forPirep($pirep->id)
            ->flightPath()
            ->orderBy('created_at', 'desc')
            ->orderBy('order', 'desc')
            ->orderBy('sim_time', 'desc')
            ->first();

        if ($latest === null
            || !in_array($pirep->state, [PirepState::IN_PROGRESS, PirepState::PENDING], true)
        ) {
            return $latest;
        }

        PirepPosition::updateOrCreate(
            ['pirep_id' => $pirep->id],
            [
                'user_id' => $pirep->user_id,
                // Phase comes off the PIREP, not off `acars`.`status`, which is
                // a different column with a different meaning.
                'phase'        => $pirep->status,
                'lat'          => $latest->lat ?? 0,
                'lon'          => $latest->lon ?? 0,
                'heading'      => $latest->heading ?? 0,
                'distance'     => $latest->distance?->internal(2) ?? 0,
                'altitude_agl' => $latest->altitude_agl ?? 0,
                'altitude_msl' => $latest->altitude_msl ?? 0,
                'vs'           => $latest->vs ?? 0,
                'gs'           => $latest->gs ?? 0,
                'ias'          => $latest->ias ?? 0,
                // Elapsed time and fuel burned live on the PIREP; `acars` has
                // neither. Its `fuel` column is fuel remaining, not fuel used.
                'flight_time' => $pirep->flight_time ?? 0,
                'fuel_used'   => $pirep->fuel_used?->internal(2) ?? 0,
            ]
        );

        return $latest;
    }

    /**
     * Post ACARS LOG update for a PIREP. These updates won't show up on the map
     * But rather in a log file.
     *
     *
     * @throws PirepCancelled
     * @throws BadRequestHttpException
     */
    public function acars_logs(string $id, LogRequest $request): JsonResponse
    {
        // Check if the status is cancelled...
        $pirep = $this->findPirepOrFail($id);

        $this->checkCancelled($pirep);

        // Log::debug('Posting ACARS log, PIREP: '.$id, $request->post());

        $count = 0;
        $logs = $request->post('logs');
        foreach ($logs as $log) {
            $log['pirep_id'] = $id;
            $log['type'] = AcarsType::LOG;

            if (isset($log['sim_time'])) {
                $log['sim_time'] = Carbon::createFromTimeString($log['sim_time']);
            }

            if (isset($log['created_at'])) {
                $log['created_at'] = Carbon::createFromTimeString($log['created_at']);
            }

            try {
                DB::transaction(function () use ($log): void {
                    if (isset($log['id'])) {
                        Acars::updateOrInsert(
                            ['id' => $log['id']],
                            $log
                        );
                    } else {
                        Acars::create($log);
                    }
                });

                $count++;
            } catch (QueryException $ex) {
                Log::info('Error on adding ACARS log: '.$ex->getMessage());
            }
        }

        return $this->message($count.' logs added', $count);
    }

    /**
     * Post ACARS LOG update for a PIREP. These updates won't show up on the map
     * But rather in a log file.
     *
     *
     * @throws PirepCancelled
     * @throws BadRequestHttpException
     */
    public function acars_events(string $id, EventRequest $request): JsonResponse
    {
        // Check if the status is cancelled...
        $pirep = $this->findPirepOrFail($id);

        $this->checkCancelled($pirep);

        Log::debug('Posting ACARS event, PIREP: '.$id, $request->post());

        $count = 0;
        $logs = $request->post('events');
        foreach ($logs as $log) {
            $log['pirep_id'] = $id;
            $log['type'] = AcarsType::LOG;
            $log['log'] = $log['event'];

            if (isset($log['sim_time'])) {
                $log['sim_time'] = Carbon::createFromTimeString($log['sim_time']);
            }

            if (isset($log['created_at'])) {
                $log['created_at'] = Carbon::createFromTimeString($log['created_at']);
            }

            try {
                DB::transaction(function () use ($log): void {
                    if (isset($log['id'])) {
                        Acars::updateOrInsert(
                            ['id' => $log['id']],
                            $log
                        );
                    } else {
                        Acars::create($log);
                    }
                });

                $count++;
            } catch (QueryException $ex) {
                Log::info('Error on adding ACARS event: '.$ex->getMessage());
            }
        }

        return $this->message($count.' logs added', $count);
    }
}
