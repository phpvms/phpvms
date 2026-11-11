<?php

declare(strict_types=1);

namespace App\Support;

use App\Support\Settings\SettingDefinition;
use Illuminate\Support\Collection;

/**
 * Groups settings into the sections of the settings pages (core and addon).
 *
 * Sections come General first, then in alphabetical order, and the settings in
 * a section in their declaration order, then by key, so the page lays out the
 * same way on every load.
 */
final class SettingsTabs
{
    /**
     * @param  Collection<int, SettingDefinition>                     $settings
     * @return Collection<string, Collection<int, SettingDefinition>>
     */
    public static function group(Collection $settings): Collection
    {
        $groups = $settings
            ->sortBy([
                fn (SettingDefinition $a, SettingDefinition $b): int => $a->order <=> $b->order,
                fn (SettingDefinition $a, SettingDefinition $b): int => strcmp($a->key, $b->key),
            ])
            ->groupBy(fn (SettingDefinition $setting): string => $setting->section)
            ->sortKeys(SORT_NATURAL | SORT_FLAG_CASE)
            ->toBase();

        // union(), not merge(): section names may be numeric strings, and
        // merge() renumbers the integer keys they become.
        $general = $groups->filter(fn (Collection $settings, string|int $section): bool => strcasecmp((string) $section, 'general') === 0);

        return $general->union($groups->diffKeys($general));
    }
}
