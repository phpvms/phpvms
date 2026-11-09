<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AddonSetting;
use App\Models\Setting;
use Illuminate\Support\Collection;

/**
 * Groups settings into the tabs of the settings pages (core and addon).
 *
 * Tabs come in alphabetical order of their group, and the settings in a tab in
 * their `order`, then by key. Both are sorted here rather than in the query:
 * settings of different groups share `order` values, and rows tied on it come
 * back from the database in no fixed order, so the tabs used to change places
 * from one load to the next.
 */
final class SettingsTabs
{
    /**
     * @template TSetting of Setting|AddonSetting
     *
     * @param  Collection<int, TSetting>                     $settings
     * @return Collection<string, Collection<int, TSetting>>
     */
    public static function group(Collection $settings): Collection
    {
        return $settings
            ->sortBy([
                fn (Setting|AddonSetting $a, Setting|AddonSetting $b): int => (int) $a->order <=> (int) $b->order,
                fn (Setting|AddonSetting $a, Setting|AddonSetting $b): int => strcmp((string) $a->key, (string) $b->key),
            ])
            ->groupBy(fn (Setting|AddonSetting $setting): string => (string) $setting->group)
            ->sortKeys(SORT_NATURAL | SORT_FLAG_CASE);
    }
}
