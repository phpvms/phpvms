<?php

declare(strict_types=1);

namespace App\Services\Theme;

final class ThemeCssRenderer
{
    private const array COLOR_KEYS = ['primary', 'secondary', 'success', 'info', 'warning', 'error'];

    private const array SHADES = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950];

    /**
     * @param array<string, mixed> $document
     */
    public function render(array $document): string
    {
        $theme = $document['nuxtUi']['theme'];

        return ":root {\n"
            .$this->declarations($theme, false)
            ."}\n\n.dark {\n"
            .$this->declarations($theme, true)
            ."}\n";
    }

    /**
     * @param array<string, mixed> $theme
     */
    private function declarations(array $theme, bool $dark): string
    {
        $colors = $dark ? $theme['darkColors'] : $theme['colors'];
        $colorShades = $dark ? $theme['darkColorShades'] : $theme['colorShades'];
        $neutral = $dark ? $theme['darkNeutral'] : $theme['neutral'];
        $overrides = $dark ? $theme['darkOverrides'] : $theme['lightOverrides'];
        $radius = $dark ? $theme['darkRadius'] : $theme['radius'];
        $font = $dark ? $theme['darkFont'] : $theme['font'];
        $globeColors = $dark
            ? ['sea' => '#0d0e12', 'land' => '#14171e', 'coast' => '#262a34']
            : ['sea' => '#dde5ee', 'land' => '#c7d0dc', 'coast' => '#a4aebd'];
        $lines = [];

        foreach ([...self::COLOR_KEYS, 'neutral'] as $key) {
            $palette = $key === 'neutral' ? $neutral : $colors[$key];
            foreach (self::SHADES as $shade) {
                $lines[] = "--ui-color-{$key}-{$shade}: var(--color-{$palette}-{$shade});";
            }
        }

        foreach (self::COLOR_KEYS as $key) {
            $lines[] = "--ui-{$key}: var(--ui-color-{$key}-{$colorShades[$key]});";
        }

        foreach ($overrides['text'] as $key => $shade) {
            $name = $key === 'default' ? 'text' : "text-{$key}";
            $lines[] = "--ui-{$name}: ".$this->neutralValue($shade).';';
        }

        foreach ($overrides['bg'] as $key => $shade) {
            $name = $key === 'default' ? 'bg' : "bg-{$key}";
            $lines[] = "--ui-{$name}: ".$this->neutralValue($shade).';';
        }

        foreach ($overrides['border'] as $key => $shade) {
            $name = $key === 'default' ? 'border' : "border-{$key}";
            $lines[] = "--ui-{$name}: ".$this->neutralValue($shade).';';
        }

        $radiusValue = $this->number($radius).'rem';
        $fontValue = '"'.$font.'", ui-sans-serif, system-ui, sans-serif';
        $lines[] = "--ui-radius: {$radiusValue};";
        $lines[] = "--ui-font: {$fontValue};";
        $lines[] = '--ui-container: 80rem;';
        $lines[] = '--ui-header-height: 4rem;';

        foreach (self::SHADES as $shade) {
            $lines[] = "--pv-ink-{$shade}: var(--ui-color-neutral-{$shade});";
        }

        $lines[] = '--pv-ink-150: color-mix(in srgb, var(--pv-ink-100) 50%, var(--pv-ink-200));';
        $lines[] = '--pv-ink-850: color-mix(in srgb, var(--pv-ink-800) 50%, var(--pv-ink-900));';
        $lines[] = '--pv-bg: var(--ui-bg-muted);';
        $lines[] = '--pv-panel: var(--ui-bg);';
        $lines[] = '--pv-panel-inset: var(--ui-bg-muted);';
        $lines[] = '--pv-hover: var(--ui-bg-elevated);';
        $lines[] = '--pv-line: var(--ui-border);';
        $lines[] = '--pv-line-strong: var(--ui-border-accented);';
        $lines[] = '--pv-ink: var(--ui-text);';
        $lines[] = '--pv-ink-dim: var(--ui-text-muted);';
        $lines[] = '--pv-ink-faint: var(--ui-text-dimmed);';
        $lines[] = '--pv-track: var(--ui-bg-accented);';
        $lines[] = '--pv-accent: var(--ui-primary);';
        $lines[] = '--pv-accent-soft: color-mix(in srgb, var(--ui-primary) 12%, transparent);';
        $lines[] = '--pv-cyan: var(--ui-info);';
        $lines[] = '--pv-green: var(--ui-success);';
        $lines[] = '--pv-amber: var(--ui-warning);';
        $lines[] = '--pv-red: var(--ui-error);';
        $lines[] = "--pv-font-body: {$fontValue};";
        $lines[] = '--pv-font-mono: "IBM Plex Mono", ui-monospace, monospace;';
        $lines[] = "--pv-font-display: {$fontValue};";
        $lines[] = '--pv-font-family-base: var(--pv-font-body);';
        $lines[] = '--pv-font-family-mono: var(--pv-font-mono);';
        $lines[] = '--pv-type-scale: 1;';
        $lines[] = '--pv-radius-sm: max(0rem, calc(var(--ui-radius) - 0.125rem));';
        $lines[] = '--pv-radius-md: var(--ui-radius);';
        $lines[] = '--pv-radius-lg: calc(var(--ui-radius) + 0.125rem);';
        $lines[] = '--pv-radius-xl: calc(var(--ui-radius) + 0.25rem);';
        $lines[] = '--pv-radius-full: 9999px;';
        $lines[] = '--pv-shadow-panel: none;';
        $lines[] = '--pv-shadow-chrome: none;';
        $lines[] = '--pv-nav-width: 240px;';
        $lines[] = '--pv-header-height: 56px;';
        $lines[] = '--pv-aside-width: 240px;';
        $lines[] = '--pv-container-width: 1360px;';
        $lines[] = "--pv-globe-sea: {$globeColors['sea']};";
        $lines[] = "--pv-globe-land: {$globeColors['land']};";
        $lines[] = "--pv-globe-coast: {$globeColors['coast']};";
        $lines[] = '--pv-slot-error-bg: color-mix(in srgb, var(--ui-error) 12%, var(--ui-bg));';
        $lines[] = '--pv-slot-error-border: color-mix(in srgb, var(--ui-error) 35%, var(--ui-border));';
        $lines[] = '--pv-slot-error-text: var(--ui-error);';
        $lines[] = '--pv-color-page: var(--pv-bg);';
        $lines[] = '--pv-color-surface: var(--pv-panel);';
        $lines[] = '--pv-color-surface-alt: var(--pv-panel-inset);';
        $lines[] = '--pv-color-surface-raised: var(--pv-panel);';
        $lines[] = '--pv-color-border: var(--pv-line);';
        $lines[] = '--pv-color-text: var(--pv-ink);';
        $lines[] = '--pv-color-text-muted: var(--pv-ink-dim);';
        $lines[] = '--pv-color-primary: var(--pv-accent);';
        $lines[] = '--pv-color-primary-text: var(--ui-text-inverted);';
        $lines[] = '--pv-color-info: var(--pv-cyan);';
        $lines[] = '--pv-color-success: var(--pv-green);';
        $lines[] = '--pv-color-warning: var(--pv-amber);';
        $lines[] = '--pv-color-error: var(--pv-red);';

        return '  '.implode("\n  ", $lines)."\n";
    }

    private function neutralValue(string $shade): string
    {
        return match ($shade) {
            'white' => '#fff',
            'black' => '#000',
            default => "var(--ui-color-neutral-{$shade})",
        };
    }

    private function number(int|float $number): string
    {
        return rtrim(rtrim(number_format($number, 3, '.', ''), '0'), '.');
    }
}
