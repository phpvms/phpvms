<?php

declare(strict_types=1);

namespace App\Features\Themes;

use Filament\Support\Colors\Color;
use InvalidArgumentException;

/**
 * Regenerates a theme's runtime-variables CSS from a named colour scheme.
 *
 * Only `--color-*` declaration values are replaced; every other character is
 * left as is. The `:root { }` block gets the light primary and secondary ramps
 * (moved by `shift`) and the `surface` ramp as `--color-dark-*`. The
 * `:root.dark { }` block, when present, gets `dark_primary` and `dark_secondary`
 * (each falling back to the light ramp), moved by `dark_shift`.
 * Schemes are read from `config('presets.color_schemes')`.
 */
final class ColorSchemeCss
{
    private const array SHADES = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950];

    /**
     * @param array{primary: string|array<int, string>, secondary: string|array<int, string>, surface: string|array<int, string>, dark_primary?: string|array<int, string>, dark_secondary?: string|array<int, string>, shift?: array{primary?: int, secondary?: int}, dark_shift?: array{primary?: int, secondary?: int}} $scheme
     */
    public function render(string $baseCss, array $scheme): string
    {
        $light = [
            'primary'   => $this->shifted($this->ramp($scheme['primary'], 'primary'), $scheme['shift']['primary'] ?? 0),
            'secondary' => $this->shifted($this->ramp($scheme['secondary'], 'secondary'), $scheme['shift']['secondary'] ?? 0),
            'dark'      => $this->ramp($scheme['surface'], 'surface'),
        ];

        $dark = [
            'primary' => $this->shifted(
                $this->ramp($scheme['dark_primary'] ?? $scheme['primary'], 'dark_primary'),
                $scheme['dark_shift']['primary'] ?? 0,
            ),
            'secondary' => $this->shifted(
                $this->ramp($scheme['dark_secondary'] ?? $scheme['secondary'], 'dark_secondary'),
                $scheme['dark_shift']['secondary'] ?? 0,
            ),
        ];

        $baseCss = (string) preg_replace_callback(
            '/(:root\s*\{)(.*?)(\})/s',
            fn (array $m): string => $m[1].$this->replaceRamps($m[2], $light).$m[3],
            $baseCss,
        );

        return (string) preg_replace_callback(
            '/(:root\.dark\s*\{)(.*?)(\})/s',
            fn (array $m): string => $m[1].$this->replaceRamps($m[2], $dark).$m[3],
            $baseCss,
        );
    }

    /**
     * Scheme slugs and names grouped by category, in config order.
     *
     * @return array<string, array<string, string>>
     */
    public function options(): array
    {
        $grouped = [];

        foreach (config('presets.color_schemes', []) as $slug => $scheme) {
            $grouped[$scheme['category']][$slug] = $scheme['name'];
        }

        return $grouped;
    }

    /**
     * @param  string|array<int|string, string> $palette
     * @return array<int|string, string>
     */
    private function ramp(string|array $palette, string $key): array
    {
        if (is_array($palette)) {
            return $palette;
        }

        return Color::all()[$palette]
            ?? throw new InvalidArgumentException("Unknown palette '{$palette}' for colour scheme key '{$key}'.");
    }

    /**
     * @param array<string, array<int|string, string>> $ramps role => shade => value
     */
    private function replaceRamps(string $block, array $ramps): string
    {
        foreach ($ramps as $role => $ramp) {
            foreach (self::SHADES as $shade) {
                if (!isset($ramp[$shade])) {
                    continue;
                }

                $value = (string) $ramp[$shade];
                $block = (string) preg_replace_callback(
                    '/(--color-'.$role.'-'.$shade.'\s*:\s*)[^;]*(;)/',
                    static fn (array $m): string => $m[1].$value.$m[2],
                    $block,
                );
            }
        }

        return $block;
    }

    /**
     * The value at stop i becomes the ramp's value at stop i + offset, clamped to the ends.
     *
     * @param  array<int|string, string> $ramp
     * @return array<int, string>
     */
    private function shifted(array $ramp, int $offset): array
    {
        if ($offset === 0) {
            return $ramp;
        }

        $last = count(self::SHADES) - 1;
        $out = [];

        foreach (self::SHADES as $index => $shade) {
            $source = self::SHADES[max(0, min($last, $index + $offset))];

            if (isset($ramp[$source])) {
                $out[$shade] = $ramp[$source];
            }
        }

        return $out;
    }
}
