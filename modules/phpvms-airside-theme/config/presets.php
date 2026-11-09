<?php

declare(strict_types=1);

/**
 * Colour schemes for the airside theme's Theme page picker.
 *
 * These are colour schemes, not themes: each one names the ramps the airside
 * runtime-variables file defines.
 *
 * - `primary` and `secondary` are the brand ramps in light mode
 *   (`--color-primary-*`, `--color-secondary-*` in `:root`).
 * - `dark_primary` and `dark_secondary` are the brand ramps in dark mode
 *   (the same variables in `:root.dark`). Omitted when they equal the light ones.
 * - `surface` is the neutral palette for dark-mode surfaces (`--color-dark-*`).
 * - `shift` and `dark_shift` move a brand ramp along its stops per role: with
 *   an offset k, the value written to a stop is the ramp's value k stops further
 *   along (clamped), so +1 makes 500 carry the 600 value. Zero offsets are omitted.
 *
 * Each palette is either a Tailwind palette name (a key of Filament's
 * `Color::all()`) or an explicit 11-shade array keyed 50, 100, ... 950. Values
 * are stored, never generated at runtime. The nuxt-ui primary ramp was
 * precomputed once with the Nuxt UI Theme Builder's own palette generator from
 * #00dc82.
 *
 * Fonts, radius and the per-mode token overrides of the source presets are
 * intentionally not ported.
 *
 * Scheme names, categories, descriptions, palette assignments and shade shifts
 * come from the Nuxt UI Theme Builder presets (MIT), except `phpvms`, which is
 * our own; `sky` is the source's "Minimal", renamed and with its shade shifts
 * removed. See ATTRIBUTIONS.md.
 *
 * @return array{color_schemes: array<string, array{name: string, category: string, description: string, primary: string|array<int, string>, secondary: string|array<int, string>, surface: string|array<int, string>, dark_primary?: string|array<int, string>, dark_secondary?: string|array<int, string>, shift?: array{primary?: int, secondary?: int}, dark_shift?: array{primary?: int, secondary?: int}}>}
 */
