<?php

use App\Bootstrap\LoadConfiguration;

if (!defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}

/**
 * Customized container to allow some of the base Laravel
 * configurations to be overridden
 */
class application extends Illuminate\Foundation\Application
{
    private $publicDirPath;
    private $publicUrlPath = '/';

    public function __construct(string $basePath = null)
    {
        $rootPath = dirname(__DIR__).'/';
        parent::__construct($rootPath);

        if (is_file($rootPath.'/env.php')) {
            exit('Please rename env.php to .env');
        }
        // if the sample module is still there, then we need to rename it
        if (is_dir($rootPath.'/modules/sample-module')) {
            rename($rootPath.'/modules/sample-module', $rootPath.'/modules/Sample');
            // clear the bootstrap and storage cache
            $cachePaths = [
                $rootPath.'/bootstrap/cache',
                $rootPath.'/storage/framework/cache'
            ];

            foreach ($cachePaths as $path) {
                $files = glob($path.'/*');
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    } elseif (is_dir($file)) {
                        array_map('unlink', glob("$file/*.*"));
                        // remove directories recursively
                        $this->deleteDirectory($file);
                    }
                }
            }
            exit('The sample module has been renamed to "Sample". Please refresh this page.');
        }

        $this->useDatabasePath($this->basePath.'/app/Database');
        $this->useStoragePath($this->basePath.'/storage');
    }

    /**
     * Override this method so we can inject our own LoadConfiguration
     * class, which looks for any configurations that have been overridden
     * in the root's config.php file
     *
     * @param array $bootstrappers
     */
    public function bootstrapWith(array $bootstrappers)
    {
        $find = \Illuminate\Foundation\Bootstrap\LoadConfiguration::class;
        $replace = LoadConfiguration::class;

        $bootstrappers = array_replace(
            $bootstrappers,
            array_fill_keys(
                array_keys($bootstrappers, $find, true),
                $replace
            )
        );

        parent::bootstrapWith($bootstrappers);
    }

    public function bindInterfaces()
    {
        $this->singleton(
            Illuminate\Contracts\Http\Kernel::class,
            App\Http\Kernel::class
        );

        $this->singleton(
            Illuminate\Contracts\Console\Kernel::class,
            App\Console\Kernel::class
        );

        $this->singleton(
            Illuminate\Contracts\Debug\ExceptionHandler::class,
            App\Exceptions\Handler::class
        );
    }

    /**
     * Override paths
     *
     * @param mixed $publicDirPath
     */
    public function setPublicPath($publicDirPath)
    {
        $this->publicDirPath = $publicDirPath;
        app()->usePublicPath($publicDirPath);
    }

    /**
     * Added for the custom filesystem driver. Used in the index.php
     * in the root of the install to set it to point to /public,
     * instead of just /
     *
     * @param $publicUrlPath
     */
    public function setPublicUrlPath($publicUrlPath)
    {
        $this->publicUrlPath = $publicUrlPath;
    }

    /**
     * Added for the custom filesystem driver lookup on what to use
     * for the base URL
     *
     * @return string
     */
    public function publicUrlPath()
    {
        return $this->publicUrlPath ?: '/';
    }

    public function configPath($path = '')
    {
        return $this->basePath.DS.'config'.($path ? DS.$path : $path);
    }

    public function langPath($path = '')
    {
        return $this->resourcePath().DS.'lang';
    }

    public function publicPath($path = '')
    {
        return $this->joinPaths($this->publicDirPath ?: $this->basePath.DS.'public', $path);
    }

    public function resourcePath($path = '')
    {
        return $this->basePath.DS.'resources'.($path ? DS.$path : $path);
    }

    private function deleteDirectory($dir)
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getRealPath());
            } else {
                unlink($item->getRealPath());
            }
        }

        rmdir($dir);
    }
}
