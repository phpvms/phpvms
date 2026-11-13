<?php

declare(strict_types=1);

namespace App\Features\Addons\Support;

use Composer\ClassMapGenerator\ClassMapGenerator;
use Illuminate\Support\Facades\Log;

/**
 * Finds the classes an addon declares in a directory, without running its code.
 *
 * Composer's class map generator reads each file's tokens, so templates,
 * routes, config and helper files are never included. Only a class whose name
 * matches its path under the namespace is kept: the addon's PSR-4 prefix is
 * the only way core can load it in a fresh process.
 */
final class AddonClassScanner
{
    /**
     * @param  string             $directory    absolute directory to scan, at any depth
     * @param  string             $namespace    namespace of the directory, without a trailing backslash
     * @param  list<string>       $excludedDirs directory names, relative to $directory, that are not read
     * @param  string|null        $addon        addon identifier; when set, each class at a wrong path is logged as a warning
     * @return list<class-string>
     */
    public function scan(string $directory, string $namespace, array $excludedDirs = [], ?string $addon = null): array
    {
        $root = realpath($directory);
        $prefix = rtrim($namespace, '\\');

        if ($prefix === '' || $root === false || !is_dir($root)) {
            return [];
        }

        $generator = new ClassMapGenerator();
        $generator->scanPaths($root, null, 'psr-4', $prefix.'\\', $excludedDirs);

        if ($addon !== null) {
            foreach ($generator->getClassMap()->getRawPsrViolations() as $path => $violations) {
                foreach ($violations as $violation) {
                    Log::warning(sprintf(
                        'Addon "%s": class %s in %s does not match its autoload path, so it is not registered.',
                        $addon,
                        $violation['className'],
                        $path,
                    ));
                }
            }
        }

        $classes = array_values(array_filter(
            array_keys($generator->getClassMap()->getMap()),
            static fn (string $class): bool => str_starts_with($class, $prefix.'\\'),
        ));

        sort($classes);

        return $classes;
    }
}
