<?php

declare(strict_types=1);

namespace App\Features\Themes;

use App\Http\Middleware\SetActiveTheme;
use App\Services\VersionService;
use BackedEnum;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Igaster\LaravelTheme\Exceptions\themeNotFound;
use Igaster\LaravelTheme\Facades\Theme;
use Illuminate\Support\Facades\Cache;

/**
 * The registry of the option forms that themes declare.
 *
 * A theme registers a closure that returns Filament schema components (see
 * {@see self::register()}). The closure is called when the form is needed, never
 * at boot: Filament components hold state and must not be shared across requests.
 *
 * Octane: bound as a singleton that holds closures only. Defaults are read from
 * the default cache store, keyed by the versions of the theme and its parents.
 */
final class ThemeOptions
{
    /**
     * @var array<string, Closure(string): array<int, Component>>
     */
    private array $registrations = [];

    /**
     * @param Closure(string): array<int, Component> $tabs Receives the name of the theme it is built for.
     */
    public function register(string $theme, Closure $tabs): void
    {
        $this->registrations[$theme] = $tabs;
    }

    /**
     * The components a theme registered, else those of its nearest ancestor
     * that registered; the closure is called with $theme, not the ancestor's
     * name. Empty when none of them registered.
     *
     * @return array<int, Component>
     */
    public function tabsFor(string $theme): array
    {
        foreach ($this->lineage($theme) as $name) {
            if (isset($this->registrations[$name])) {
                return ($this->registrations[$name])($theme);
            }
        }

        return [];
    }

    /**
     * Every field's default and emit tag, read off-request: `[name => [default, emit]]`.
     * Hidden fields are included, non-dehydrated ones are not, state casts are applied and a backed enum is
     * its value. `emit` is `css`, `head`, `footer` or null.
     *
     * @return array<string, array{default: mixed, emit: 'css'|'head'|'footer'|null}>
     */
    public function defaults(string $theme): array
    {
        if (!$this->hasRegistration($theme)) {
            return [];
        }

        return Cache::rememberForever(
            'theme-options:defaults:'.$theme.':'.$this->versions($theme),
            fn (): array => $this->buildDefaults($theme),
        );
    }

    /**
     * A select of the colour schemes. Choosing one sets the `$target` field's
     * state to the scheme applied over that field's default. The select is not
     * dehydrated, so it is never in {@see self::defaults()}, saved or emitted.
     */
    public static function colorSchemeSelect(string $target = 'style_css'): Select
    {
        return Select::make('color_scheme')
            ->label(__('filament.theme_color_scheme'))
            ->placeholder(__('filament.theme_color_scheme_placeholder'))
            ->helperText(__('filament.theme_color_scheme_hint'))
            ->options(fn (): array => app(ColorSchemeCss::class)->options())
            ->searchable()
            ->native(false)
            ->dehydrated(false)
            ->live()
            ->afterStateUpdated(function (?string $state, Get $get, Set $set) use ($target): void {
                $scheme = config('presets.color_schemes.'.($state ?? ''));

                if (!$scheme) {
                    return;
                }

                $default = app(self::class)->defaults((string) $get('theme'))[$target]['default'] ?? '';

                $set($target, app(ColorSchemeCss::class)->render((string) $default, $scheme));
            });
    }

    /**
     * The live page preview. The Theme page places it in the right-hand
     * column of its form; the frame follows the form's `data.theme` state and
     * the registered `css` fields.
     */
    public static function preview(): View
    {
        return View::make('filament.pages.theme.preview')
            ->viewData([
                'url'   => route('frontend.dashboard.index'),
                'param' => SetActiveTheme::PREVIEW_PARAM,
            ])
            ->columnSpan(['lg' => 2]);
    }

    private function hasRegistration(string $theme): bool
    {
        return array_any($this->lineage($theme), fn (string $name): bool => isset($this->registrations[$name]));
    }

    /**
     * @return array<string, array{default: mixed, emit: 'css'|'head'|'footer'|null}>
     */
    private function buildDefaults(string $theme): array
    {
        $schema = Schema::make(new ThemeOptionsHost())->components($this->tabsFor($theme));
        $defaults = [];

        foreach ($schema->getFlatFields(withHidden: true) as $key => $field) {
            // isDehydrated() is also false for a hidden field, which is kept;
            // the clone shows only an explicit dehydrated(false).
            if (!(clone $field)->dehydratedWhenHidden()->isDehydrated()) {
                continue;
            }

            $state = $field->getDefaultState();

            if ($state === '') {
                $state = null;
            }

            foreach ($field->getStateCasts() as $cast) {
                $state = $cast->get($state);
            }

            $emit = $field->getMeta('emit');

            $defaults[(string) $key] = [
                'default' => $this->normalise($state),
                'emit'    => in_array($emit, ['css', 'head', 'footer'], true) ? $emit : null,
            ];
        }

        return $defaults;
    }

    private function normalise(mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if (is_array($value)) {
            return array_map($this->normalise(...), $value);
        }

        return $value;
    }

    /**
     * The theme's name, then each parent's, child first.
     *
     * @return list<string>
     */
    private function lineage(string $theme): array
    {
        $names = [$theme];

        try {
            $parent = Theme::find($theme)->getParent();
        } catch (themeNotFound) {
            return $names;
        }

        while ($parent && !in_array($parent->name, $names, true)) {
            $names[] = $parent->name;
            $parent = $parent->getParent();
        }

        return $names;
    }

    /**
     * The versions of the theme and each parent, joined for a cache key.
     */
    public function versions(string $theme): string
    {
        return implode(',', array_map($this->versionOf(...), $this->lineage($theme)));
    }

    /**
     * A module theme's `module.json` version, else the phpvms version.
     */
    private function versionOf(string $theme): string
    {
        try {
            $viewsPath = Theme::find($theme)->getViewPaths()[0] ?? null;
        } catch (themeNotFound) {
            $viewsPath = null;
        }

        if (is_string($viewsPath)) {
            $manifest = dirname($viewsPath, 2).'/module.json';
            $version = is_file($manifest) ? (json_decode((string) file_get_contents($manifest), true)['version'] ?? null) : null;

            if (is_string($version) && $version !== '') {
                return $version;
            }
        }

        return app(VersionService::class)->getCurrentVersion();
    }
}
