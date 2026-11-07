<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Moves the aircraft and fuel operation settings out of `pireps`, `bids`,
 * `simbrief` and `flights` into the `operations` group, keeping the
 * operator's chosen values.
 *
 * `bids.block_aircraft` and `simbrief.block_aircraft` merge into the single
 * `operations.block_aircraft`. The merged value is on when either old lock was
 * on; a missing old row counts as its old default (bids on, simbrief off).
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
        'pireps.restrict_aircraft_to_rank'       => 'operations.restrict_aircraft_to_rank',
        'pireps.restrict_aircraft_to_typerating' => 'operations.restrict_aircraft_to_typerating',
        'pireps.only_aircraft_at_dpt_airport'    => 'operations.only_aircraft_at_dpt_airport',
        'pireps.advanced_fuel'                   => 'operations.advanced_fuel',
        'flights.only_company_aircraft'          => 'operations.only_company_aircraft',
    ];

    /** @var array<string, bool> old lock key => its old default */
    private const array LOCKS = [
        'bids.block_aircraft'     => true,
        'simbrief.block_aircraft' => false,
    ];

    private const string LOCK_KEY = 'operations.block_aircraft';

    public function up(): void
    {
        if (!Schema::hasTable('settings')) {
            return;
        }

        foreach (self::MOVES as $old => $new) {
            $oldId = $this->id($old);

            // Read, write and delete together, so a stop part-way never leaves
            // a half-moved setting for a retry to misread.
            $moved = DB::transaction(function () use ($oldId, $new): bool {
                $oldRow = DB::table('settings')->where('id', $oldId)->first();
                if ($oldRow === null) {
                    return false;
                }

                $this->write($oldRow->value, $oldId, $new);

                return true;
            });

            if ($moved) {
                $this->forget($old, $new);
            }
        }

        $this->mergeLocks();
    }

    private function mergeLocks(): void
    {
        // One transaction: if it stops after the bids delete, a retry would read
        // the missing bids row as its default (on) and overwrite a correct off.
        $merged = DB::transaction(function (): bool {
            $rows = [];
            foreach (self::LOCKS as $old => $default) {
                $rows[$old] = DB::table('settings')->where('id', $this->id($old))->first();
            }

            // Neither old row left: already migrated (or a fresh install).
            if (array_filter($rows) === []) {
                return false;
            }

            $on = false;
            foreach (self::LOCKS as $old => $default) {
                $on = $on || ($rows[$old] instanceof stdClass ? $this->truthy($rows[$old]->value) : $default);
            }

            $first = true;
            foreach ($rows as $old => $row) {
                if ($row === null) {
                    continue;
                }

                if ($first) {
                    $this->write($on ? '1' : '0', $this->id($old), self::LOCK_KEY);
                    $first = false;
                } else {
                    DB::table('settings')->where('id', $this->id($old))->delete();
                }
            }

            return true;
        });

        // After commit, so the cache is never cleared for a rolled-back write.
        if ($merged) {
            $this->forget(...array_keys(self::LOCKS), ...[self::LOCK_KEY]);
        }
    }

    /** Put $value on the new key's row: reuse a seeded row, else rename the old one. */
    private function write(mixed $value, string $oldId, string $new): void
    {
        $newId = $this->id($new);

        if (DB::table('settings')->where('id', $newId)->exists()) {
            DB::table('settings')->where('id', $newId)->update(['value' => $value]);
            DB::table('settings')->where('id', $oldId)->delete();

            return;
        }

        DB::table('settings')->where('id', $oldId)->update([
            'id'    => $newId,
            'key'   => $new,
            'group' => 'operations',
            'value' => $value,
        ]);
    }

    private function truthy(mixed $value): bool
    {
        return in_array(strtolower((string) $value), ['1', 'true', 'on', 'yes'], true);
    }

    private function id(string $key): string
    {
        return str_replace('.', '_', $key);
    }

    private function forget(string ...$keys): void
    {
        $cache = config('cache.keys.SETTINGS');
        if (!is_array($cache) || !isset($cache['key'])) {
            return;
        }

        foreach ($keys as $key) {
            Cache::forget($cache['key'].$key);
        }
    }
};
