<?php

namespace App\Services\Pirep;

use App\Enums\AcarsType;
use App\Enums\PirepStatus;
use App\Models\Acars;
use App\Models\Pirep;
use Illuminate\Support\Collection;

class PerformanceChartService
{
    public const int MAX_POINTS = 500;

    /**
     * Build the chart payload for a PIREP. Returns null when no ACARS samples
     * exist — the blade renders an empty stub instead of a chart container.
     *
     * @return array{
     *     sample_count: int,
     *     series: array<string, array<string, mixed>>,
     *     phases: array<int, array{code: string, label: string, start: int, end: int}>,
     *     meta: array<string, mixed>,
     *     landing: array<string, mixed>|null,
     *     summary: array{climb_seconds: int, cruise_seconds: int, descent_seconds: int, cruise_altitude: ?int},
     * }|null
     */
    public function buildDatasets(Pirep $pirep): ?array
    {
        // Inline the Acars query rather than calling its `ofType` / `orderedByCreatedAt`
        // scopes — larastan does not forward `#[Scope]` attribute methods through
        // HasMany relation builders, so the scoped form trips a false-positive
        // method.notFound at PHPStan level 5 even though both scopes exist at runtime.
        $samples = $pirep->acars()
            ->where('type', AcarsType::FLIGHT_PATH)
            ->orderBy('created_at', 'asc')
            ->get();

        if ($samples->isEmpty()) {
            return null;
        }

        $reduced = $this->downsample($samples, self::MAX_POINTS);
        $phases = $this->detectPhases($pirep, $reduced);

        return [
            'sample_count' => $samples->count(),
            'series'       => [
                'altitude' => $this->altitudeSeries($reduced),
                'speed'    => $this->speedSeries($reduced),
                'fuel'     => $this->fuelSeries($reduced),
                'vs'       => $this->vsSeries($reduced),
            ],
            'phases'  => $phases,
            'meta'    => $this->buildMeta($reduced),
            'landing' => $this->buildLandingBlock($pirep),
            'summary' => $this->buildSummary($phases, $reduced),
        ];
    }

    /**
     * Compact phase-timing summary rendered as four stat boxes beneath the
     * performance chart. Bucket per PirepStatus code:
     *
     *   - climb   : TAKEOFF, INIT_CLIM, AIRBORNE
     *   - cruise  : ENROUTE
     *   - descent : APPROACH, APPROACH_ICAO, ON_FINAL, LANDING, EMERG_DESCENT
     *
     * Cruise altitude is the max altitude observed inside any cruise phase;
     * falls back to the overall max when no cruise phase was detected so a
     * VFR / short hop without classified cruise still shows a number.
     *
     * @param  array<int, array{code: string, label: string, start: int, end: int}>                        $phases
     * @return array{climb_seconds: int, cruise_seconds: int, descent_seconds: int, cruise_altitude: ?int}
     */
    private function buildSummary(array $phases, Collection $samples): array
    {
        $climbCodes = ['TOF', 'ICL', 'TKO'];
        $cruiseCodes = ['ENR'];
        $descentCodes = ['TEN', 'APR', 'FIN', 'LDG', 'EMG'];

        $climb = 0;
        $cruise = 0;
        $descent = 0;
        $cruiseRanges = [];

        foreach ($phases as $phase) {
            $duration = max(0, $phase['end'] - $phase['start']);

            if (in_array($phase['code'], $climbCodes, true)) {
                $climb += $duration;
            } elseif (in_array($phase['code'], $cruiseCodes, true)) {
                $cruise += $duration;
                $cruiseRanges[] = [$phase['start'], $phase['end']];
            } elseif (in_array($phase['code'], $descentCodes, true)) {
                $descent += $duration;
            }
        }

        $cruiseAltitude = $this->maxAltitudeInRanges($samples, $cruiseRanges);

        if ($cruiseAltitude === null) {
            $alts = $samples
                ->map(fn ($s): ?float => $s->altitude_msl !== null ? (float) $s->altitude_msl : null)
                ->filter(fn (?float $v): bool => $v !== null)
                ->all();

            $cruiseAltitude = $alts === [] ? null : (int) max($alts);
        }

        return [
            'climb_seconds'   => $climb,
            'cruise_seconds'  => $cruise,
            'descent_seconds' => $descent,
            'cruise_altitude' => $cruiseAltitude,
        ];
    }

