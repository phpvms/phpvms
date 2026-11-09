<?php

declare(strict_types=1);

namespace PhpvmsAddonFixture\Listeners;

use App\Contracts\Addons\Attributes\AddonListener;
use App\Features\Hooks\Fill;
use App\Features\Hooks\Points\RenderingPirepDetail;

#[AddonListener]
class AcmePirepFillListener
{
    public function handle(RenderingPirepDetail $hook): void
    {
        $hook->add(Fill::html('<p data-acme-fill>Acme was here</p>'));
    }
}
