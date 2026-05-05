<?php

declare(strict_types=1);

namespace App\Providers;

use Nwidart\Modules\LaravelModulesServiceProvider as BaseModulesServiceProvider;

class ModulesServiceProvider extends BaseModulesServiceProvider
{
    #[\Override]
    public function register(): void
    {
        parent::register();

        // Boot the modules before resolving Filament so that modules' panels can be discovered
        $this->app->beforeResolving('filament', function (): void {
            parent::boot();
        });
    }
}
