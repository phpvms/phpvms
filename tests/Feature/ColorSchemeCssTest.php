<?php

declare(strict_types=1);

use App\Features\Themes\ColorSchemeCss;
use Filament\Support\Colors\Color;

function airsideBaseCss(): string
{
    return (string) file_get_contents(base_path('modules/phpvms-airside-theme/resources/customize/styles.css'));
}

function cssBlock(string $css, string $selector): string
{
    preg_match('/'.preg_quote($selector, '/').'\s*\{(.*?)\}/s', $css, $m);

    return $m[1] ?? '';
}

function cssVar(string $block, string $name): ?string
{
    return preg_match('/'.preg_quote($name, '/').':\s*([^;]*);/', $block, $m) === 1 ? trim($m[1]) : null;
}

/**
 * @return list<string>
 */
function nonColourLines(string $css): array
{
    return array_values(array_filter(
        explode("\n", $css),
        fn (string $line): bool => !preg_match('/--color-(primary|secondary|dark)-\d+:/', $line),
    ));
}

it('replaces the three ramps with a named palette scheme and leaves other lines alone', function (): void {
    $base = airsideBaseCss();
    $out = app(ColorSchemeCss::class)->render($base, ['primary' => 'green', 'secondary' => 'lime', 'surface' => 'slate']);

    expect(cssVar(cssBlock($out, ':root'), '--color-primary-600'))->toBe(Color::all()['green'][600])
        ->and(cssVar(cssBlock($out, ':root'), '--color-secondary-600'))->toBe(Color::all()['lime'][600])
        ->and(cssVar(cssBlock($out, ':root'), '--color-dark-950'))->toBe(Color::all()['slate'][950])
        ->and(substr_count($out, "\n"))->toBe(substr_count($base, "\n"))
        ->and(nonColourLines($out))->toBe(nonColourLines($base));
});

it('uses a stored shade array as-is', function (): void {
    $stored = [50 => '#000001', 100 => '#000002', 200 => '#000003', 300 => '#000004', 400 => '#000005', 500 => '#000006', 600 => '#000007', 700 => '#000008', 800 => '#000009', 900 => '#00000a', 950 => '#00000b'];
    $out = app(ColorSchemeCss::class)->render(airsideBaseCss(), ['primary' => $stored, 'secondary' => 'blue', 'surface' => 'slate']);

    foreach ($stored as $shade => $hex) {
        expect($out)->toContain("--color-primary-{$shade}: {$hex};");
    }

    expect($out)->toContain('--color-secondary-500: '.Color::all()['blue'][500].';');
});

it('groups the schemes by category for the select', function (): void {
    config(['presets.color_schemes' => [
        'one'   => ['name' => 'One', 'category' => 'Alpha', 'primary' => 'green', 'secondary' => 'lime', 'surface' => 'slate'],
        'two'   => ['name' => 'Two', 'category' => 'Alpha', 'primary' => 'red', 'secondary' => 'lime', 'surface' => 'slate'],
        'three' => ['name' => 'Three', 'category' => 'Beta', 'primary' => 'blue', 'secondary' => 'lime', 'surface' => 'slate'],
    ]]);

    $options = app(ColorSchemeCss::class)->options();

    expect($options)->toHaveKeys(['Alpha', 'Beta'])
        ->and($options['Alpha'])->toHaveKey('one', 'One')
        ->and(array_sum(array_map(count(...), $options)))->toBe(3);
});

it('only uses palette names or 11-shade arrays', function (): void {
    foreach (config('presets.color_schemes') as $scheme) {
        foreach (['primary', 'secondary', 'surface', 'dark_primary', 'dark_secondary'] as $role) {
            if (!array_key_exists($role, $scheme)) {
                expect(in_array($role, ['dark_primary', 'dark_secondary'], true))->toBeTrue();

                continue;
            }

            $value = $scheme[$role];

            if (is_array($value)) {
                expect(array_keys($value))->toBe([50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950]);
            } else {
                expect(array_key_exists($value, Color::all()))->toBeTrue();
            }
        }

        foreach (['shift', 'dark_shift'] as $key) {
            foreach ($scheme[$key] ?? [] as $role => $offset) {
                expect($role)->toBeIn(['primary', 'secondary'])
                    ->and($offset)->toBeInt();
            }
        }
    }
});

it('ships a :root.dark block whose primary and secondary equal the light block', function (): void {
    $css = airsideBaseCss();
    $light = cssBlock($css, ':root');
    $dark = cssBlock($css, ':root.dark');

    expect($dark)->not->toBe('');

    foreach (['primary', 'secondary'] as $role) {
        foreach ([50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950] as $shade) {
            expect(cssVar($dark, "--color-{$role}-{$shade}"))
                ->not->toBeNull()
                ->toBe(cssVar($light, "--color-{$role}-{$shade}"));
        }
    }

    expect($dark)->not->toContain('--color-dark-');
});

