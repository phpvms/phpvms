<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The 7.x $moduleSvc link API, shared with views so 7.x themes still render their menus.
 *
 * @deprecated add menu entries as navigation items; add-on admin pages go in the add-on's own panel
 */
final class LegacyModuleLinks
{
    /** @var array<int, list<array{title: string, url: string, icon: string}>> */
    private array $frontendLinks = [0 => [], 1 => []];

    public function addFrontendLink(string $title, string $url, string $icon = '', bool $logged_in = true): void
    {
        $this->frontendLinks[(int) $logged_in][] = ['title' => $title, 'url' => $url, 'icon' => $icon];
    }

    /**
     * @return list<array{title: string, url: string, icon: string}>
     */
    public function getFrontendLinks(mixed $logged_in): array
    {
        return $this->frontendLinks[(int) (bool) $logged_in];
    }

    // The 7.x admin sidebar is gone; each add-on has its own panel now.
    public function addAdminLink(string $title, string $url, string $icon = ''): void {}
}
