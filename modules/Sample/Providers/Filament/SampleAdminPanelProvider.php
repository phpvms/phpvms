<?php

declare(strict_types=1);

namespace Modules\Sample\Providers\Filament;

use App\Contracts\Modules\PanelProvider;

/**
 * Filament panel for the Sample module.
 *
 * Everything (id, path, middleware, auth, branding, theme, panel switcher, and
 * discovery of this module's Filament/{Resources,Pages,Widgets}) is supplied by
 * the base contract; the module only declares its key.
 *
 * The panel is served at /admin/sample and gated via access:sample.
 */
class SampleAdminPanelProvider extends PanelProvider
{
    protected function moduleKey(): string
    {
        return 'sample';
    }
}