it('shifts the light and dark blocks independently', function (): void {
    $green = Color::all()['green'];
    $out = app(ColorSchemeCss::class)->render(airsideBaseCss(), [
        'primary' => 'green', 'secondary' => 'lime', 'surface' => 'slate',
        'shift'   => ['primary' => 2], 'dark_shift' => ['primary' => -1],
    ]);
    $light = cssBlock($out, ':root');
    $dark = cssBlock($out, ':root.dark');

    expect(cssVar($light, '--color-primary-500'))->toBe($green[700])
        ->and(cssVar($dark, '--color-primary-500'))->toBe($green[400])
        ->and(cssVar($light, '--color-primary-950'))->toBe($green[950])
        ->and(cssVar($dark, '--color-primary-50'))->toBe($green[50]);
});

it('writes dark_primary and dark_secondary into the dark block only', function (): void {
    $scheme = [
        'primary'      => 'orange', 'secondary' => 'rose', 'surface' => 'slate',
        'dark_primary' => 'violet', 'dark_secondary' => 'fuchsia',
    ];
    $out = app(ColorSchemeCss::class)->render(airsideBaseCss(), $scheme);
    $light = cssBlock($out, ':root');
    $dark = cssBlock($out, ':root.dark');

    expect(cssVar($light, '--color-primary-500'))->toBe(Color::all()['orange'][500])
        ->and(cssVar($dark, '--color-primary-500'))->toBe(Color::all()['violet'][500])
        ->and(cssVar($light, '--color-secondary-500'))->toBe(Color::all()['rose'][500])
        ->and(cssVar($dark, '--color-secondary-500'))->toBe(Color::all()['fuchsia'][500])
        ->and($light)->not->toContain(Color::all()['violet'][500])
        ->and($dark)->not->toContain(Color::all()['orange'][500]);
});

it('falls back to the light palette in the dark block when no dark palette is set', function (): void {
    $out = app(ColorSchemeCss::class)->render(airsideBaseCss(), ['primary' => 'green', 'secondary' => 'lime', 'surface' => 'zinc']);

    expect(cssVar(cssBlock($out, ':root.dark'), '--color-primary-500'))->toBe(Color::all()['green'][500])
        ->and(cssVar(cssBlock($out, ':root.dark'), '--color-secondary-500'))->toBe(Color::all()['lime'][500]);
});

it('changes the surface values in the light block only', function (): void {
    $base = ['primary' => 'green', 'secondary' => 'lime'];
    $a = app(ColorSchemeCss::class)->render(airsideBaseCss(), $base + ['surface' => 'slate']);
    $b = app(ColorSchemeCss::class)->render(airsideBaseCss(), $base + ['surface' => 'stone']);

    expect(cssVar(cssBlock($a, ':root'), '--color-dark-900'))->toBe(Color::all()['slate'][900])
        ->and(cssVar(cssBlock($b, ':root'), '--color-dark-900'))->toBe(Color::all()['stone'][900])
        ->and(cssBlock($a, ':root.dark'))->toBe(cssBlock($b, ':root.dark'))
        ->and(cssBlock($a, ':root.dark'))->not->toContain('--color-dark-');
});

it('skips the dark block silently when the base css has none', function (): void {
    $base = (string) preg_replace('/:root\.dark\s*\{.*?\}/s', '', airsideBaseCss());
    $out = app(ColorSchemeCss::class)->render($base, ['primary' => 'green', 'secondary' => 'lime', 'surface' => 'slate']);

    expect($out)->not->toMatch('/:root\.dark\s*\{/')
        ->and(cssVar(cssBlock($out, ':root'), '--color-primary-500'))->toBe(Color::all()['green'][500]);
});

it('clamps a shift at the ends of the ramp', function (): void {
    $out = app(ColorSchemeCss::class)->render(airsideBaseCss(), [
        'primary' => 'green', 'secondary' => 'lime', 'surface' => 'zinc', 'shift' => ['primary' => 5],
    ]);
    $light = cssBlock($out, ':root');

    expect(cssVar($light, '--color-primary-50'))->toBe(Color::all()['green'][500])
        ->and(cssVar($light, '--color-primary-950'))->toBe(Color::all()['green'][950]);
});

it('throws for an unknown palette name', function (): void {
    $scheme = ['primary' => 'not-a-palette', 'secondary' => 'blue', 'surface' => 'slate'];

    app(ColorSchemeCss::class)->render(airsideBaseCss(), $scheme);
})->throws(InvalidArgumentException::class, "colour scheme key 'primary'");
