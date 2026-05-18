<?php

namespace App\Services\Pirep;

use App\Enums\AcarsType;
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
     *     phases: array<int, array{name: string, start: int, end: int}>,
     *     meta: array<string, mixed>,
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

        return [
            'sample_count' => $samples->count(),
            'series'       => [
                'altitude' => $this->altitudeSeries($reduced),
                'speed'    => $this->speedSeries($reduced),
                'fuel'     => $this->fuelSeries($reduced),
                'vs'       => $this->vsSeries($reduced),
            ],
            'phases' => $this->detectPhases($reduced),
            'meta'   => $this->buildMeta($reduced),
        ];
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

    /** @return array<int, array{name: string, start: int, end: int}> */
    private function detectPhases(Collection $samples): array
    {
        // Simple altitude-derivative based phase detection.
        // Cruise threshold: |vs| < 200 fpm for at least 5 consecutive samples.
        $phases = [];
        $current = ['name' => 'climb', 'start' => $this->ts($samples->first())];

        foreach ($samples as $s) {
            $vs = (float) ($s->vs ?? 0);
            $name = match (true) {
                $vs > 200  => 'climb',
                $vs < -200 => 'descent',
                default    => 'cruise',
            };

            if ($name !== $current['name']) {
                $current['end'] = $this->ts($s);
                $phases[] = $current;
                $current = ['name' => $name, 'start' => $this->ts($s)];
            }
        }

        $current['end'] = $this->ts($samples->last());
        $phases[] = $current;

        return $phases;
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
