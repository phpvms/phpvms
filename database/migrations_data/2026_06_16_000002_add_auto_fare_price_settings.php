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

        foreach ($this->settings as $setting) {
            $id = Setting::formatKey($setting['key']);

            if (Setting::where('id', $id)->exists()) {
                continue;
            }

            // id is not auto-derived from the key on create (the accessor is
            // read-only and the model does not auto-increment), so set it
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