    /**
     * Peak altitude among samples whose timestamp falls inside any of the
     * supplied [start, end] ranges. Returns null when no sample falls in
     * range or all in-range samples are missing altitude.
     *
     * @param array<int, array{0: int, 1: int}> $ranges
     */
    private function maxAltitudeInRanges(Collection $samples, array $ranges): ?int
    {
        if ($ranges === []) {
            return null;
        }

        $max = null;

        foreach ($samples as $s) {
            if ($s->altitude_msl === null) {
                continue;
            }

            $ts = $this->ts($s);
            foreach ($ranges as [$start, $end]) {
                if ($ts >= $start && $ts <= $end) {
                    $alt = (float) $s->altitude_msl;
                    if ($max === null || $alt > $max) {
                        $max = $alt;
                    }

                    break;
                }
            }
        }

        return $max === null ? null : (int) $max;
    }

    /**
     * Pull departure + arrival runway metrics and landing scorecard data
     * from the PIREP's custom field values. Returns null when nothing
     * usable is present.
     *
     * Field-name lookups are case-insensitive substring matches against
     * the names ACARS clients use today (e.g. "Departure Runway",
     * "Landing Rate"). Storing them in this service rather than a config
     * file keeps the mapping next to where it's consumed; if more clients
     * adopt different naming, this becomes the one place to extend.
     *
     * @return array<string, mixed>|null
     */
    private function buildLandingBlock(Pirep $pirep): ?array
    {
        $fields = $pirep->field_values
            ->mapWithKeys(fn ($f): array => [strtolower((string) $f->name) => $f->value]);

        if ($fields->isEmpty()) {
            return null;
        }

        $get = fn (string $needle): ?string => $fields
            ->first(fn ($_v, $k): bool => str_contains($k, $needle));

        $departure = [
            'runway'            => $get('departure runway'),
            'heading_deviation' => $this->toFloat($get('departure heading deviation')),
            'centerline_offset' => $this->toFloat($get('departure centerline deviation')),
        ];

        $arrival = [
            'runway'                 => $get('arrival runway'),
            'heading_deviation'      => $this->toFloat($get('arrival heading deviation')),
            'centerline_offset'      => $this->toFloat($get('arrival centerline deviation')),
            'threshold_distance'     => $this->toFloat($get('arrival threshold distance')),
            'threshold_crossing_alt' => $this->toFloat($get('arrival threshold crossing height')),
        ];

        // Landing scorecard — raw values exposed alongside normalized 0–100
        // scores (where 100 = ideal). The frontend polar chart consumes the
        // scores; the table beneath shows the raw values for context.
        $landingRate = $this->toFloat($get('landing rate'));
        $landingG = $this->toFloat($get('landing g-force'));
        $landingPitch = $this->toFloat($get('landing pitch'));
        $landingRoll = $this->toFloat($get('landing roll'));

        $scorecard = [
            'rate'       => ['value' => $landingRate,  'score' => $this->scoreLandingRate($landingRate)],
            'g_force'    => ['value' => $landingG,     'score' => $this->scoreGForce($landingG)],
            'pitch'      => ['value' => $landingPitch, 'score' => $this->scorePitch($landingPitch)],
            'roll'       => ['value' => $landingRoll,  'score' => $this->scoreRoll($landingRoll)],
            'centerline' => ['value' => $arrival['centerline_offset'], 'score' => $this->scoreCenterline($arrival['centerline_offset'])],
            'heading'    => ['value' => $arrival['heading_deviation'], 'score' => $this->scoreHeading($arrival['heading_deviation'])],
        ];

        return [
            'departure' => $departure,
            'arrival'   => $arrival,
            'scorecard' => $scorecard,
        ];
    }

    private function toFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * Score landing rate (fpm). Ideal touchdown: -100 to -300 fpm.
     * Smoother (less negative) is still good; harder degrades fast.
     * Returns 0–100.
     */
    private function scoreLandingRate(?float $fpm): float
    {
        if ($fpm === null) {
            return 0.0;
        }

        $rate = abs($fpm);

        return match (true) {
            $rate <= 200.0  => 100.0,
            $rate <= 400.0  => 100.0 - ($rate - 200.0) / 200.0 * 30.0, // 100 → 70
            $rate <= 600.0  => 70.0 - ($rate - 400.0) / 200.0 * 40.0,  // 70 → 30
            $rate <= 1000.0 => 30.0 - ($rate - 600.0) / 400.0 * 30.0, // 30 → 0
            default         => 0.0,
        };
    }

    /** G-force at touchdown. Ideal ≤ 1.2g; hard landing 1.5g+. */
    private function scoreGForce(?float $g): float
    {
        if ($g === null) {
            return 0.0;
        }

        return match (true) {
            $g <= 1.2 => 100.0,
            $g <= 1.5 => 100.0 - ($g - 1.2) / 0.3 * 40.0,  // 100 → 60
            $g <= 2.0 => 60.0 - ($g - 1.5) / 0.5 * 60.0,   // 60 → 0
            default   => 0.0,
        };
    }

