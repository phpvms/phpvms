<?php

declare(strict_types=1);

namespace App\Features\Setup\Support;

use App\Features\Setup\Actions\ResolveSiteState;
use App\Features\Setup\Enums\SiteState;
use Illuminate\Http\Request;

/**
 * The site state, resolved once per request and shared by the middleware that need it.
 */
final readonly class RequestSiteState
{
    private const string ATTRIBUTE = 'phpvms.site_state';

    public function __construct(private ResolveSiteState $resolve) {}

    public function of(Request $request): SiteState
    {
        $state = $request->attributes->get(self::ATTRIBUTE);

        if (!$state instanceof SiteState) {
            $state = $this->resolve->handle();
            $request->attributes->set(self::ATTRIBUTE, $state);
        }

        return $state;
    }
}
