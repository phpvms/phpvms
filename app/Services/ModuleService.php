<?php

declare(strict_types=1);

namespace App\Services;

use App\Addons\AddonRegistry;
use App\Contracts\Service;

class ModuleService extends Service
{
    /**
     * Module-registered admin nav links. Populated once per worker via each
     * module's boot()->registerLinks() call; not a per-request accumulator.
     */
    protected array $adminLinks = [];

    /**
     * @var array 0 == logged out, 1 == logged in
     */
    protected array $frontendLinks = [
        0 => [],
        1 => [],
    ];

    /**
     * Add a module link in the frontend
     */
    public function addFrontendLink(string $title, string $url, string $icon = 'bi bi-people', bool $logged_in = true): void
    {
        $this->frontendLinks[$logged_in][] = [
            'title' => $title,
            'url'   => $url,
            'icon'  => $icon,
        ];
    }

    /**
     * Get all of the frontend links
     */
    public function getFrontendLinks(mixed $logged_in): array
    {
        return $this->frontendLinks[$logged_in];
    }

    /**
     * Add a module link in the admin panel
     */
    public function addAdminLink(string $title, string $url, string $icon = 'bi bi-people'): void
    {
        $this->adminLinks[] = [
            'title' => $title,
            'url'   => $url,
            'icon'  => $icon,
        ];
    }

    /**
     * Get all of the module links in the admin panel
     */
    public function getAdminLinks(): array
    {
        return $this->adminLinks;
    }

    /**
     * Update module with the status passed by user.
     *
     * @deprecated Delegate to AddonRegistry::enable()/disable() directly.
     */
    public function updateModule(string $name, bool $enabled): void
    {
        if ($enabled) {
            app(AddonRegistry::class)->enable($name);
        } else {
            app(AddonRegistry::class)->disable($name);
        }
    }

    /**
     * Delete Module from the Storage & Database.
     *
     * @deprecated Delegate to AddonRegistry::delete() directly.
     */
    public function deleteModule(string $name): void
    {
        app(AddonRegistry::class)->delete($name);
    }
}