    /** Landing pitch (degrees nose-up). Ideal 2–6° for transport jets. */
    private function scorePitch(?float $pitch): float
    {
        if ($pitch === null) {
            return 0.0;
        }

        return match (true) {
            $pitch >= 2.0 && $pitch <= 6.0 => 100.0,
            $pitch >= 0.0 && $pitch < 2.0  => 60.0 + $pitch / 2.0 * 40.0,
            $pitch > 6.0 && $pitch <= 10.0 => 100.0 - ($pitch - 6.0) / 4.0 * 70.0,
            default                        => 0.0,
        };
    }

    /** Roll at touchdown (degrees). Ideal ≤ 1°; >5° is bad. */
    private function scoreRoll(?float $deg): float
    {
        if ($deg === null) {
            return 0.0;
        }

        $absDeg = abs($deg);

        return match (true) {
            $absDeg <= 1.0 => 100.0,
            $absDeg <= 3.0 => 100.0 - ($absDeg - 1.0) / 2.0 * 40.0,
            $absDeg <= 5.0 => 60.0 - ($absDeg - 3.0) / 2.0 * 60.0,
            default        => 0.0,
        };
    }

    /** Centerline deviation (meters/feet, units TBD by client). Tight ≤3, OK ≤10, ugly >20. */
    private function scoreCenterline(?float $offset): float
    {
        if ($offset === null) {
            return 0.0;
        }

        $abs = abs($offset);

        return match (true) {
            $abs <= 3.0  => 100.0,
            $abs <= 10.0 => 100.0 - ($abs - 3.0) / 7.0 * 30.0,
            $abs <= 20.0 => 70.0 - ($abs - 10.0) / 10.0 * 50.0,
            default      => 0.0,
        };
    }

    /** Heading deviation from runway (degrees). Crosswind landings ≤5° normal. */
    private function scoreHeading(?float $deg): float
    {
        if ($deg === null) {
            return 0.0;
        }

        $abs = abs($deg);

        return match (true) {
            $abs <= 1.0  => 100.0,
            $abs <= 3.0  => 100.0 - ($abs - 1.0) / 2.0 * 30.0,
            $abs <= 5.0  => 70.0 - ($abs - 3.0) / 2.0 * 40.0,
            $abs <= 10.0 => 30.0 - ($abs - 5.0) / 5.0 * 30.0,
            default      => 0.0,
        };
    }

    private function downsample(Collection $samples, int $maxPoints): Collection
    {
        $total = $samples->count();

        if ($total <= $maxPoints) {
            return $samples->values();
        }

        $step = (int) ceil($total / $maxPoints);
        $lastIndex = $total - 1;

        // Keep every Nth sample AND always keep the final sample so the chart
        // shows touchdown / arrival even when (count - 1) is not divisible by
        // step. Index 0 is already preserved by the modulo (0 % step === 0).
        return $samples->values()
            ->filter(fn ($_, int $i): bool => $i % $step === 0 || $i === $lastIndex)
            ->values();
    }

    /** @return array{data: array<int, array{0: int, 1: float|null}>, min: float, max: float, avg_cruise: float|null} */
    private function altitudeSeries(Collection $samples): array
    {
        $points = $samples->map(fn ($s): array => [$this->ts($s), $s->altitude_msl])->all();
        $alts = array_filter(array_column($points, 1), fn ($v): bool => $v !== null);

        return [
            'data'       => $points,
            'min'        => $alts === [] ? 0.0 : (float) min($alts),
            'max'        => $alts === [] ? 0.0 : (float) max($alts),
            'avg_cruise' => null, // populated when phase detection runs
        ];
    }

    /** @return array{gs: array<int, array{0: int, 1: int|null}>, ias: array<int, array{0: int, 1: int|null}>, gs_max: int} */
    private function speedSeries(Collection $samples): array
    {
        $gs = $samples->map(fn ($s): array => [$this->ts($s), $s->gs])->all();
        $ias = $samples->map(fn ($s): array => [$this->ts($s), $s->ias])->all();

        return [
            'gs'     => $gs,
            'ias'    => $ias,
            'gs_max' => (int) max(0, ...array_filter(array_column($gs, 1))),
        ];
    }

