<?php

declare(strict_types=1);

namespace App\Services;

use App\Addons\AddonRegistry;
use App\Contracts\Award;
use App\Contracts\Service;
use App\Support\ClassLoader;

class AwardService extends Service
{
    /**
     * Find any of the award classes
     *
     * @return Award[]
     */
    public function findAllAwardClasses(): array
    {
        $awards = [];
        $formatted_awards = [];

        // Find the awards in the modules/Awards directory
        //        $classes = ClassLoader::getClassesInPath(module_path('Awards'));
        //        $awards = array_merge($awards, $classes);

        // Look throughout all the other modules, in the module/{MODULE}/Awards directory
        foreach (app(AddonRegistry::class)->all() as $module) {
            $path = $module->getExtraPath('Awards');
            $classes = ClassLoader::getClassesInPath($path);

            foreach ($classes as $class) {
                $awards[] = $class;
            }
        }

        foreach ($awards as $award) {
            $formatted_awards[$award::class] = $award;
        }

        return $formatted_awards;
    }
}
