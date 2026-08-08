<?php

declare(strict_types=1);

use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Actions\View\ActionsIconAlias;
use Filament\Support\Facades\FilamentIcon;
use Filament\Support\View\SupportIconAlias;
use Illuminate\Support\Facades\File;

/**
 * The admin renders Tabler icons only. The tabler-icons package maps every
 * Filament icon alias through FilamentIcon::register(), which replays onto
 * each request-scoped IconManager via an afterResolving hook — so vendor
 * defaults resolve to Tabler even after Octane's scope flush. What that
 * cannot cover is icons hardcoded in our own code, hence the scan below.
 */
it('resolves filament icon aliases to tabler icons', function (): void {
    expect(FilamentIcon::resolve(ActionsIconAlias::DELETE_ACTION))->toBe(TablerIcon::Trash)
        ->and(FilamentIcon::resolve(SupportIconAlias::MODAL_CLOSE_BUTTON))->toBe(TablerIcon::X);
});

it('keeps admin code free of hardcoded heroicons', function (): void {
    $paths = [
        app_path('Filament'),
        app_path('Providers/Filament'),
        resource_path('views/filament'),
    ];

    $offenders = [];
    foreach ($paths as $path) {
        foreach (File::allFiles($path) as $file) {
            if (preg_match('/heroicon-|Heroicon::/', $file->getContents())) {
                $offenders[] = $file->getRelativePathname();
            }
        }
    }

    expect($offenders)->toBeEmpty();
});
