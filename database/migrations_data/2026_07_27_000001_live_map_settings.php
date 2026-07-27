<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * `acars.live_time` decided two unrelated things. It becomes
     * `pireps.tombstone_time` - the reaper's half - keeping hours and 12 so stored
     * values aren't reinterpreted. `livemap.live_time` and `livemap.idle_time` take
     * the map's half and are new, so the seeder owns them. The rest is a regroup.
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
     * New here, so reversing removes them rather than renaming them back.
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

        // No pre-change counterpart to restore to.
        foreach ($this->introduced as $key) {
            Setting::where('id', Setting::formatKey($key))->delete();
        }
    }

    /**
     * Renamed in place, not recreated: `value` is not among the columns written, so
     * it survives by construction. The discord migration's copy-when-blank rule would
     * never fire here, since every setting moved seeds to a real default.
     *
     * The seeder runs first, so the destination usually exists. Its placement is
     * adopted and the row dropped, leaving upgrades and fresh installs identical
     * except for the operator's value. Idempotent: no source, nothing to do.
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

        // Query builder because this rewrites the primary key, and the explicit
        // column list is what documents that `value` is not among them.
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
