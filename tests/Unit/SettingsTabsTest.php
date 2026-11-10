<?php

declare(strict_types=1);

use App\Settings\GeneralSettings;
use App\Support\Settings\SettingDefinition;
use App\Support\Settings\SettingType;
use App\Support\SettingsTabs;
use Illuminate\Support\Collection;

/**
 * The settings pages lay their sections out through SettingsTabs: General
 * first, then alphabetically whatever order the settings come in, and the
 * settings of a section by declaration order, then by key on a tie, so the
 * page is the same on every load.
 */
function tabSetting(string $section, int $order, string $key): SettingDefinition
{
    return new SettingDefinition(
        class: GeneralSettings::class,
        group: $section,
        section: $section,
        key: $key,
        name: $key,
        type: SettingType::String,
        nullable: false,
        default: '',
        enum: null,
        hidden: false,
        encrypted: false,
        order: $order,
        labelKey: null,
        descriptionKey: null,
        translationPrefix: null,
    );
}

/** Section names, and each section's setting keys, as the pages would build them. */
function tabLayout(Collection $settings): array
{
    return SettingsTabs::group($settings)->map(fn (Collection $tab): array => $tab->pluck('key')->all())->all();
}

it('puts General first and orders the other sections alphabetically whatever order the settings come in', function (): void {
    $settings = collect([
        tabSetting('pilots', 1, 'pilots.a'),
        tabSetting('airports', 1, 'airports.a'),
        tabSetting('general', 1, 'general.a'),
        tabSetting('bids', 1, 'bids.a'),
    ]);

    $expected = ['general', 'airports', 'bids', 'pilots'];

    expect(array_keys(tabLayout($settings)))->toBe($expected)
        ->and(array_keys(tabLayout($settings->reverse())))->toBe($expected)
        ->and(array_keys(tabLayout($settings->shuffle())))->toBe($expected);
});

it('orders a section by declaration order, then by key on a tie', function (): void {
    $settings = collect([
        tabSetting('general', 2, 'general.c'),
        tabSetting('general', 1, 'general.z'),
        tabSetting('general', 2, 'general.a'),
        tabSetting('general', 10, 'general.b'),
    ]);

    $expected = ['general' => ['general.z', 'general.a', 'general.c', 'general.b']];

    expect(tabLayout($settings))->toBe($expected)
        ->and(tabLayout($settings->reverse()))->toBe($expected);
});

it('sorts the section names regardless of case', function (): void {
    // A case-sensitive sort would put 'Beta' before 'alpha'.
    $settings = collect([
        tabSetting('charlie', 1, 'c'),
        tabSetting('Beta', 1, 'b'),
        tabSetting('alpha', 1, 'a'),
    ]);

    expect(array_keys(tabLayout($settings)))->toBe(['alpha', 'Beta', 'charlie']);
});

it('keeps a numeric section name as its own section', function (): void {
    $settings = collect([
        tabSetting('2024', 1, 'a'),
        tabSetting('General', 1, 'b'),
    ]);

    expect(array_map(strval(...), array_keys(tabLayout($settings))))->toBe(['General', '2024']);
});
