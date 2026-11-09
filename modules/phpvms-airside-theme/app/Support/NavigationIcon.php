<?php

declare(strict_types=1);

namespace Modules\PhpvmsAirsideTheme\Support;

use BladeUI\Icons\Exceptions\SvgNotFound;

/**
 * Resolves an icon name to the Phosphor set the admin uses (light style), so
 * both halves of the app draw from one pack. Menu icons are stored as tabler
 * names for skylight, and the theme's own views still carry a few heroicon
 * names, so both are mapped; any other name is tried as a Phosphor name of
 * the same spelling, and an unknown one renders no icon.
 */
final class NavigationIcon
{
    public const string STYLE = 'light';

    /** @var array<string, string> */
    private const array MAP = [
        // tabler (menu items)
        'layout-dashboard' => 'squares-four',
        'plane'            => 'airplane-tilt',
        'checks'           => 'list-checks',
        'route'            => 'map-pin',
        'map'              => 'map-trifold',
        'notebook'         => 'notebook',
        'user'             => 'user',
        'users'            => 'users',
        'home'             => 'house',
        'file-text'        => 'file-text',
        'download'         => 'download-simple',
        'settings'         => 'gear',
        'info-circle'      => 'info',
        'book'             => 'book-open',
        'link'             => 'link',
        'trophy'           => 'trophy',
        'award'            => 'medal',
        // heroicons (theme views)
        'paper-airplane'  => 'airplane-tilt',
        'x-mark'          => 'x',
        'photo'           => 'image',
        'arrow-down-tray' => 'download-simple',
        'check-circle'    => 'check-circle',
        'check'           => 'check',
    ];

    /**
     * The Phosphor icon name (without set prefix or style), or null when the
     * name maps to no known icon.
     */
    public static function name(?string $name): ?string
    {
        if ($name === null || $name === '') {
            return null;
        }

        $bare = preg_replace('/^i-tabler-/', '', $name) ?? $name;
        $phosphor = self::MAP[$bare] ?? $bare;

        try {
            svg(self::component($phosphor));
        } catch (SvgNotFound) {
            return null;
        }

        return $phosphor;
    }

    /**
     * The name a TallStackUI `icon` attribute takes for a BladeUI pack
     * (`set.name.style`, dots), or null when unknown.
     */
    public static function tallstack(?string $name): ?string
    {
        $phosphor = self::name($name);

        return $phosphor === null ? null : 'phosphor.'.$phosphor.'.'.self::STYLE;
    }

    /**
     * The icon markup, or null when the name maps to no known icon.
     */
    public static function html(?string $name, string $class = ''): ?string
    {
        $phosphor = self::name($name);

        return $phosphor === null ? null : svg(self::component($phosphor), $class)->toHtml();
    }

    private static function component(string $phosphor): string
    {
        return 'phosphor-'.$phosphor.'-'.self::STYLE;
    }
}
