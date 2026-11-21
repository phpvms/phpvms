<?php

declare(strict_types=1);

namespace App\Features\Setup\PostUpgrade;

use App\Features\Setup\Contracts\PostUpgradeTask;
use App\Features\Setup\Contracts\SkipsFailingItems;
use App\Features\Setup\Support\PostUpgradeTaskState;
use App\Support\Dto\SimBriefOfp\SimBriefOfp;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use stdClass;
use Throwable;

/**
 * Writes each 7.x simbrief.ofp_xml out as the JSON file 8.x reads, and links it through ofp_json_path.
 * ofp_xml is never cleared, so an OFP that does not convert is still there to retry.
 *
 * @phpstan-type Data array{cursor: ?string, converted: int, total: ?int, skipped: list<string>, skipped_count: int, complete: bool}
 */
final readonly class ConvertSimBriefOfps implements PostUpgradeTask, SkipsFailingItems
{
    public const string KEY = 'simbrief_ofps';

    // An OFP's XML can run to several hundred KB.
    public const int CHUNK = 50;

    private const int SKIPPED_LIMIT = 100;

    public function __construct(
        private PostUpgradeTaskState $state,
        private SimBriefXmlToJson $converter,
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
            return $this->hasColumns() && $this->unconverted()->exists();
        }

        return !($data['complete'] ?? false);
    }

    public function progress(): array
    {
        $data = $this->load();

        return ['done' => $data['converted'] + $data['skipped_count'], 'total' => $data['total'] ?? 0];
    }

    /**
     * @return Data
     */
    public function load(): array
    {
        /** @var Data $data */
        $data = array_merge([
            'cursor'        => null,
            'converted'     => 0,
            'total'         => null,
            'skipped'       => [],
            'skipped_count' => 0,
            'complete'      => false,
        ], $this->state->data(self::KEY));

        return $data;
    }

    public function run(?Closure $progress = null): void
    {
        $data = $this->load();

        if ($data['complete']) {
            return;
        }

        if (!$this->hasColumns()) {
            $data['total'] ??= 0;
            $data['complete'] = true;
            $this->state->putData(self::KEY, $data);

            return;
        }

        if ($data['total'] === null) {
            $data['total'] = $this->unconverted()->count();
            $this->state->putData(self::KEY, $data);
        }

        while (true) {
            $rows = $this->nextChunk($data['cursor']);

            if ($rows->isEmpty()) {
                $data['complete'] = true;
                $this->state->putData(self::KEY, $data);

                return;
            }

            // The cursor only moves with the rows it covers.
            $data = DB::transaction(function () use ($rows, $data): array {
                foreach ($rows as $row) {
                    $this->convertRow($row, $data);
                }

                $data['cursor'] = (string) $rows->last()->id;
                $this->state->putData(self::KEY, $data);

                return $data;
            });

            if ($progress instanceof Closure) {
                $progress($data['converted'] + $data['skipped_count'], $data['total']);
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

        $failed = 0;

        foreach ($rows as $row) {
            try {
                $this->convertRow($row, $data);
            } catch (Throwable $throwable) {
                Log::error('SimBrief OFP conversion skipped simbrief row '.$row->id.': '.$throwable->getMessage(), ['exception' => $throwable]);
                $this->skip($data, (string) $row->id);
                $failed++;
            }
        }

        if ($failed === $rows->count()) {
            throw new RuntimeException('Every row from simbrief id '.$rows->first()->id.' on failed to convert.');
        }

        $data['cursor'] = (string) $rows->last()->id;
        $this->state->putData(self::KEY, $data);
    }

    /**
     * An OFP that will not convert is skipped on the spot: the same XML fails every retry.
     * Storage and database errors still throw, for the runner's retry and skip.
     *
     * @param Data $data
     */
    private function convertRow(stdClass $row, array &$data): void
    {
        try {
            $ofp = new SimBriefOfpDefaults()->fill($this->converter->convert((string) $row->ofp_xml));
            // 8.x reads the file through this DTO; a file it cannot parse would break the pages that show it.
            SimBriefOfp::from($ofp);
            $json = json_encode($ofp, JSON_THROW_ON_ERROR);
        } catch (Throwable $throwable) {
            Log::warning('SimBrief OFP '.$row->id.' could not be converted and was skipped: '.$throwable->getMessage());
            $this->skip($data, (string) $row->id);

            return;
        }

        $path = 'simbrief/'.$row->id.'.json';

        if (!Storage::put($path, $json)) {
            throw new RuntimeException('Could not write '.$path);
        }

        DB::table('simbrief')->where('id', $row->id)->update(['ofp_json_path' => $path]);
        $data['converted']++;
    }

    /**
     * @param Data $data
     */
    private function skip(array &$data, string $id): void
    {
        if (count($data['skipped']) < self::SKIPPED_LIMIT) {
            $data['skipped'][] = $id;
        }

        $data['skipped_count']++;
    }

    private function hasColumns(): bool
    {
        return Schema::hasColumns('simbrief', ['ofp_xml', 'ofp_json_path']);
    }

    private function unconverted(): Builder
    {
        return DB::table('simbrief')
            ->whereNull('ofp_json_path')
            ->whereNotNull('ofp_xml')
            ->where('ofp_xml', '!=', '');
    }

    /**
     * Keyset on the primary key, resumable from the saved cursor.
     *
     * @return Collection<int, stdClass>
     */
    private function nextChunk(?string $cursor): Collection
    {
        return $this->unconverted()
            ->select(['id', 'ofp_xml'])
            ->when($cursor !== null, fn (Builder $query) => $query->where('id', '>', $cursor))
            ->orderBy('id')
            ->limit($this->chunkSize)
            ->get();
    }
}
