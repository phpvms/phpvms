<?php

declare(strict_types=1);

namespace App\Addons\Support;

use App\Contracts\Modules\PanelProvider;
use Illuminate\Contracts\Foundation\Application;
use Override;

/**
 * Builds the module panel defaults (id, `admin/{key}` path, console chrome, the
 * addon settings page and discovery of `Filament/{Resources,Pages,Widgets}`)
 * for an addon that declares a panel through `registerAdminFilamentPanel()`.
 *
 * It keeps the chrome in {@see PanelProvider}, and binds the module key and
 * paths to the addon instead of to a panel provider's file location. It is
 * never registered as a provider.
 */
final class AdminPanelBuilder extends PanelProvider
{
    /**
     * @param string $key        panel id, path segment and access-permission suffix
     * @param string $sourcePath directory the root namespace maps to
     */
    public function __construct(
        Application $app,
        private readonly string $key,
        private readonly string $sourcePath,
        private readonly string $rootNamespace,
    ) {
        parent::__construct($app);
    }

    #[Override]
    protected function moduleKey(): string
    {
        return $this->key;
    }

    #[Override]
    protected function moduleBasePath(): string
    {
        return $this->sourcePath;
    }

    #[Override]
    protected function moduleRootNamespace(): string
    {
        return $this->rootNamespace;
    }
}
