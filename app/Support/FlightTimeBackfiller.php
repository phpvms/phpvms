<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Backfill the structured TIME columns on the flights table from the legacy
 * free-form `dpt_time` / `arr_time` strings using FlightTimeParser.
 *
 * Used inline by the 2026_05_19 add_time_columns migration so admins don't
 * need to invoke a separate artisan command after migrate. Extracted to a
 * dedicated class for direct testability — see tests/Feature/Support/FlightTimeBackfillerTest.php.
 */
class FlightTimeBackfiller
{
    /**
     * Run the backfill in chunks of 1000.
     *
     * Counter semantics:
     *   - `parsed`   counts individual time *fields* successfully parsed and written.
     *   - `failures` counts individual time *fields* that failed to parse.
     * A single row can contribute up to two of either (one each for dpt + arr).
     *
     * @return array{parsed: int, failures: int}
     */
    public static function run(): array
    {
        $parsed = 0;
        $failures = 0;

        DB::table('flights')
            ->select(['id', 'dpt_time', 'arr_time', 'departure_time', 'arrival_time'])
            ->orderBy('id')
            ->chunk(1000, function ($rows) use (&$parsed, &$failures): void {
                foreach ($rows as $row) {
                    $updates = [];

                    if (!empty($row->dpt_time) && $row->departure_time === null) {
                        $result = FlightTimeParser::parse($row->dpt_time);
                        if ($result !== null) {
                            $updates['departure_time'] = $result;
                            $parsed++;
                        } else {
                            $failures++;
                            Log::warning('flights:time-backfill unparseable dpt_time', [
                                'flight_id' => $row->id,
                                'value'     => $row->dpt_time,
                            ]);
                        }
                    }

                    if (!empty($row->arr_time) && $row->arrival_time === null) {
                        $result = FlightTimeParser::parse($row->arr_time);
                        if ($result !== null) {
                            $updates['arrival_time'] = $result;
                            $parsed++;
                        } else {
                            $failures++;
                            Log::warning('flights:time-backfill unparseable arr_time', [
                                'flight_id' => $row->id,
                                'value'     => $row->arr_time,
                            ]);
                        }
                    }

                    if ($updates !== []) {
                        DB::table('flights')->where('id', $row->id)->update($updates);
                    }
                }
            });

        if ($parsed > 0 || $failures > 0) {
            Log::info(sprintf(
                'flights:time-backfill complete (parsed=%d, failures=%d)',
                $parsed,
                $failures,
            ));
        }

        return ['parsed' => $parsed, 'failures' => $failures];
    }
}
