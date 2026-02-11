<?php

namespace App\Bootstrap;

use Exception;
use Illuminate\Contracts\Config\Repository as RepositoryContract;
use Illuminate\Contracts\Foundation\Application;

class LoadConfiguration extends \Illuminate\Foundation\Bootstrap\LoadConfiguration
{
    /**
     * Load the configuration items from all of the files. This reads the config.php from
     * that's sitting in the root, and then recursively merges it with the current configs
     *
     *
     * @throws Exception
     */
    protected function loadConfigurationFiles(Application $app, RepositoryContract $repository)
    {
        parent::loadConfigurationFiles($app, $repository);

        /*
         * Read in the base config, only if it exists
         */
        if (file_exists($app->basePath().'/config.php')) {
            $local_conf = include $app->basePath().'/config.php';
            foreach ($local_conf as $namespace => $override_config) {
                $config = $repository->get($namespace, []);
                $update_config = array_replace_recursive($config, $override_config);
                $repository->set($namespace, $update_config);
            }
        }
    }
}
