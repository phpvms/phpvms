<?php

declare(strict_types=1);

namespace App\Services;

use App\Addons\AddonRegistry;
use App\Addons\Compat\Module;
use App\Contracts\Service;

class ModuleService extends Service
{
    public function __construct(
        private readonly AddonRegistry $addonRegistry
    ) {}

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
     * Update module with the status passed by user
     * TODO: Remove
     */
    public function updateModule(string $name, bool $enabled): void
    {
        /** @var ?Module $module */
        $module = $this->addonRegistry->find($name);

        if (!$module) {
            return;
        }

        // setActive() flips the enabled flag, persists to DB, and regenerates the boot cache
        // (via ModuleShim::setActive() → AddonRuntimeService::run()). The per-module migrate command
        // (module:migrate) belonged to nwidart and no longer exists. Addon migration execution
        // is owned by the standard `php artisan migrate` path (Phase 5 lifecycle).
        $module->setActive($enabled);

        if (file_exists(base_path('bootstrap/cache/modules.php'))) {
            unlink(base_path('bootstrap/cache/modules.php'));
        }
    }

    /**
     * Delete Module from the Storage & Database.
     */
    public function deleteModule(string $name): void
    {
        /** @var ?Module $module */
        $module = $this->addonRegistry->find($name);

        if (!$module) {
            return;
        }

        $module->delete();

        if (file_exists(base_path('bootstrap/cache/modules.php'))) {
            unlink(base_path('bootstrap/cache/modules.php'));
        }
    }
}
