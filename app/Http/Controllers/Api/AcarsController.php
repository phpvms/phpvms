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
        // No filtering: the join is the whole membership test.
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
        $latest = null;
        $latestAt = null;
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
                $written = null;

                DB::transaction(function () use ($position, &$written): void {
                    if (!empty($position['id'])) {
                        Acars::updateOrInsert(
                            ['id' => $position['id']],
                            $position
                        );

                        $written = new Acars($position);
                    } else {
                        $written = Acars::create($position);
                    }
                });

                // Newest by collection time, not by position in the array: a
                // batch can carry its points in any order.
                $collectedAt = $position['created_at'] ?? Carbon::now('UTC');

                if ($latestAt === null || $collectedAt->greaterThanOrEqualTo($latestAt)) {
                    $latest = $written;
                    $latestAt = $collectedAt;
                }

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

        $this->syncPosition($pirep, $latest);

        // Still the acars row, not the position row - this event's payload is unchanged.
        event(new AcarsUpdate($pirep, $latest));

        return $this->message($count.' positions added', $count);
    }

    /**
     * Upsert the position row from the newest point the batch carried.
     *
     * Only IN_PROGRESS and PENDING get a row. PENDING because filing happens while a
     * client may still be posting the tail of the flight. Refused batches still write
     * their `acars` rows.
     */
    private function syncPosition(Pirep $pirep, ?Acars $latest): void
    {
        if (!$latest instanceof Acars
            || !in_array($pirep->state, [PirepState::IN_PROGRESS, PirepState::PENDING], true)
        ) {
            return;
        }

        PirepPosition::updateOrCreate(
            ['pirep_id' => $pirep->id],
            [
                'user_id' => $pirep->user_id,
                // Off the PIREP, not `acars`.`status` - a different column entirely.
                'phase'        => $pirep->status->value,
                'lat'          => $latest->lat ?? 0,
                'lon'          => $latest->lon ?? 0,
                'heading'      => $latest->heading ?? 0,
                'distance'     => $latest->distance?->internal(2) ?? 0,
                'altitude_agl' => $latest->altitude_agl ?? 0,
                'altitude_msl' => $latest->altitude_msl ?? 0,
                'vs'           => $latest->vs ?? 0,
                'gs'           => $latest->gs ?? 0,
                'ias'          => $latest->ias ?? 0,
                // On the PIREP: `acars` has neither, and its `fuel` is fuel remaining.
                'flight_time' => $pirep->flight_time ?? 0,
                'fuel_used'   => $pirep->fuel_used?->internal(2) ?? 0,
            ]
        );
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
