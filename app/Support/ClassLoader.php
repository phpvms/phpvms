<?php

namespace App\Support;

use Composer\Autoload\ClassMapGenerator;
use Log;

/**
 * Class find/load related functionality. Is used to find
 * the award classes right now that might be in a module
 */
class ClassLoader
{
    public static function getClassesInPath($path): array
    {
        if (!file_exists($path)) {
            return [];
        }

        $classes = [];
        $all_classes = array_keys(ClassMapGenerator::createMap($path));
        foreach ($all_classes as $cl) {
            try {
                $klass = new $cl();
            } catch (\Exception $e) {
                Log::error('Error loading class: '.$e->getMessage());

                continue;
            }

            $classes[] = $klass;
        }

        return $classes;
    }
}
