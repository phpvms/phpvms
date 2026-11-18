<?php

declare(strict_types=1);

use App\Features\Themes\Support\ThemeViewFinder;
use Igaster\LaravelTheme\Facades\Theme;
use Illuminate\Support\Facades\View;

beforeEach(function (): void {
    Theme::set('seven');

    $finder = View::getFinder();
    if (!$finder instanceof ThemeViewFinder) {
        throw new RuntimeException('view.finder is '.$finder::class.', not '.ThemeViewFinder::class);
    }

    $this->finder = $finder;
    $this->themeRoot = array_values(array_diff($finder->getPaths(), Theme::getLaravelViewPaths()))[0];
    $this->view = 'phpvms-hint-'.str_replace('.', '', uniqid('', true));
});

// Hints are realpath()'d since laravel/framework#62005, so a symlinked hint can sit outside base_path().
it('resolves a namespaced view from a hint outside base_path, not the theme root', function (): void {
    $hint = realpath(sys_get_temp_dir());

    $decoy = $this->themeRoot.'/'.$this->view.'.blade.php';
    $own = $hint.'/'.$this->view.'.blade.php';
    file_put_contents($decoy, 'decoy');
    file_put_contents($own, 'own');

    try {
        View::addNamespace('outside', $hint);

        expect($this->finder->find('outside::'.$this->view))->toBe($own);
    } finally {
        unlink($decoy);
        unlink($own);
    }
});

it('remaps a resources/views/vendor hint to the theme vendor folder', function (): void {
    $package = $this->view;
    $overrideDir = $this->themeRoot.'/vendor/'.$package;
    $override = $overrideDir.'/page.blade.php';

    mkdir($overrideDir, 0o777, true);
    file_put_contents($override, 'override');

    try {
        View::addNamespace('remapped', base_path('resources/views/vendor/'.$package));

        expect($this->finder->find('remapped::page'))->toBe($override);
    } finally {
        unlink($override);
        rmdir($overrideDir);
    }
});
