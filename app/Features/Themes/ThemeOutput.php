<?php

declare(strict_types=1);

namespace App\Features\Themes;

use App\Enums\GlobalSlotPosition;
use App\Enums\GlobalSlotType;
use App\Models\GlobalSlot;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Compiles the head and footer output of a theme: its emit-tagged fields
 * followed by the enabled global slot rows.
 *
 * Head order: the theme's `scheme` fields (each rendered by {@see ColorSchemeCss}
 * from `presets.color_schemes`; an unknown or blank key emits nothing), the theme's `css` fields, global `css` rows, the theme's `head`
 * fields, global `head` rows. Footer order: the theme's `footer` fields, global
 * `footer` rows. Global rows follow the theme's values in `order`. CSS (a
 * `css` field or a row of type `css`) is wrapped in `<style>` with any
 * `</style` neutralised; everything else is raw HTML. Blank values emit nothing.
 *
 * Output is cached in the default store under
 * `theme-output:{theme}:{versions}:v{N}:{head|footer}`. `N` is the counter
 * {@see self::bump()} increments on every save, re-order, toggle or delete of a
 * global slot and every theme field write, so old entries fall out of use for
 * every server at once. A missing entry is compiled and stored.
 *
 * Octane: holds no state; the counter and entries live in the cache.
 */
final readonly class ThemeOutput
{
    public const string COUNTER_KEY = 'theme-output:counter';

    /**
     * A TTL (ten years) makes Cache::add use the store's atomic add(); with no
     * TTL the repository falls back to a non-atomic get then put.
     */
    private const int COUNTER_TTL = 315_360_000;

    public function __construct(
        private ThemeOptions $options,
        private ThemeFields $fields,
    ) {}

    public function head(string $theme): string
    {
        return $this->cached($theme, 'head', fn (): string => $this->compileHead($theme));
    }

    public function footer(string $theme): string
    {
        return $this->cached($theme, 'footer', fn (): string => $this->compileFooter($theme));
    }

    /**
     * Invalidates every compiled entry. The counter starts at the current time
     * so that a counter lost from the cache restarts above any value it had.
     */
    public static function bump(): void
    {
        Cache::add(self::COUNTER_KEY, time(), self::COUNTER_TTL);
        Cache::increment(self::COUNTER_KEY);
    }

    /**
     * @param Closure():string $compile
     */
    private function cached(string $theme, string $part, Closure $compile): string
    {
        $counter = Cache::get(self::COUNTER_KEY);

        if (!is_int($counter)) {
            Cache::add(self::COUNTER_KEY, time(), self::COUNTER_TTL);
            $counter = (int) Cache::get(self::COUNTER_KEY, 0);
        }

        $key = sprintf('theme-output:%s:%s:v%d:%s', $theme, $this->options->versions($theme), $counter, $part);

        return Cache::rememberForever($key, $compile);
    }

    private function compileHead(string $theme): string
    {
        return $this->join([
            $this->schemeValues($theme),
            $this->themeValues($theme, 'css'),
            $this->globalValues(GlobalSlotPosition::CSS),
            $this->themeValues($theme, 'head'),
            $this->globalValues(GlobalSlotPosition::HEAD),
        ]);
    }

    private function compileFooter(string $theme): string
    {
        return $this->join([
            $this->themeValues($theme, 'footer'),
            $this->globalValues(GlobalSlotPosition::FOOTER),
        ]);
    }

    /**
     * @param list<list<string>> $groups
     */
    private function join(array $groups): string
    {
        return implode("\n", array_merge(...$groups));
    }

    /**
     * @return list<string>
     */
    private function schemeValues(string $theme): array
    {
        $out = [];

        foreach ($this->options->defaults($theme) as $name => $field) {
            if ($field['emit'] !== 'scheme') {
                continue;
            }

            $value = $this->fields->get($theme, $name);
            $scheme = is_string($value) && $value !== '' ? config('presets.color_schemes.'.$value) : null;

            if (is_array($scheme)) {
                $out[] = $this->style(app(ColorSchemeCss::class)->render($scheme));
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function themeValues(string $theme, string $emit): array
    {
        $out = [];

        foreach ($this->options->defaults($theme) as $name => $field) {
            if ($field['emit'] !== $emit) {
                continue;
            }

            $value = $this->fields->get($theme, $name);

            if (is_string($value) && trim($value) !== '') {
                $out[] = $emit === 'css' ? $this->style($value) : $value;
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function globalValues(GlobalSlotPosition $slot): array
    {
        if (!Schema::hasTable('global_slots')) {
            return [];
        }

        $out = [];

        $rows = GlobalSlot::query()
            ->where('key', GlobalSlot::SITE_KEY)
            ->where('slot', $slot->value)
            ->where('enabled', true)
            ->orderBy('order')
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            $value = (string) $row->value;

            if (trim($value) === '') {
                continue;
            }

            $out[] = $row->type === GlobalSlotType::CSS ? $this->style($value) : $value;
        }

        return $out;
    }

    private function style(string $css): string
    {
        return '<style>'.str_ireplace('</style', '<\/style', $css).'</style>';
    }
}
