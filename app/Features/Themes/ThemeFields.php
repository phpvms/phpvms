<?php

declare(strict_types=1);

namespace App\Features\Themes;

use App\Models\ThemeSetting;
use Igaster\LaravelTheme\Exceptions\themeNotFound;
use Igaster\LaravelTheme\Facades\Theme;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Reads and writes the admin-editable fields of a theme (currently `css`).
 *
 * A value resolves through a fallback chain: the theme's own row, then the
 * global (`*`) row, then the default the theme ships under
 * `resources/customize/styles.css` (for `css`). Only overrides are stored; saving a value equal to
 * the shipped default deletes the row so a theme update can still change it.
 *
 * Octane: registered as a scoped binding, so an instance lives for one request
 * and holds only a per-request memo of the shipped defaults.
 */
final class ThemeFields
{
    /**
     * @var array<string, string>
     */
    private array $defaults = [];

    private ?bool $tableExists = null;

    private bool $warned = false;

    public function get(string $theme, string $name): string
    {
        if (!$this->hasTable()) {
            return $this->defaultFor($theme, $name);
        }

        foreach ([$theme, ThemeSetting::GLOBAL_THEME] as $owner) {
            $row = ThemeSetting::query()
                ->where('theme', $owner)
                ->where('name', $name)
                ->where('active', true)
                ->first();

            if ($row !== null) {
                return (string) $row->content;
            }
        }

        return $this->defaultFor($theme, $name);
    }

    /**
     * The stored override only (theme row, then `*` row); null when none, or
     * when the table is missing. Never the shipped default.
     */
    public function override(string $theme, string $name): ?string
    {
        if (!$this->hasTable()) {
            return null;
        }

        foreach ([$theme, ThemeSetting::GLOBAL_THEME] as $owner) {
            $row = ThemeSetting::query()
                ->where('theme', $owner)
                ->where('name', $name)
                ->where('active', true)
                ->first();

            if ($row !== null) {
                return (string) $row->content;
            }
        }

        return null;
    }

    public function set(string $theme, string $name, ?string $value): void
    {
        if (!$this->hasTable(warn: true)) {
            return;
        }

        if ($value === null || trim($value) === '' || $value === $this->defaultFor($theme, $name)) {
            $this->reset($theme, $name);

            return;
        }

        ThemeSetting::query()->updateOrCreate(
            ['theme' => $theme, 'name' => $name],
            ['content' => $value, 'active' => true],
        );
    }

    public function reset(string $theme, string $name): void
    {
        if (!$this->hasTable(warn: true)) {
            return;
        }

        ThemeSetting::query()
            ->where('theme', $theme)
            ->where('name', $name)
            ->delete();
    }

    /**
     * The shipped default: `resources/customize/styles.css` for `css`,
     * `resources/customize/{name}` otherwise, beside the theme's views.
     * Empty when the theme or the file is missing.
     */
    public function defaultFor(string $theme, string $name): string
    {
        $key = $theme."\0".$name;

        return $this->defaults[$key] ??= $this->readDefault($theme, $name);
    }

    /**
     * An install that has not run the `theme_settings` migration must still
     * render; writes are skipped with one warning per request.
     */
    private function hasTable(bool $warn = false): bool
    {
        $this->tableExists ??= Schema::hasTable('theme_settings');

        if (!$this->tableExists && $warn && !$this->warned) {
            $this->warned = true;
            Log::warning('The theme_settings table is missing; run `php artisan migrate` to store theme fields.');
        }

        return $this->tableExists;
    }

    private function readDefault(string $theme, string $name): string
    {
        try {
            $found = Theme::find($theme);
        } catch (themeNotFound) {
            return '';
        }

        $viewPaths = $found->getViewPaths();
        $viewsPath = $viewPaths[0] ?? null;

        if (!is_string($viewsPath)) {
            return '';
        }

        $file = $name === 'css' ? 'styles.css' : $name;
        $path = dirname($viewsPath).'/customize/'.$file;

        if (!is_file($path)) {
            return '';
        }

        return (string) file_get_contents($path);
    }
}
