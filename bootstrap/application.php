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

    public function __construct(?string $basePath = null)
    {
        $rootPath = dirname(__DIR__).'/';
        parent::__construct($rootPath);

        if (is_file($rootPath.'/env.php')) {
            exit('Please rename env.php to .env');
        }

        // $this->useDatabasePath($this->basePath.'/app/Database');
        $this->useStoragePath($this->basePath.'/storage');
    }

    /**
     * Override this method so we can inject our own LoadConfiguration
     * class, which looks for any configurations that have been overridden
     * in the root's config.php file
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
}
