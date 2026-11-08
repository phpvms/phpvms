<?php

declare(strict_types=1);

namespace App\Features\Themes;

use App\Models\ThemeSetting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Reads and writes the stored values of a theme's options (see {@see ThemeOptions}).
 *
 * Only overrides are stored, under the theme's own name; there is no global
 * (`*`) fallback. A value resolves to the theme's row cast to the row's recorded
 * type, else the field's default. Saving a value equal to the cast default
 * deletes the row so a theme update can still change the default; a blank value
 * that differs from it is stored.
 *
 * Octane: registered as a scoped binding, so an instance lives for one request
 * and holds only a per-request table-exists flag.
 */
final class ThemeFields
{
    private ?bool $tableExists = null;

    private bool $warned = false;

    public function __construct(private readonly ThemeOptions $options) {}

    /**
     * The stored value, cast to its recorded type, else the default.
     */
    public function get(string $theme, string $name): mixed
    {
        return $this->override($theme, $name) ?? $this->defaultValue($theme, $name);
    }

    /**
     * The stored value only, cast to its recorded type; null when there is no
     * row, or when the table is missing. Never the default.
     */
    public function override(string $theme, string $name): mixed
    {
        if (!$this->hasTable()) {
            return null;
        }

        $row = ThemeSetting::query()
            ->where('theme', $theme)
            ->where('name', $name)
            ->where('active', true)
            ->first();

        return $row?->typedContent();
    }

    /**
     * Stores $value as an override, or deletes the row when it equals the cast
     * default. Null deletes the row. A blank string that differs from the
     * default is stored.
     */
    public function set(string $theme, string $name, mixed $value): void
    {
        if (!$this->hasTable(warn: true)) {
            return;
        }

        if ($value === null || $this->equalsDefault($value, $this->defaultValue($theme, $name))) {
            $this->reset($theme, $name);

            return;
        }

        ThemeSetting::query()->updateOrCreate(
            ['theme' => $theme, 'name' => $name],
            [
                'type'    => ThemeSetting::typeOf($value),
                'content' => ThemeSetting::encode($value),
                'active'  => true,
            ],
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

        // A query-builder delete fires no model events, so bump here.
        ThemeOutput::bump();
    }

    /**
     * The default of a field: the registered field's default, else null.
     */
    private function defaultValue(string $theme, string $name): mixed
    {
        return $this->options->defaults($theme)[$name]['default'] ?? null;
    }

    private function equalsDefault(mixed $value, mixed $default): bool
    {
        return $default !== null
            && is_bool($value) === is_bool($default)
            && is_array($value) === is_array($default)
            && ThemeSetting::encode($value) === ThemeSetting::encode($default);
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
}
