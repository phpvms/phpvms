<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Moves `general.auto_airport_lookup` and `general.allow_unadded_airports`
 * into the `airports` group, keeping the operator's chosen values.
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
        'general.auto_airport_lookup'    => 'airports.auto_airport_lookup',
        'general.allow_unadded_airports' => 'airports.allow_unadded_airports',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('settings_backup')) {
            return;
        }

        foreach (self::MOVES as $old => $new) {
            $oldId = str_replace('.', '_', $old);
            $newId = str_replace('.', '_', $new);

            $oldRow = DB::table('settings_backup')->where('id', $oldId)->first();
            if ($oldRow === null) {
                continue;
            }

            if (DB::table('settings_backup')->where('id', $newId)->exists()) {
                DB::table('settings_backup')->where('id', $newId)->update(['value' => $oldRow->value]);
                DB::table('settings_backup')->where('id', $oldId)->delete();
            } else {
                DB::table('settings_backup')->where('id', $oldId)->update([
                    'id'    => $newId,
                    'key'   => $new,
                    'group' => 'airports',
                ]);
            }
        }
    }
};
