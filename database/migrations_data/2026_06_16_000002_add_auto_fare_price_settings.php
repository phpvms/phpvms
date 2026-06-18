<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Seed the auto fare price settings for existing installs. Mirrors the
     * entries in SettingsSeeder; idempotent so re-running never clobbers a
     * value an admin has already changed.
     *
     * @var list<array{key: string, name: string, value: string, group: string, type: string, options: string, description: string}>
     */
    private array $settings = [
        [
            'key'         => 'fares.auto_price',
            'name'        => 'Automatic fare pricing',
            'group'       => 'fares',
            'value'       => 'false',
            'type'        => 'boolean',
            'options'     => 'true,false',
            'description' => 'Compute PIREP fare prices from distance, seat category and airline type instead of using the configured fare/subfleet/flight prices',
        ],
        [
            'key'         => 'fares.low_cost_multiplier',
            'name'        => 'Low-cost airline multiplier',
            'group'       => 'fares',
            'value'       => '0.8',
            'type'        => 'float',
            'options'     => '',
            'description' => 'Multiplier applied to the automatic fare price when the PIREP airline is flagged as low cost',
        ],
    ];

    public function up(): void
    {
        if (!Schema::hasTable('settings')) {
            return;
        }

        // Track a per-group position so offset/order match SettingsSeeder's
        // per-group incrementing (defaults of 0/99 would otherwise push these
        // to the end of the group rather than their seeded position).
        $groupPositions = [];

        foreach ($this->settings as $setting) {
            $group = $setting['group'];
            $position = $groupPositions[$group] ?? 0;
            $groupPositions[$group] = $position + 1;

            $id = Setting::formatKey($setting['key']);

            if (Setting::where('id', $id)->exists()) {
                continue;
            }

            // id, offset and order are not fillable / auto-derived, so set them
            // explicitly the same way SettingsSeeder does.
            $model = new Setting([
                'key'         => $setting['key'],
                'name'        => $setting['name'],
                'value'       => $setting['value'],
                'group'       => $setting['group'],
                'type'        => $setting['type'],
                'options'     => $setting['options'],
                'description' => $setting['description'],
            ]);
            $model->id = $id;
            $model->default = $setting['value'];
            $model->offset = $position;
            $model->order = $position;
            $model->save();
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('settings')) {
            return;
        }

        foreach ($this->settings as $setting) {
            Setting::where('id', Setting::formatKey($setting['key']))->delete();
        }
    }
};
