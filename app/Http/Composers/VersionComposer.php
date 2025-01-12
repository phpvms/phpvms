<?php

namespace App\Http\Composers;

use App\Contracts\Composer;
use App\Services\VersionService;
use Illuminate\View\View;

class VersionComposer extends Composer
{
    public function __construct(
        private readonly VersionService $versionSvc
    ) {}

    public function compose(View $view)
    {
        $view->with('version', $this->versionSvc->getCurrentVersion(false));
        $view->with('version_full', $this->versionSvc->getCurrentVersion(true));
    }
}
