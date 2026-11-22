<?php

declare(strict_types=1);

namespace App\Features\Setup\PostUpgrade;

use App\Enums\AcarsType;
use App\Features\Setup\Contracts\PostUpgradeTask;
use App\Features\Setup\Contracts\SkipsFailingItems;
use App\Features\Setup\Support\PostUpgradeTaskState;
use App\Models\PirepEvent;
use App\Services\Pirep\EventClassifier;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use stdClass;
use Throwable;

/**
 * Copies 7.x acars LOG rows into pirep_events, keyed on the acars id so a rerun overwrites.
 * Source rows are never deleted here; `phpvms:backfill-pirep-events --delete` does that by hand.
 *
 * @phpstan-type Data array{cursor: ?string, copied: int, total: ?int, missing_telemetry: int, skipped: list<string>, skipped_count: int, complete: bool}
 */
final readonly class BackfillPirepEvents implements PostUpgradeTask, SkipsFailingItems
{
    public const string KEY = 'pirep_events_backfill';

    public const int CHUNK = 2000;

    // 14 columns a row: keeps one statement well under every driver's placeholder limit.
    private const int UPSERT_BATCH = 500;

    private const int SKIPPED_LIMIT = 100;

    private const array COLUMNS = ['id', 'pirep_id', 'log', 'lat', 'lon', 'sim_time', 'created_at'];

    private const array UPDATE_COLUMNS = ['pirep_id', 'acars_id', 'type', 'category', 'phase', 'lat', 'lon', 'altitude_msl', 'log', 'details', 'sim_time', 'created_at'];

    public function __construct(
        private PostUpgradeTaskState $state,
        private int $chunkSize = self::CHUNK,
    ) {}

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return __('installer.post_upgrade.tasks.'.self::KEY);
    }

    public function pending(): bool
    {
        $data = $this->state->data(self::KEY);

        if ($data === []) {
            return DB::table('acars')->where('type', AcarsType::LOG)->exists();
        }

        return !($data['complete'] ?? false);
    }

    public function progress(): array
    {
        $data = $this->load();

        return ['done' => $data['copied'] + $data['skipped_count'], 'total' => $data['total'] ?? 0];
    }

    /**
     * @return Data
     */
    public function load(): array
    {
        /** @var Data $data */
        $data = array_merge([
            'cursor'            => null,
            'copied'            => 0,
            'total'             => null,
            'missing_telemetry' => 0,
            'skipped'           => [],
            'skipped_count'     => 0,
            'complete'          => false,
        ], $this->state->data(self::KEY));

        return $data;
    }

    public function run(?Closure $progress = null): void
    {
        $data = $this->load();

        if ($data['complete']) {
            return;
        }

        if ($data['total'] === null) {
            $data['total'] = DB::table('acars')->where('type', AcarsType::LOG)->count();
            $this->state->putData(self::KEY, $data);
        }

        while (true) {
            $rows = $this->nextChunk($data['cursor']);

            if ($rows->isEmpty()) {
                $data['complete'] = true;
                $this->state->putData(self::KEY, $data);

                return;
            }

            // One commit per chunk, and the cursor only moves with the rows it covers.
            $data = DB::transaction(function () use ($rows, $data): array {
                $data['missing_telemetry'] += $this->copy($rows);
                $data['copied'] += $rows->count();
                $data['cursor'] = (string) $rows->last()->id;
                $this->state->putData(self::KEY, $data);

                return $data;
            });

            if ($progress instanceof Closure) {
                $progress($data['copied'] + $data['skipped_count'], $data['total']);
            }
        }
    }

    public function skipFailing(): void
    {
        $data = $this->load();
        $rows = $this->nextChunk($data['cursor']);

        if ($rows->isEmpty()) {
            return;
        }

        $skipped = [];

        foreach ($rows as $row) {
            try {
                $data['missing_telemetry'] += $this->copy(collect([$row]));
                $data['copied']++;
            } catch (Throwable $throwable) {
                Log::error('Flight log backfill skipped acars row '.$row->id.': '.$throwable->getMessage(), ['exception' => $throwable]);
                $skipped[] = (string) $row->id;
            }
        }

        if (count($skipped) === $rows->count()) {
            throw new RuntimeException('Every row from acars id '.$rows->first()->id.' on failed to copy.');
        }

        $data['skipped'] = array_slice([...$data['skipped'], ...$skipped], 0, self::SKIPPED_LIMIT);
        $data['skipped_count'] += count($skipped);
        $data['cursor'] = (string) $rows->last()->id;
        $this->state->putData(self::KEY, $data);
    }

    /**
     * Keyset on the primary key: no offset scan, and resumable from the saved cursor.
     *
     * @return Collection<int, stdClass>
     */
    private function nextChunk(?string $cursor): Collection
    {
        return DB::table('acars')
            ->select(self::COLUMNS)
            ->where('type', AcarsType::LOG)
            ->when($cursor !== null, fn ($query) => $query->where('id', '>', $cursor))
            ->orderBy('id')
            ->limit($this->chunkSize)
            ->get();
    }

    /**
     * Upsert one chunk; returns how many rows referenced a missing telemetry row.
     *
     * @param Collection<int, stdClass> $rows
     */
    private function copy(Collection $rows): int
    {
        $telemetryIds = $rows
            ->map(fn (stdClass $row): ?array => $this->decodeBlob($row->log))
            ->filter()
            ->pluck('telemetry_id')
            ->filter()
            ->unique()
            ->values();

        $existing = $telemetryIds->isEmpty()
            ? []
            : array_flip(DB::table('acars')->whereIn('id', $telemetryIds->all())->pluck('id')->map(fn ($id): string => (string) $id)->all());

        $missing = 0;
        $events = [];

        foreach ($rows as $row) {
            $events[] = $this->toEventRow($row, $existing, $missing);
        }

        foreach (array_chunk($events, self::UPSERT_BATCH) as $batch) {
            PirepEvent::query()->upsert($batch, ['id'], self::UPDATE_COLUMNS);
        }

        return $missing;
    }

    /**
     * @param  array<string, int>   $existing telemetry ids that exist, as keys
     * @return array<string, mixed>
     */
    private function toEventRow(stdClass $row, array $existing, int &$missing): array
    {
        $blob = $this->decodeBlob($row->log);
        $position = $this->position($row);

        if ($blob !== null) {
            $telemetryId = $blob['telemetry_id'] ?? null;
            $acarsId = null;

            if ($telemetryId !== null) {
                if (isset($existing[(string) $telemetryId])) {
                    $acarsId = $telemetryId;
                } else {
                    $missing++;
                }
            }

            $category = $blob['category'] ?? EventClassifier::classify($blob['log'] ?? null)['category'];
            $details = $blob['payload'] ?? null;

            return [
                'id'              => $row->id,
                'pirep_id'        => $row->pirep_id,
                'acars_id'        => $acarsId,
                'client_event_id' => null,
                'type'            => $blob['type'] ?? null,
                'category'        => $category,
                'phase'           => $blob['phase'] ?? null,
                'lat'             => $blob['lat'] ?? $position['lat'],
                'lon'             => $blob['lon'] ?? $position['lon'],
                'altitude_msl'    => $blob['altitude_msl_ft'] ?? null,
                'log'             => $blob['log'] ?? null,
                'details'         => $details === null ? null : json_encode($details),
                'sim_time'        => $row->sim_time,
                'created_at'      => $row->created_at,
            ];
        }

        $classified = EventClassifier::classify($row->log);

        return [
            'id'              => $row->id,
            'pirep_id'        => $row->pirep_id,
            'acars_id'        => null,
            'client_event_id' => null,
            'type'            => $classified['type'],
            'category'        => $classified['category'],
            'phase'           => $classified['phase'],
            'lat'             => $position['lat'],
            'lon'             => $position['lon'],
            'altitude_msl'    => null,
            'log'             => $row->log,
            'details'         => $classified['details'] === null ? null : json_encode($classified['details']),
            'sim_time'        => $row->sim_time,
            'created_at'      => $row->created_at,
        ];
    }

    /**
     * acars.lat/lon default to 0, so 0,0 means "never reported", not Null Island.
     *
     * @return array{lat: ?float, lon: ?float}
     */
    private function position(stdClass $row): array
    {
        $lat = (float) $row->lat;
        $lon = (float) $row->lon;

        if ($lat === 0.0 && $lon === 0.0) {
            return ['lat' => null, 'lon' => null];
        }

        return ['lat' => $lat, 'lon' => $lon];
    }

    /**
     * The TelemetryWriter JSON blob, or null for plain strings, scalars, lists and invalid JSON.
     *
     * @return array<string, mixed>|null
     */
    private function decodeBlob(?string $log): ?array
    {
        if ($log === null || $log === '') {
            return null;
        }

        $decoded = json_decode($log, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded) || array_is_list($decoded)) {
            return null;
        }

        return $decoded;
    }
}