return [
    'color_schemes' => [
        // phpvms's own scheme, primary ramp with the logo blue #067ec1 anchored at 500 (re-anchored in OKLCH from the Theme Builder generator output).
        'phpvms' => [
            'name'        => 'phpvms',
            'category'    => 'Essentials',
            'description' => 'The phpvms logo blue',
            'primary'     => [
                50  => '#f1f9fe',
                100 => '#c9e0f3',
                200 => '#a1c8e8',
                300 => '#78afdb',
                400 => '#4c97ce',
                500 => '#067ec1',
                600 => '#0d6da7',
                700 => '#105d8d',
                800 => '#114d75',
                900 => '#113e5d',
                950 => '#0f2f46',
            ],
            'secondary' => 'sky',
            'surface'   => 'neutral',
        ],
        'default' => [
            'name'        => 'Default',
            'category'    => 'Essentials',
            'description' => 'Balanced indigo palette with standard contrast on zinc neutral. A versatile classic.',
            'primary'     => 'indigo',
            'secondary'   => 'sky',
            'surface'     => 'zinc',
            'shift'       => ['primary' => 1, 'secondary' => 2],
        ],
        'nuxt-ui' => [
            'name'        => 'Nuxt UI',
            'category'    => 'Essentials',
            'description' => 'Official Nuxt UI defaults. Nuxt green primary, blue secondary, slate neutral with standard shading. Matches stock Nuxt UI exactly, so light-mode buttons fall short of WCAG AA contrast.',
            'primary'     => [
                50  => '#f0fdf5',
                100 => '#dcfbe9',
                200 => '#b9f6d4',
                300 => '#7befb1',
                400 => '#00dc82',
                500 => '#00c46b',
                600 => '#00a256',
                700 => '#007f44',
                800 => '#016538',
                900 => '#0e5331',
                950 => '#042e19',
            ],
            'secondary' => 'blue',
            'surface'   => 'slate',
        ],
        'shadcn' => [
            'name'        => 'shadcn',
            'category'    => 'Essentials',
            'description' => 'Inspired by shadcn/ui. Near-black dark mode, zinc neutrals, Geist font, and minimal borders for a clean developer aesthetic.',
            'primary'     => 'zinc',
            'secondary'   => 'zinc',
            'surface'     => 'zinc',
            'shift'       => ['primary' => 5, 'secondary' => 1],
            'dark_shift'  => ['primary' => -5, 'secondary' => -1],
        ],
        'cherry' => [
            'name'        => 'Cherry',
            'category'    => 'Warm',
            'description' => 'Bold red tones for high-energy, attention-grabbing interfaces.',
            'primary'     => 'red',
            'secondary'   => 'orange',
            'surface'     => 'zinc',
            'shift'       => ['primary' => 1, 'secondary' => 2],
            'dark_shift'  => ['primary' => -1, 'secondary' => -1],
        ],
        'sunset' => [
            'name'           => 'Sunset',
            'category'       => 'Warm',
            'description'    => 'Dual personality — warm orange in light, cool violet in dark. Every setting transforms between modes.',
            'primary'        => 'orange',
            'secondary'      => 'rose',
            'surface'        => 'slate',
            'dark_primary'   => 'violet',
            'dark_secondary' => 'fuchsia',
            'shift'          => ['primary' => 2, 'secondary' => 1],
            'dark_shift'     => ['primary' => -1, 'secondary' => -1],
        ],
        'sahara' => [
            'name'        => 'Sahara',
            'category'    => 'Warm',
            'description' => 'Desert-inspired amber warmth on stone neutral. Rich and earthy.',
            'primary'     => 'amber',
            'secondary'   => 'orange',
            'surface'     => 'zinc',
            'shift'       => ['primary' => 2, 'secondary' => 2],
            'dark_shift'  => ['primary' => -1, 'secondary' => -1],
        ],
        'sunflower' => [
            'name'        => 'Sunflower',
            'category'    => 'Warm',
            'description' => 'Bright yellow for cheerful, optimistic designs. Deeper shades ensure readability.',
            'primary'     => 'yellow',
            'secondary'   => 'amber',
            'surface'     => 'zinc',
            'shift'       => ['primary' => 2, 'secondary' => 2],
            'dark_shift'  => ['primary' => -1, 'secondary' => -1],
        ],
        'zest' => [
            'name'        => 'Zest',
            'category'    => 'Nature',
            'description' => 'Fresh lime green with modern energy and clean lines.',
            'primary'     => 'lime',
            'secondary'   => 'green',
            'surface'     => 'neutral',
            'shift'       => ['primary' => 2, 'secondary' => 2],
            'dark_shift'  => ['primary' => -1, 'secondary' => -1],
        ],
        'forest' => [
            'name'        => 'Forest',
            'category'    => 'Nature',
            'description' => 'Natural green tones on warm stone. Earthy and grounded.',
            'primary'     => 'green',
            'secondary'   => 'lime',
            'surface'     => 'slate',
            'shift'       => ['primary' => 2, 'secondary' => 2],
            'dark_shift'  => ['primary' => -1, 'secondary' => -1],
        ],
        'emerald' => [
            'name'        => 'Emerald',
            'category'    => 'Nature',
            'description' => 'Rich emerald gemstone palette with Plus Jakarta Sans. Sophisticated and luxurious.',
            'primary'     => 'emerald',
            'secondary'   => 'teal',
            'surface'     => 'gray',
            'shift'       => ['primary' => 2, 'secondary' => 2],
            'dark_shift'  => ['primary' => -1, 'secondary' => -1],
        ],
        'coastal' => [
            'name'        => 'Coastal',
            'category'    => 'Cool',
            'description' => 'Calm teal tones evoking ocean shores and open skies.',
            'primary'     => 'teal',
            'secondary'   => 'cyan',
            'surface'     => 'slate',
            'shift'       => ['primary' => 2, 'secondary' => 2],
            'dark_shift'  => ['primary' => -1, 'secondary' => -1],
        ],
        'arctic' => [
            'name'        => 'Arctic',
            'category'    => 'Cool',
            'description' => 'Icy cyan on slate. Cool, crisp, and professional.',
            'primary'     => 'cyan',
            'secondary'   => 'sky',
            'surface'     => 'gray',
            'shift'       => ['primary' => 2, 'secondary' => 2],
            'dark_shift'  => ['primary' => -1, 'secondary' => -1],
        ],
        // Shade shifts removed as a trial: every shade stays distinct.
        'sky' => [
            'name'        => 'Sky',
            'category'    => 'Cool',
            'description' => 'Clean sky blue. Content-focused design.',
            'primary'     => 'sky',
            'secondary'   => 'blue',
            'surface'     => 'neutral',
        ],
        'corporate' => [
            'name'        => 'Corporate',
            'category'    => 'Cool',
            'description' => 'Professional blue with high contrast. Ideal for business applications.',
            'primary'     => 'blue',
            'secondary'   => 'sky',
            'surface'     => 'gray',
            'shift'       => ['primary' => 1, 'secondary' => 2],
            'dark_shift'  => ['primary' => -1, 'secondary' => -1],
        ],
        'dark-mono' => [
            'name'        => 'Dark Mono',
            'category'    => 'Bold',
            'description' => 'High-contrast violet with deep 950 dark base. Bold and dramatic.',
            'primary'     => 'violet',
            'secondary'   => 'indigo',
            'surface'     => 'neutral',
            'shift'       => ['primary' => 1],
            'dark_shift'  => ['primary' => -1, 'secondary' => -1],
        ],
        'lavender' => [
            'name'         => 'Lavender',
            'category'     => 'Bold',
            'description'  => 'Soft purple tones deepening to rich violet in dark mode.',
            'primary'      => 'purple',
            'secondary'    => 'fuchsia',
            'surface'      => 'neutral',
            'dark_primary' => 'violet',
            'shift'        => ['primary' => 1, 'secondary' => 1],
            'dark_shift'   => ['primary' => -1, 'secondary' => -1],
        ],
        'neon' => [
            'name'           => 'Neon',
            'category'       => 'Bold',
            'description'    => 'Vibrant fuchsia with bright shades. Playful and high-energy.',
            'primary'        => 'fuchsia',
            'secondary'      => 'violet',
            'surface'        => 'zinc',
            'dark_secondary' => 'cyan',
            'shift'          => ['primary' => 1, 'secondary' => 1],
            'dark_shift'     => ['primary' => -1, 'secondary' => -1],
        ],
        'blush' => [
            'name'           => 'Blush',
            'category'       => 'Warm',
            'description'    => 'Dual personality — soft pink in light transforms to cool indigo in dark. Colors, shape, and feel all change between modes.',
            'primary'        => 'pink',
            'secondary'      => 'rose',
            'surface'        => 'slate',
            'dark_primary'   => 'indigo',
            'dark_secondary' => 'cyan',
            'shift'          => ['primary' => 1, 'secondary' => 1],
            'dark_shift'     => ['primary' => -1, 'secondary' => -1],
        ],
        'rose-gold' => [
            'name'           => 'Rose Gold',
            'category'       => 'Warm',
            'description'    => 'Elegant rose on warm stone neutral. Refined and sophisticated.',
            'primary'        => 'rose',
            'secondary'      => 'pink',
            'surface'        => 'zinc',
            'dark_secondary' => 'fuchsia',
            'shift'          => ['primary' => 1, 'secondary' => 1],
            'dark_shift'     => ['primary' => -1, 'secondary' => -1],
        ],
        'spotify' => [
            'name'        => 'Spotify',
            'category'    => 'Brands',
            'description' => "Inspired by Spotify's iconic green-on-black aesthetic. High contrast with deep dark backgrounds.",
            'primary'     => 'green',
            'secondary'   => 'emerald',
            'surface'     => 'neutral',
            'shift'       => ['primary' => 2, 'secondary' => 2],
            'dark_shift'  => ['primary' => -1, 'secondary' => -1],
        ],
        'youtube' => [
            'name'        => 'YouTube',
            'category'    => 'Brands',
            'description' => "Inspired by YouTube's bold red branding with clean, content-focused design.",
            'primary'     => 'red',
            'secondary'   => 'sky',
            'surface'     => 'neutral',
            'shift'       => ['primary' => 1, 'secondary' => 2],
            'dark_shift'  => ['secondary' => -1],
        ],
        'slack' => [
            'name'        => 'Slack',
            'category'    => 'Brands',
            'description' => "Inspired by Slack's friendly purple palette with warm, collaborative energy.",
            'primary'     => 'purple',
            'secondary'   => 'teal',
            'surface'     => 'slate',
            'shift'       => ['primary' => 1, 'secondary' => 2],
            'dark_shift'  => ['primary' => -1, 'secondary' => -1],
        ],
        'discord' => [
            'name'        => 'Discord',
            'category'    => 'Brands',
            'description' => "Inspired by Discord's signature blurple with deep dark mode backgrounds.",
            'primary'     => 'indigo',
            'secondary'   => 'violet',
            'surface'     => 'gray',
            'shift'       => ['secondary' => 1],
            'dark_shift'  => ['primary' => -1, 'secondary' => -1],
        ],
        'github' => [
            'name'        => 'GitHub',
            'category'    => 'Brands',
            'description' => "Inspired by GitHub's developer-focused design. Clean, minimal, and functional.",
            'primary'     => 'blue',
            'secondary'   => 'green',
            'surface'     => 'gray',
            'shift'       => ['primary' => 1, 'secondary' => 2],
            'dark_shift'  => ['primary' => -1, 'secondary' => -1],
        ],
        'stripe' => [
            'name'        => 'Stripe',
            'category'    => 'Brands',
            'description' => "Inspired by Stripe's polished violet-indigo palette. Premium and trustworthy.",
            'primary'     => 'violet',
            'secondary'   => 'indigo',
            'surface'     => 'slate',
            'shift'       => ['primary' => 1],
            'dark_shift'  => ['primary' => -1, 'secondary' => -1],
        ],
        'twitch' => [
            'name'        => 'Twitch',
            'category'    => 'Brands',
            'description' => "Inspired by Twitch's vibrant purple with high-energy dark mode gaming aesthetic.",
            'primary'     => 'violet',
            'secondary'   => 'purple',
            'surface'     => 'zinc',
            'shift'       => ['primary' => 1, 'secondary' => 1],
            'dark_shift'  => ['primary' => -1, 'secondary' => -1],
        ],
        'netflix' => [
            'name'        => 'Netflix',
            'category'    => 'Brands',
            'description' => "Inspired by Netflix's dramatic red-on-black design. Bold and cinematic.",
            'primary'     => 'red',
            'secondary'   => 'rose',
            'surface'     => 'neutral',
            'shift'       => ['primary' => 1, 'secondary' => 1],
            'dark_shift'  => ['secondary' => -1],
        ],
        'linear' => [
            'name'        => 'Linear',
            'category'    => 'Brands',
            'description' => "Inspired by Linear's sleek product design. Violet accents on minimal dark surfaces.",
            'primary'     => 'violet',
            'secondary'   => 'blue',
            'surface'     => 'slate',
            'shift'       => ['primary' => 1, 'secondary' => 1],
            'dark_shift'  => ['primary' => -1, 'secondary' => -1],
        ],
    ],
];