    /** @return array{data: array<int, array{0: int, 1: float|null}>, flow_avg: float|null} */
    private function fuelSeries(Collection $samples): array
    {
        // Preserve legitimate zero-fuel samples — `$s->fuel ? ... : null` would drop them.
        $points = $samples->map(fn ($s): array => [$this->ts($s), $s->fuel->toUnit('lbs') !== null ? (float) $s->fuel->toUnit('lbs') : null])->all();
        $flows = array_filter($samples->pluck('fuel_flow')->all(), fn ($v): bool => $v !== null);

        return [
            'data'     => $points,
            'flow_avg' => $flows === [] ? null : array_sum($flows) / count($flows),
        ];
    }

    /** @return array{data: array<int, array{0: int, 1: float|null}>, max_climb: float, max_descent: float} */
    private function vsSeries(Collection $samples): array
    {
        $points = $samples->map(fn ($s): array => [$this->ts($s), $s->vs])->all();
        $vs = array_filter(array_column($points, 1), fn ($v): bool => $v !== null);

        return [
            'data'        => $points,
            'max_climb'   => $vs === [] ? 0.0 : (float) max($vs),
            'max_descent' => $vs === [] ? 0.0 : (float) min($vs),
        ];
    }

    /**
     * Log-substring → PirepStatus marker table. Ordered by typical flight
     * sequence; substrings matched case-insensitively against the first
     * occurrence of each row in the LOG stream (with "flaps set to up"
     * gated to fire only after takeoff — pre-takeoff flap retract and
     * post-landing flap stow share the same string).
     *
     * @var array<int, array{needle: string, status: PirepStatus, after_takeoff: bool}>
     */
    private const array LOG_MARKERS = [
        ['needle' => 'started boarding',  'status' => PirepStatus::BOARDING,      'after_takeoff' => false],
        ['needle' => 'started pushback',  'status' => PirepStatus::PUSHBACK_TOW,  'after_takeoff' => false],
        ['needle' => 'started taxi out',  'status' => PirepStatus::TAXI,          'after_takeoff' => false],
        ['needle' => 'started takeoff',   'status' => PirepStatus::TAKEOFF,       'after_takeoff' => false],
        ['needle' => 'flaps set to up',   'status' => PirepStatus::ENROUTE,       'after_takeoff' => true],
        ['needle' => 'on approach',       'status' => PirepStatus::APPROACH_ICAO, 'after_takeoff' => true],
        ['needle' => 'on final approach', 'status' => PirepStatus::ON_FINAL,      'after_takeoff' => true],
        ['needle' => 'landing rate',      'status' => PirepStatus::LANDING,       'after_takeoff' => true],
        ['needle' => 'blocks on time',    'status' => PirepStatus::ON_BLOCK,      'after_takeoff' => true],
    ];

    /**
     * Phase detection strategy:
     *
     * 1. If FLIGHT_PATH samples carry real per-sample status (anything other
     *    than the default 'SCH'), emit one phase per contiguous status run.
     * 2. Otherwise scan the LOG rows for known marker substrings and derive
     *    phases from marker timestamps.
     * 3. If neither produces phases (no logs either), fall back to a VS-
     *    derived heuristic so the chart still gets some shading.
     *
     * @return array<int, array{code: string, label: string, start: int, end: int}>
     */
    private function detectPhases(Pirep $pirep, Collection $samples): array
    {
        $hasRealStatus = $samples->contains(fn ($s): bool => $s->status !== null && $s->status !== 'SCH');

        if ($hasRealStatus) {
            return $this->collapseToPhases(
                $samples,
                fn ($s): string => (string) ($s->status ?? 'SCH'),
            );
        }

        $fromLogs = $this->detectPhasesFromLogs($pirep, $samples);
        if ($fromLogs !== []) {
            return $fromLogs;
        }

        return $this->detectPhasesFromVs($samples);
    }

