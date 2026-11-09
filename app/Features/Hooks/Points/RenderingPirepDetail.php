<?php

declare(strict_types=1);

namespace App\Features\Hooks\Points;

use App\Features\Hooks\RenderHook;
use App\Models\Pirep;

/**
 * Render hook for the PIREP detail page, full-width below the two-column
 * detail grid.
 */
final class RenderingPirepDetail extends RenderHook
{
    public function __construct(public readonly Pirep $pirep)
    {
        parent::__construct();
    }

    public static function name(): string
    {
        return 'pireps.detail.main';
    }
}
