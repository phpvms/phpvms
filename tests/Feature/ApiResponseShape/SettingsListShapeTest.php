<?php

declare(strict_types=1);

use App\Http\Resources\SettingResource;
use App\Support\Settings\SettingsRegistry;

test('SettingResource exposes the documented JSON shape', function (): void {
    setting_save('general.site_name', 'phpvms');

    $payload = new SettingResource(app(SettingsRegistry::class)->find('general.site_name'))->toArray(request());

    expect(array_keys($payload))->toEqualCanonicalizing([
        'id', 'type', 'name', 'value', 'group', 'order', 'description',
    ])->and($payload['id'])->toBe('general_site_name')
        ->and($payload['type'])->toBe('text')
        ->and($payload['name'])->toBe('Site Name')
        ->and($payload['value'])->toBe('phpvms')
        ->and($payload['group'])->toBe('general');
});

test('SettingResource lists a boolean and an option setting as the settings table stored them', function (): void {
    setting_save('general.telemetry', true);
    setting_save('units.weight', 'kg');

    $registry = app(SettingsRegistry::class);
    $telemetry = new SettingResource($registry->find('general.telemetry'))->toArray(request());
    $weight = new SettingResource($registry->find('units.weight'))->toArray(request());

    expect($telemetry['type'])->toBe('boolean')
        ->and($telemetry['value'])->toBe('1')
        ->and($weight['type'])->toBe('select')
        ->and($weight['value'])->toBe('kg');
});
