<?php

namespace App\Console\Commands;

use App\Enums\AcarsType;
use App\Models\Acars;
use App\Models\PirepEvent;
use App\Services\Pirep\EventClassifier;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'phpvms:backfill-pirep-events', description: 'Backfill acars LOG rows into the pirep_events table')]
#[Signature('phpvms:backfill-pirep-events
                            {--delete : Purge the migrated acars LOG rows after a successful backfill}')]
class BackfillPirepEvents extends Command
{
    private const int CHUNK_SIZE = 500;

    private int $migrated = 0;

    private int $missingTelemetry = 0;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        Acars::query()
            ->ofType(AcarsType::LOG)
            ->chunkById(self::CHUNK_SIZE, function (Collection $rows): void {
                $this->migrateChunk($rows);
            });

        $this->components->info(sprintf(
            'Migrated %d row(s); %d referenced a missing telemetry row.',
            $this->migrated,
            $this->missingTelemetry
        ));

        $purged = $this->purgeIfRequested();

        if ($purged === null) {
            $this->components->warn('Purge skipped.');
        } else {
            $this->components->info(sprintf('Purged %d acars LOG row(s).', $purged));
        }

        return self::SUCCESS;
    }

    /**
     * @param Collection<int, Acars> $rows
     */
    private function migrateChunk(Collection $rows): void
    {
        $telemetryIds = $rows
            ->pluck('log')
            ->map(fn (?string $log): ?array => $this->decodeBlob($log))
            ->filter()
            ->pluck('telemetry_id')
            ->filter()
            ->unique()
            ->values();

        $existingTelemetryIds = $telemetryIds->isEmpty()
            ? collect()
            : Acars::query()->whereIn('id', $telemetryIds)->pluck('id');

        $events = $rows->map(fn (Acars $acars): array => $this->toEventRow($acars, $existingTelemetryIds));

        PirepEvent::query()->upsert(
            $events->all(),
            ['id'],
            ['pirep_id', 'acars_id', 'type', 'category', 'phase', 'log', 'details', 'sim_time', 'created_at']
        );

        $this->migrated += $events->count();
    }

    /**
     * @param  Collection<int, string> $existingTelemetryIds
     * @return array<string, mixed>
     */
    private function toEventRow(Acars $acars, Collection $existingTelemetryIds): array
    {
        $blob = $this->decodeBlob($acars->log);

        if ($blob !== null) {
            $telemetryId = $blob['telemetry_id'] ?? null;
            $acarsId = null;

            if ($telemetryId !== null) {
                if ($existingTelemetryIds->contains($telemetryId)) {
                    $acarsId = $telemetryId;
                } else {
                    $this->missingTelemetry++;
                }
            }

            $category = $blob['category'] ?? EventClassifier::classify($blob['log'] ?? null)['category'];
            $details = $blob['payload'] ?? null;

            return [
                'id'              => $acars->id,
                'pirep_id'        => $acars->pirep_id,
                'acars_id'        => $acarsId,
                'client_event_id' => null,
                'type'            => $blob['type'] ?? null,
                'category'        => $category,
                'phase'           => $blob['phase'] ?? null,
                'log'             => $blob['log'] ?? null,
                'details'         => $details === null ? null : json_encode($details),
                'sim_time'        => $acars->sim_time,
                'created_at'      => $acars->created_at,
            ];
        }

        $classified = EventClassifier::classify($acars->log);

        return [
            'id'              => $acars->id,
            'pirep_id'        => $acars->pirep_id,
            'acars_id'        => null,
            'client_event_id' => null,
            'type'            => $classified['type'],
            'category'        => $classified['category'],
            'phase'           => $classified['phase'],
            'log'             => $acars->log,
            'details'         => $classified['details'] === null ? null : json_encode($classified['details']),
            'sim_time'        => $acars->sim_time,
            'created_at'      => $acars->created_at,
        ];
    }

    /**
     * Decodes `log` as a TelemetryWriter JSON blob. Returns null for plain
     * strings, scalars, JSON arrays, or invalid JSON.
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

    /**
     * Purges migrated acars LOG rows when `--delete` is passed. Returns the
     * number of rows purged, or null if the purge was skipped (declined or
     * not requested).
     */
    private function purgeIfRequested(): ?int
    {
        if (!$this->option('delete')) {
            return null;
        }

        if ($this->input->isInteractive() && !$this->confirm(
            'This will permanently delete the migrated acars LOG rows. This cannot be undone. Continue?'
        )) {
            return null;
        }

        return Acars::query()->ofType(AcarsType::LOG)->delete();
    }
}
