<?php

declare(strict_types=1);

use App\Services\Theme\ThemeCssRenderer;
use App\Services\Theme\ThemeDocumentNormalizer;

it('renders complete light and dark Nuxt UI and phpVMS variables', function (): void {
    $document = app(ThemeDocumentNormalizer::class)->defaults();
    $css = app(ThemeCssRenderer::class)->render($document);

    expect($css)
        ->toContain(':root {')
        ->toContain('.dark {')
        ->toContain('--ui-primary: var(--ui-color-primary-600);')
        ->toContain('--ui-primary: var(--ui-color-primary-500);');

    $uiVariables = [
        'primary', 'secondary', 'success', 'info', 'warning', 'error',
        'text-dimmed', 'text-muted', 'text-toned', 'text', 'text-highlighted', 'text-inverted',
        'bg', 'bg-muted', 'bg-elevated', 'bg-accented', 'bg-inverted',
        'border', 'border-muted', 'border-accented', 'border-inverted',
        'radius', 'font', 'container', 'header-height',
    ];
    foreach ($uiVariables as $variable) {
        expect(substr_count($css, "--ui-{$variable}:"))->toBe(2);
    }

    foreach (['primary', 'secondary', 'success', 'info', 'warning', 'error', 'neutral'] as $color) {
        foreach ([50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950] as $shade) {
            expect(substr_count($css, "--ui-color-{$color}-{$shade}:"))->toBe(2);
        }
    }

    $pvVariables = [
        'bg', 'panel', 'panel-inset', 'hover', 'line', 'line-strong', 'ink', 'ink-dim', 'ink-faint', 'track',
        'accent', 'accent-soft', 'cyan', 'green', 'amber', 'red',
        'font-body', 'font-mono', 'font-display', 'font-family-base', 'font-family-mono', 'type-scale',
        'radius-sm', 'radius-md', 'radius-lg', 'radius-xl', 'radius-full', 'shadow-panel', 'shadow-chrome',
        'nav-width', 'header-height', 'aside-width', 'container-width',
        'globe-sea', 'globe-land', 'globe-coast', 'slot-error-bg', 'slot-error-border', 'slot-error-text',
        'color-page', 'color-surface', 'color-surface-alt', 'color-surface-raised', 'color-border',
        'color-text', 'color-text-muted', 'color-primary', 'color-primary-text', 'color-info',
        'color-success', 'color-warning', 'color-error',
    ];
    foreach ($pvVariables as $variable) {
        expect(substr_count($css, "--pv-{$variable}:"))->toBe(2);
    }

    foreach ([50, 100, 150, 200, 300, 400, 500, 600, 700, 800, 850, 900, 950] as $shade) {
        expect(substr_count($css, "--pv-ink-{$shade}:"))->toBe(2);
    }
});

it('keeps generated CSS limited to root and dark runtime declarations', function (): void {
    $css = app(ThemeCssRenderer::class)->render(app(ThemeDocumentNormalizer::class)->defaults());

    expect(substr_count($css, '{'))->toBe(2)
        ->and(substr_count($css, '}'))->toBe(2)
        ->and($css)->not->toContain('@theme')
        ->not->toContain('@import')
        ->not->toContain('.pv-')
        ->not->toContain('--pv-page-areas')
        ->not->toContain('--pv-shadow-lg');
});

it('renders MapLibre-compatible concrete globe colors', function (): void {
    $css = app(ThemeCssRenderer::class)->render(app(ThemeDocumentNormalizer::class)->defaults());
    preg_match_all('/--pv-globe-(?:sea|land|coast): ([^;]+);/', $css, $matches);

    expect($matches[1])->toBe([
        '#dde5ee', '#c7d0dc', '#a4aebd',
        '#0d0e12', '#14171e', '#262a34',
    ]);

    $declarations = implode("\n", $matches[0]);
    expect($declarations)
        ->not->toContain('var(')
        ->not->toContain('oklch(')
        ->not->toContain('color(')
        ->not->toContain('color-mix(');
});
