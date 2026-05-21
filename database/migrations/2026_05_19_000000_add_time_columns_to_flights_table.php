<?php

use App\Support\FlightTimeParser;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Adds the structured TIME columns and backfills them from the legacy
     * free-form `dpt_time` / `arr_time` strings using FlightTimeParser.
     *
     * Backfill runs inline (chunked) so admins don't need to invoke a separate
     * artisan command after migrate. Parse failures are logged via the standard
     * Laravel log facade and leave the new column NULL.
     */
    public function up(): void
    {
        Schema::table('flights', function (Blueprint $table): void {
            $table->time('departure_time')->nullable()->after('dpt_time');
            $table->time('arrival_time')->nullable()->after('arr_time');
        });

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
                        $parsed++;
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
    }

    public function down(): void
    {
        Schema::table('flights', function (Blueprint $table): void {
            $table->dropColumn('departure_time');
            $table->dropColumn('arrival_time');
        });
    }
};
