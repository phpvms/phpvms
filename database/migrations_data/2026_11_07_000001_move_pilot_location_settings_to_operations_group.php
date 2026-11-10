<?php

use App\Models\LegacySetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Moves `pilots.home_hubs_only`, `pilots.only_flights_from_current` and
 * `pilots.only_show_flights_from_current` into the `operations` group,
 * keeping the operator's chosen values.
 *
 * Handles both upgrade orders. If the settings seeder has not run yet the old
 * row is renamed in place. If it has, the new row already exists holding the
 * default, so the old row's value is copied onto it and the old row removed.
 *
 * Idempotent: with no old row there is nothing to do.
 */
return new class() extends Migration
{
    /** @var array<string, string> old key => new key */
    private const array MOVES = [
        'pilots.home_hubs_only'                 => 'operations.home_hubs_only',
        'pilots.only_flights_from_current'      => 'operations.only_flights_from_current',
        'pilots.only_show_flights_from_current' => 'operations.only_show_flights_from_current',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('settings_backup')) {
            return;
        }

        foreach (self::MOVES as $old => $new) {
            $oldId = LegacySetting::formatKey($old);
            $newId = LegacySetting::formatKey($new);

            // Read, write and delete together, so a stop part-way never leaves
            // a half-moved setting for a retry to misread.
            DB::transaction(function () use ($oldId, $newId, $new): bool {
                $oldRow = DB::table('settings_backup')->where('id', $oldId)->first();
                if ($oldRow === null) {
                    return false;
                }

                if (DB::table('settings_backup')->where('id', $newId)->exists()) {
                    DB::table('settings_backup')->where('id', $newId)->update(['value' => $oldRow->value]);
                    DB::table('settings_backup')->where('id', $oldId)->delete();
                } else {
                    DB::table('settings_backup')->where('id', $oldId)->update([
                        'id'    => $newId,
                        'key'   => $new,
                        'group' => 'operations',
                    ]);
                }

                return true;
            });
        }
    }
};
