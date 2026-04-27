<?php

declare(strict_types=1);

use App\Http\Resources\Setting as SettingResource;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

test('SettingResource exposes the documented JSON shape', function () {
    // `id` is not fillable on Setting, so `Setting::create(['id' => ...])`
    // drops it. Production seeds via raw insert (see SeederService::addSetting).
    DB::table('settings')->insert([
        'id'          => Setting::formatKey('general.name'),
        'name'        => 'Name',
        'key'         => 'general.name',
        'value'       => 'phpvms',
        'type'        => 'string',
        'group'       => 'general',
        'order'       => 1,
        'description' => '',
    ]);

    $model = Setting::find(Setting::formatKey('general.name'));

    $payload = (new SettingResource($model))->toArray(request());

    expect(array_keys($payload))->toEqualCanonicalizing([
        'id', 'type', 'name', 'value', 'group', 'order', 'description',
    ]);
});
