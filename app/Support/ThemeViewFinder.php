<?php

namespace App\Support;

use Igaster\LaravelTheme\Facades\Theme;
use Illuminate\Support\Arr;

class ThemeViewFinder extends \Igaster\LaravelTheme\themeViewFinder
{
    public $themeEngine;

    /*
     * Override findNamespacedView() to add "Theme/vendor/..." paths
     *
     * @param  string  $name
     *
     * @return string
     */
    #[\Override]
    protected function findNamespacedView($name): string
    {
        // Extract the $view and the $namespace parts
        [$namespace, $view] = $this->parseNamespaceSegments($name);

        $paths = $this->addThemeNamespacePaths($namespace);

        // Find and return the view
        return $this->findInPaths($view, $paths);
    }

    #[\Override]
    public function addThemeNamespacePaths($namespace)
    {
        // This rule will remap all paths starting with $key to $value.
        // For exapmle paths starting with 'resources/views/vendor' (relative to base_path())
        // will be maped to path 'THEMENAME/vendor' (relative to current theme views-path)
        $pathsMap = [
            // 'resources/views/vendor/mail' => 'mail',
            'resources/views/vendor'  => 'vendor',
            'resources/views/modules' => 'modules',
        ];

        // Does $namespace exists?
        if (!isset($this->hints[$namespace])) {
            return [];
        }

        // Get the paths registered to the $namespace
        $paths = $this->hints[$namespace];

        // Search $paths array and remap paths that start with a key of $pathsMap array.
        // replace with the value of $pathsMap array
        $themeSubPaths = [];
        foreach ($paths as $path) {
            $pathRelativeToApp = substr((string) $path, strlen(base_path()) + 1);
            $pathRelativeToApp = str_replace('\\', '/', $pathRelativeToApp);
            // Ignore paths in composer installed packages (paths inside vendor folder)
            if (!str_starts_with($pathRelativeToApp, 'vendor')) {
                // Remap paths definded int $pathsMap array
                foreach ($pathsMap as $key => $value) {
                    if (str_starts_with($pathRelativeToApp, $key)) {
                        $pathRelativeToApp = str_replace($key, $value, $pathRelativeToApp);
                        break;
                    }
                }

                $themeSubPaths[] = $pathRelativeToApp;
            }
        }

        // Prepend current theme's view path to the remaped paths
        $newPaths = [];
        $searchPaths = array_diff($this->paths, Theme::getLaravelViewPaths());
        foreach ($searchPaths as $path1) {
            foreach ($themeSubPaths as $path2) {
                $newPaths[] = $path1.'/'.$path2;
            }
        }

        // Add new paths in the beggin of the search paths array
        foreach (array_reverse($newPaths) as $path) {
            if (!in_array($path, $paths, true)) {
                $paths = Arr::prepend($paths, $path);
            }
        }

        return $paths;
    }
}
