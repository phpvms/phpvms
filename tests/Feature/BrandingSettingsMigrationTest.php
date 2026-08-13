<?php

declare(strict_types=1);

use App\Models\Setting;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\DB;

function brandingSettingsMigration(): object
{
    return require base_path('database/migrations_data/2026_08_13_000000_branding_settings.php');
}

function brandingSettingRow(string $key): ?object
{
    return DB::table('settings')->where('id', Setting::formatKey($key))->first();
}

/** The seven keys this migration owns. */
function brandingKeys(): array
{
    return [
        'general.site_name',
        'branding.brand_color',
        'branding.logo_url',
        'branding.logo_32_url',
        'branding.logo_64_url',
        'branding.logo_180_url',
        'branding.banner_url',
    ];
}

it('seeds general.site_name below every existing general row', function (): void {
    brandingSettingsMigration()->up();

    $siteName = brandingSettingRow('general.site_name');
    $minOtherOrder = Setting::where('group', 'general')
        ->where('id', '!=', Setting::formatKey('general.site_name'))
        ->min('order');

    expect($siteName)->not->toBeNull()
        ->and($siteName->group)->toBe('general')
        ->and($siteName->type)->toBe('text')
        ->and($siteName->value)->toBe('')
        ->and((int) $siteName->order)->toBeLessThan($minOtherOrder)
        ->and(Setting::where('group', 'general')->min('order'))->toBe((int) $siteName->order);
});

it('seeds the six branding keys as hidden and empty', function (): void {
    brandingSettingsMigration()->up();

    foreach ([
        'branding.brand_color',
        'branding.logo_url',
        'branding.logo_32_url',
        'branding.logo_64_url',
        'branding.logo_180_url',
        'branding.banner_url',
    ] as $key) {
        $row = brandingSettingRow($key);

        expect($row)->not->toBeNull()
            ->and($row->group)->toBe('branding')
            ->and($row->type)->toBe('hidden')
            ->and($row->value)->toBe('');
    }
});

it('is idempotent: a re-run does not shift general order twice', function (): void {
    brandingSettingsMigration()->up();
    $firstRun = Setting::where('group', 'general')->orderBy('order')->pluck('order', 'key')->all();

    brandingSettingsMigration()->up();
    $secondRun = Setting::where('group', 'general')->orderBy('order')->pluck('order', 'key')->all();

    expect($secondRun)->toBe($firstRun);
});

it('does not touch an already-present row', function (): void {
    Setting::where('id', Setting::formatKey('general.site_name'))->delete();

    DB::table('settings')->insert([
        'id'          => Setting::formatKey('general.site_name'),
        'key'         => 'general.site_name',
        'name'        => 'Operator Set Name',
        'value'       => 'Acme Air',
        'default'     => '',
        'group'       => 'general',
        'order'       => 0,
        'type'        => 'text',
        'options'     => '',
        'description' => '',
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);

    brandingSettingsMigration()->up();

    expect(brandingSettingRow('general.site_name')->value)->toBe('Acme Air');
});

it('does not shift order again when the seeder already created general.site_name', function (): void {
    // TestCase's setUp already ran SettingsSeeder, which now seeds
    // general.site_name first (order 0) and shifts every other general row
    // naturally via array position. Snapshot before running the data
    // migration, since that is what the guard must leave untouched.
    $before = Setting::where('group', 'general')->orderBy('key')->pluck('order', 'key')->all();

    expect(brandingSettingRow('general.site_name'))->not->toBeNull();

    brandingSettingsMigration()->up();

    $after = Setting::where('group', 'general')->orderBy('key')->pluck('order', 'key')->all();

    expect($after)->toBe($before);
});

it('ordering survives seed -> migrate -> re-seed with no tie on order', function (): void {
    new SettingsSeeder()->run();
    brandingSettingsMigration()->up();
    new SettingsSeeder()->run();

    $general = Setting::where('group', 'general')->orderBy('order')->get();
    $siteName = $general->firstWhere('key', 'general.site_name');
    $rest = $general->where('key', '!=', 'general.site_name');

    expect($siteName)->not->toBeNull()
        ->and($general->first()->key)->toBe('general.site_name')
        ->and((int) $siteName->order)->toBeLessThan($rest->min('order'))
        ->and($rest->pluck('order'))->not->toContain($siteName->order);
});

it('down() deletes exactly the seven seeded rows', function (): void {
    brandingSettingsMigration()->up();

    brandingSettingsMigration()->down();

    foreach (brandingKeys() as $key) {
        expect(brandingSettingRow($key))->toBeNull();
    }

    // Nothing else in the general group was removed.
    expect(Setting::where('group', 'general')->where('key', 'general.theme')->exists())->toBeTrue();
});
