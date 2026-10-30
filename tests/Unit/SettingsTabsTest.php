<?php

declare(strict_types=1);

use App\Models\AddonSetting;
use App\Models\Setting;
use App\Support\SettingsTabs;
use Illuminate\Support\Collection;

/**
 * The settings pages grouped rows sorted on `order` alone. Settings of different
 * groups share `order` values and the database returns rows tied on it in no
 * fixed order, so the tabs changed places from one load to the next.
 */
function tabSetting(string $class, string $group, int $order, string $key): Setting|AddonSetting
{
    return new $class()->forceFill(['group' => $group, 'order' => $order, 'key' => $key]);
}

/** Tab names, and each tab's setting keys, as the pages would build them. */
function tabLayout(Collection $settings): array
{
    return SettingsTabs::group($settings)->map(fn (Collection $tab): array => $tab->pluck('key')->all())->all();
}

it('orders the tabs alphabetically whatever order the rows come in', function (string $class): void {
    $settings = collect([
        tabSetting($class, 'pilots', 1, 'pilots.a'),
        tabSetting($class, 'airports', 1, 'airports.a'),
        tabSetting($class, 'general', 1, 'general.a'),
        tabSetting($class, 'bids', 1, 'bids.a'),
    ]);

    expect(array_keys(tabLayout($settings)))->toBe(['airports', 'bids', 'general', 'pilots'])
        ->and(array_keys(tabLayout($settings->reverse())))->toBe(['airports', 'bids', 'general', 'pilots'])
        ->and(array_keys(tabLayout($settings->shuffle())))->toBe(['airports', 'bids', 'general', 'pilots']);
})->with([Setting::class, AddonSetting::class]);

it('orders a tab by order, then by key on a tie', function (string $class): void {
    $settings = collect([
        tabSetting($class, 'general', 2, 'general.c'),
        tabSetting($class, 'general', 1, 'general.z'),
        tabSetting($class, 'general', 2, 'general.a'),
        tabSetting($class, 'general', 10, 'general.b'),
    ]);

    $expected = ['general' => ['general.z', 'general.a', 'general.c', 'general.b']];

    expect(tabLayout($settings))->toBe($expected)
        ->and(tabLayout($settings->reverse()))->toBe($expected);
})->with([Setting::class, AddonSetting::class]);

it('sorts the tab names regardless of case', function (): void {
    // A case-sensitive sort would put 'Beta' before 'alpha'.
    $settings = collect([
        tabSetting(AddonSetting::class, 'charlie', 1, 'c'),
        tabSetting(AddonSetting::class, 'Beta', 1, 'b'),
        tabSetting(AddonSetting::class, 'alpha', 1, 'a'),
    ]);

    expect(array_keys(tabLayout($settings)))->toBe(['alpha', 'Beta', 'charlie']);
});
