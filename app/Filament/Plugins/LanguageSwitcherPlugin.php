<?php

declare(strict_types=1);

namespace App\Filament\Plugins;

use App\Http\Middleware\SetActiveLanguage;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\View\PanelsRenderHook;
use Illuminate\View\View;

final class LanguageSwitcherPlugin implements Plugin
{
    /**
     * Instantiate the plugin
     */
    public static function make(): self
    {
        return new self();
    }

    /**
     * The id of the plugin
     */
    public function getId(): string
    {
        return 'language-switcher';
    }

    /**
     * Register the plugin
     */
    public function register(Panel $panel): void
    {
        $panel->renderHook(
            PanelsRenderHook::USER_MENU_BEFORE,
            fn (): View => $this->getLanguageSwitcherView()
        );

        $panel->authMiddleware([
            SetActiveLanguage::class,
        ], true);
    }

    /**
     * Boot the plugin
     */
    public function boot(Panel $panel): void
    {
        //
    }

    /**
     * Returns a View object that renders the language switcher component.
     *
     * @return View The View object that renders the language switcher component.
     */
    private function getLanguageSwitcherView(): View
    {
        $locales = array_keys(config('languages'));

        $currentLocale = app()->getLocale();

        return view('filament.plugins.language-switcher', [
            'currentLocale' => $currentLocale,
            'locales'       => $locales,
            'getLabel'      => fn (string $locale): string => $this->getLabel($locale),
            'getFlag'       => fn (string $locale): string => $this->getFlag($locale),
        ]);
    }

    private function getLabel(string $locale): string
    {
        return config('languages')[$locale]['display'];
        // Resolve using php-intl
        /*return str(
            locale_get_display_name(
                locale: $locale
            )
        )
            ->title()
            ->toString();*/
    }

    private function getFlag(string $locale): string
    {
        return 'https://cdn.jsdelivr.net/gh/hampusborgos/country-flags@main/svg/'.(config('languages')[$locale]['flag-icon'] ?: null).'.svg';
    }
}
