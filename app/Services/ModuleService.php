<?php

declare(strict_types=1);

namespace App\Services;

use App\Addons\AddonRegistry;
use App\Contracts\Service;
use Deprecated;

class ModuleService extends Service
{
    /**
     * Update module with the status passed by user.
     */
    #[Deprecated(message: 'Delegate to AddonRegistry::enable()/disable() directly.')]
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
     */
    #[Deprecated(message: 'Delegate to AddonRegistry::delete() directly.')]
    public function deleteModule(string $name): void
    {
        app(AddonRegistry::class)->delete($name);
    }
}
