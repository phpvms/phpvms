<?php

declare(strict_types=1);

use Igaster\LaravelTheme\Facades\Theme;
use Illuminate\Support\Facades\View;
use Illuminate\View\FileViewFinder;

// Hints are realpath()'d since laravel/framework#62005, so a symlinked hint can sit outside base_path().
it('resolves a namespaced view from a hint outside base_path, not the theme root', function (): void {
    Theme::set('seven');

    $finder = View::getFinder();
    assert($finder instanceof FileViewFinder);
    $themeRoot = array_values(array_diff($finder->getPaths(), Theme::getLaravelViewPaths()))[0];
    $hint = realpath(sys_get_temp_dir());
    $view = 'phpvms-hint-'.str_replace('.', '', uniqid('', true));

    $decoy = $themeRoot.'/'.$view.'.blade.php';
    $own = $hint.'/'.$view.'.blade.php';
    file_put_contents($decoy, 'decoy');
    file_put_contents($own, 'own');

    try {
        View::addNamespace('outside', $hint);

        expect($finder->find('outside::'.$view))->toBe($own);
    } finally {
        unlink($decoy);
        unlink($own);
    }
});
