<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Split `acars.live_time`'s two jobs apart and move the live map's display
     * settings out of the ACARS group.
     *
     * `acars.live_time` governed two unrelated behaviours: how long a finished
     * flight stayed drawn on the map, and how long a silent in-progress PIREP
     * survived before the reaper cancelled it. It becomes `pireps.tombstone_time`
     * — only the second of those — and keeps hours as its unit and 12 as its
     * default, because converting the unit would silently reinterpret every
     * value an operator has already stored.
     *
     * `livemap.live_time` and `livemap.idle_time` take over the first job. They
     * are new settings expressed in minutes, so they are left entirely to
     * SettingsSeeder: there is no old value to carry onto them.
     *
     * The remaining three keys are a straight regroup. They were only ever under
     * `acars` because the live map arrived as an ACARS feature.
     *
     * @var list<array{old: string, new: string, name: string, group: string, type: string, default: string, description: string}>
     */
    private array $renames = [
        [
            'old'         => 'acars.live_time',
            'new'         => 'pireps.tombstone_time',
            'name'        => 'Tombstone Time',
            'group'       => 'pireps',
            'type'        => 'int',
            'default'     => '12',
            'description' => 'How long an in-progress PIREP that has stopped reporting survives before it is cancelled, in hours. Set to 0 to never cancel a PIREP on account of age',
        ],
        [
            'old'         => 'acars.center_coords',
            'new'         => 'livemap.center_coords',
            'name'        => 'Center Coords',
            'group'       => 'livemap',
            'type'        => 'text',
            'default'     => '30.1945,-97.6699',
            'description' => 'Where to center the map; enter as LAT,LON',
        ],
        [
            'old'         => 'acars.default_zoom',
            'new'         => 'livemap.default_zoom',
            'name'        => 'Default Zoom',
            'group'       => 'livemap',
            'type'        => 'int',
            'default'     => '5',
            'description' => 'Initial zoom level on the map',
        ],
        [
            'old'         => 'acars.update_interval',
            'new'         => 'livemap.update_interval',
            'name'        => 'Refresh Interval',
            'group'       => 'livemap',
            'type'        => 'int',
            'default'     => '60',
            'description' => 'How often the live map updates its data',
        ],
    ];

    /**
     * The settings that did not exist before this change, and so have to be
     * removed rather than renamed back when it is reversed.
     *
     * @var list<string>
     */
    private array $introduced = [
        'livemap.live_time',
        'livemap.idle_time',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('settings')) {
            return;
        }

        foreach ($this->renames as $rename) {
            $this->move(
                from: $rename['old'],
                to: $rename['new'],
                name: $rename['name'],
                group: $rename['group'],
                type: $rename['type'],
                default: $rename['default'],
                description: $rename['description'],
            );
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('settings')) {
            return;
        }

        foreach ($this->renames as $rename) {
            $this->move(
                from: $rename['new'],
                to: $rename['old'],
                name: $rename['old'] === 'acars.live_time' ? 'Live Time' : $rename['name'],
                group: 'acars',
                type: $rename['type'],
                default: $rename['default'],
                description: $rename['old'] === 'acars.live_time'
                    ? 'Age of flights to show on the map in hours. Set to 0 to show only all in-progress flights'
                    : $rename['description'],
            );
        }

        // These have no pre-change counterpart to be restored to, so reversing
        // means removing them rather than moving them anywhere.
        foreach ($this->introduced as $key) {
            Setting::where('id', Setting::formatKey($key))->delete();
        }
    }

    /**
     * Rename a setting onto a new key, in place.
     *
     * The row is renamed rather than recreated, which is what keeps an
     * operator's configured value: `value` is simply not among the columns
     * written, so it survives by construction rather than by a rule about when
     * to copy it. That distinction matters here in a way it did not for
     * `2026_07_14_000001_discord_route_settings`, whose settings all seed to an
     * empty string — every setting moved here seeds to a real default, so a
     * copy-only-when-the-destination-is-blank rule would never fire and every
     * customised live time would quietly reset to 12 hours.
     *
     * The destination usually exists already. Updater runs
     * SeederService::syncAllSeeds() before the data migrations, so SettingsSeeder
     * has just inserted the new key at its default. That freshly seeded row is
     * dropped and its placement adopted, so an upgraded install ends up with the
     * same group, order and default as a fresh one, and differs only in the
     * value the operator chose.
     *
     * Idempotent: with the source already gone there is nothing to carry, and
     * the seeded destination is left exactly as it is.
     */
    private function move(
        string $from,
        string $to,
        string $name,
        string $group,
        string $type,
        string $default,
        string $description,
    ): void {
        $source = Setting::where('id', Setting::formatKey($from))->first();

        if (!$source instanceof Setting) {
            return;
        }

        $target = Setting::where('id', Setting::formatKey($to))->first();

        $offset = $target->offset ?? $source->offset;
        $order = $target->order ?? $source->order;

        $target?->delete();

        // Straight to the query builder: this rewrites the primary key, and
        // listing the columns explicitly is what documents that `value` is not
        // among them.
        DB::table('settings')
            ->where('id', Setting::formatKey($from))
            ->update([
                'id'          => Setting::formatKey($to),
                'key'         => $to,
                'name'        => $name,
                'group'       => $group,
                'type'        => $type,
                'options'     => '',
                'default'     => $default,
                'description' => $description,
                'offset'      => $offset,
                'order'       => $order,
            ]);
    }
};