    /**
     * Scan ACARS LOG rows for known marker substrings (boarding / pushback /
     * taxi / takeoff / enroute / approach / final / landing / on-block) and
     * emit one phase per consecutive marker pair. Final phase end-anchored
     * to the last flight-path sample.
     *
     * Returns an empty array when the LOG stream produces no markers — the
     * caller then falls back to the VS heuristic.
     *
     * @return array<int, array{code: string, label: string, start: int, end: int}>
     */
    private function detectPhasesFromLogs(Pirep $pirep, Collection $samples): array
    {
        // Inline rather than using the `acars()` relation (which prescopes to
        // FLIGHT_PATH) or `acars_logs()` (which orders desc) — we need LOG
        // rows ordered ascending so the first-match-wins marker scan picks up
        // markers in flight-time order.
        $logs = $pirep->hasMany(Acars::class, 'pirep_id')
            ->where('type', AcarsType::LOG)
            ->whereNotNull('log')
            ->orderBy('created_at', 'asc')
            ->get();

        if ($logs->isEmpty()) {
            return [];
        }

        $takeoffSeen = false;
        $matched = [];

        foreach ($logs as $logRow) {
            $haystack = strtolower((string) $logRow->log);
            $ts = $this->ts($logRow);

            foreach (self::LOG_MARKERS as $i => $marker) {
                if (isset($matched[$i])) {
                    continue;
                }

                if ($marker['after_takeoff'] && !$takeoffSeen) {
                    continue;
                }

                if (!str_contains($haystack, $marker['needle'])) {
                    continue;
                }

                $matched[$i] = ['status' => $marker['status'], 'ts' => $ts];

                if ($marker['status'] === PirepStatus::TAKEOFF) {
                    $takeoffSeen = true;
                }

                // Each log row maps to at most one marker — first match wins.
                break;
            }
        }

        if ($matched === []) {
            return [];
        }

        // Preserve LOG_MARKERS table order rather than match-discovery order.
        ksort($matched);
        $ordered = array_values($matched);

        $phases = [];
        $endAnchor = $this->ts($samples->last());

        foreach ($ordered as $idx => $entry) {
            $next = $ordered[$idx + 1] ?? null;

            $phases[] = [
                'code'  => $entry['status']->value,
                'label' => $entry['status']->value,
                'start' => $entry['ts'],
                'end'   => $next['ts'] ?? $endAnchor,
            ];
        }

        return $phases;
    }

    /**
     * Last-resort heuristic when neither per-sample status nor LOG markers
     * are available. Cruise threshold: |vs| < 200 fpm.
     *
     * @return array<int, array{code: string, label: string, start: int, end: int}>
     */
    private function detectPhasesFromVs(Collection $samples): array
    {
        return $this->collapseToPhases(
            $samples,
            fn ($s): string => match (true) {
                (float) ($s->vs ?? 0) > 200  => PirepStatus::INIT_CLIM->value,
                (float) ($s->vs ?? 0) < -200 => PirepStatus::APPROACH_ICAO->value,
                default                      => PirepStatus::ENROUTE->value,
            },
        );
    }

    /**
     * Walk the sample collection, group contiguous runs that share the same
     * phase code (resolved by `$codeFor`), and emit one entry per run with
     * its translated PirepStatus label.
     *
     * @param  callable(Acars): string                                              $codeFor
     * @return array<int, array{code: string, label: string, start: int, end: int}>
     */
    private function collapseToPhases(Collection $samples, callable $codeFor): array
    {
        $phases = [];
        $first = $samples->first();
        $currentCode = $codeFor($first);
        $currentStart = $this->ts($first);

        foreach ($samples as $s) {
            $code = $codeFor($s);

            if ($code !== $currentCode) {
                $phases[] = [
                    'code'  => $currentCode,
                    'label' => $this->phaseLabel($currentCode),
                    'start' => $currentStart,
                    'end'   => $this->ts($s),
                ];
                $currentCode = $code;
                $currentStart = $this->ts($s);
            }
        }

        $phases[] = [
            'code'  => $currentCode,
            'label' => $this->phaseLabel($currentCode),
            'start' => $currentStart,
            'end'   => $this->ts($samples->last()),
        ];

        return $phases;
    }

    /**
     * Label = the PirepStatus 3-letter code itself (e.g. 'TXI', 'ENR').
     * Chart corner real estate is cramped and the codes are unambiguous
     * to anyone reading flight data. Unknown codes pass through as-is.
     */
    private function phaseLabel(string $code): string
    {
        return $code;
    }

    /** @return array<string, mixed> */
    private function buildMeta(Collection $samples): array
    {
        $start = $this->ts($samples->first());
        $end = $this->ts($samples->last());

        return [
            'duration_seconds' => $end - $start,
            'first_sample_ts'  => $start,
            'last_sample_ts'   => $end,
        ];
    }

    /**
     * Unix timestamp from an Acars row's created_at, in seconds. Returns 0
     * when created_at is null (Acars::$created_at is documented Carbon|null
     * and partial imports can leave it unset). The explicit isset() guard
     * sidesteps a larastan false positive — its model resolver narrows the
     * type to non-null Carbon, so `?->` reads as nullsafe.neverNull at
     * level 5 even though the property is genuinely nullable at runtime.
     */
    private function ts(mixed $sample): int
    {
        $createdAt = $sample->created_at;

        return $createdAt instanceof \DateTimeInterface ? $createdAt->getTimestamp() : 0;
    }
}
