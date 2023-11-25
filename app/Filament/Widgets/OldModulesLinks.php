<?php

namespace App\Filament\Widgets;

use App\Services\ModuleService;
use Filament\Widgets\Widget;

class OldModulesLinks extends Widget
{
    protected static string $view = 'filament.widgets.old_modules_links';

    protected static ?int $sort = 3;

    public ?array $adminLinks = null;

    public function mount()
    {
        $this->adminLinks = array_filter(app(ModuleService::class)->getAdminLinks(), static fn (array $link): bool => !str_contains($link['title'], 'Sample'));
    }
}
