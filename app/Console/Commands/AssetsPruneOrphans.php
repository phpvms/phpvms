<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Model;
use App\Models\Asset;
use App\Models\Award;
use App\Models\FlightBundle;
use App\Models\Rank;
use App\Traits\HasAssets;
use Closure;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use LogicException;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Deletes assets whose owning record is gone -- rows left behind by a delete
 * that predates {@see HasAssets}'s `bootHasAssets()` cleanup hook.
 *
 * Only a slot owned by a `HasAssets` model can be swept this way, because only
 * those slots are guaranteed to key on that model's id. `branding`,
 * `airline-logo`, `user`, and anything a module registers are an open
 * vocabulary -- their keys are not necessarily record ids -- so they are
 * always reported as skipped rather than guessed at.
 */
#[AsCommand(name: 'assets:prune-orphans', description: 'Delete assets whose owning record no longer exists')]
#[Signature('assets:prune-orphans
                    {--force : Actually delete. Without this, the command only reports what it would do.}')]
class AssetsPruneOrphans extends Command
{
    private const int CHUNK_SIZE = 200;

    /**
     * The only models this command may reason about ownership for. Each
     * declares its own slot via `HasAssets::assetSlot()`, and each is soft
     * deleted -- see `ownerExists()` for why that matters here.
     *
     * @var array<class-string<Model>>
     */
    private const array OWNED_MODELS = [
        Award::class,
        Rank::class,
        FlightBundle::class,
    ];

    public function handle(): int
    {
        $dryRun = !$this->option('force');
        $ownedSlots = [];
        $totalSwept = 0;
        $totalReclaimed = 0;

        foreach (self::OWNED_MODELS as $modelClass) {
            $slot = new $modelClass()->assetSlot();
            $ownedSlots[] = $slot;

            [$swept, $reclaimed] = $this->sweepSlot($slot, $this->ownerExists($modelClass), $dryRun);
            $totalSwept += $swept;
            $totalReclaimed += $reclaimed;
        }

        $this->reportSkippedSlots($ownedSlots);

        $this->components->info(sprintf(
            '%s %d asset(s) across %d owned slot(s), reclaiming %s.',
            $dryRun ? 'Would delete' : 'Deleted',
            $totalSwept,
            count($ownedSlots),
            $this->formatBytes($totalReclaimed),
        ));

        if ($dryRun && $totalSwept > 0) {
            $this->components->info('Run with --force to delete.');
        }

        return self::SUCCESS;
    }

    /**
     * Sweep every asset in `$slot`, deleting (or, on a dry run, just
     * reporting) the ones `$ownerExists` says have no owner left.
     *
     * @param  Closure(string): bool $ownerExists
     * @return array{0: int, 1: int} [assets swept, bytes reclaimed]
     */
    private function sweepSlot(string $slot, Closure $ownerExists, bool $dryRun): array
    {
        $rows = [];
        $count = 0;
        $reclaimed = 0;

        Asset::query()->slot($slot)->orderBy('id')
            ->chunkById(self::CHUNK_SIZE, function (Collection $assets) use ($ownerExists, $dryRun, &$rows, &$count, &$reclaimed): void {
                foreach ($assets as $asset) {
                    if ($ownerExists($asset->key)) {
                        continue;
                    }

                    $rows[] = [$asset->key, $asset->path, $asset->size];
                    $count++;
                    $reclaimed += $asset->size;

                    if (!$dryRun) {
                        // Through the model, not the query builder, so Asset's
                        // own `deleted` hook removes the file too.
                        $asset->delete();
                    }
                }
            });

        if ($rows !== []) {
            $this->components->twoColumnDetail(sprintf('<fg=yellow>%s</> slot', $slot), sprintf('%d orphan(s)', $count));
            $this->table(['Key', 'Path', 'Size'], array_map(
                fn (array $row): array => [$row[0], $row[1], $this->formatBytes($row[2])],
                $rows,
            ));
        }

        return [$count, $reclaimed];
    }

    /**
     * Whether `$modelClass` still has a row for a given asset key -- trashed
     * or not. `withTrashed()` is the load-bearing check: a soft-deleted
     * award, rank or bundle is still restorable and must keep its badge,
     * exactly like the `isForceDeleting()` guard in
     * `HasAssets::bootHasAssets()`. Only a key with no row at all is an
     * orphan.
     *
     * A `match` over the three concrete classes rather than a dynamic
     * `$modelClass::withTrashed()` call: `withTrashed()` is a Builder macro
     * SoftDeletingScope registers at runtime, not a real method, and static
     * analysis can only follow it through a class PHPStan already knows uses
     * SoftDeletes -- not through `class-string<Model>`.
     *
     * @param  class-string<Model>   $modelClass
     * @return Closure(string): bool
     */
    private function ownerExists(string $modelClass): Closure
    {
        return match ($modelClass) {
            Award::class        => fn (string $key): bool => Award::withTrashed()->whereKey($key)->exists(),
            Rank::class         => fn (string $key): bool => Rank::withTrashed()->whereKey($key)->exists(),
            FlightBundle::class => fn (string $key): bool => FlightBundle::withTrashed()->whereKey($key)->exists(),
            default             => throw new LogicException("No ownerExists() branch for [{$modelClass}] -- add one alongside its OWNED_MODELS entry."),
        };
    }

    /**
     * @param array<int, string> $ownedSlots
     */
    private function reportSkippedSlots(array $ownedSlots): void
    {
        $skipped = Asset::query()
            ->whereNotIn('slot', $ownedSlots)
            ->distinct()
            ->pluck('slot');

        if ($skipped->isEmpty()) {
            return;
        }

        $this->components->warn(sprintf(
            'Skipped slot(s) with no known owner, left untouched: %s',
            $skipped->implode(', '),
        ));
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $value = (float) $bytes;

        foreach ($units as $unit) {
            if ($value < 1024 || $unit === end($units)) {
                return ($unit === 'B' ? (string) $bytes : number_format($value, 1)).' '.$unit;
            }

            $value /= 1024;
        }

        return $bytes.' B';
    }
}
