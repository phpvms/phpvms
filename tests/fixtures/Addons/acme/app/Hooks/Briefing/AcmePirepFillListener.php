<?php

declare(strict_types=1);

namespace PhpvmsAddonFixture\Hooks\Briefing;

use App\Contracts\Addons\Attributes\AddonListener;
use App\Features\Hooks\Fill;
use App\Features\Hooks\Listeners\RenderListener;
use App\Features\Hooks\Points\RenderingPirepDetail;

#[AddonListener]
class AcmePirepFillListener extends RenderListener
{
    public function render(RenderingPirepDetail $hook): void
    {
        $hook->add(Fill::html('<p data-acme-fill>Acme was here</p>'));
    }
}
