<?php

namespace App\Services\Installer;

use App\Contracts\Service;
use Exception;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Nwidart\Modules\Support\Stub;
use PDO;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

use function extension_loaded;
use function is_bool;

class ConfigService extends Service
{
    protected static array $defaultValues = [
        'APP_ENV'             => 'production',
        'APP_KEY'             => '',
        'APP_DEBUG'           => false,
        'APP_LOCALE'          => 'en',
        'DEBUG_TOOLBAR'       => false,
        'SITE_NAME'           => '',
        'APP_URL'             => 'http://phpvms.test',
        'DB_CONNECTION'       => '',
        'DB_HOST'             => '',
        'DB_PORT'             => 3306,
        'DB_DATABASE'         => '',
        'DB_USERNAME'         => '',
        'DB_PASSWORD'         => '',
        'DB_PREFIX'           => '',
        'DB_EMULATE_PREPARES' => false,
        'CACHE_DRIVER'        => 'array',
        'CACHE_PREFIX'        => '',
        'MAIL_DRIVER'         => 'log',
        'MAIL_HOST'           => '',
        'MAIL_PORT'           => 587,
        'MAIL_ENCRYPTION'     => '',
        'MAIL_USERNAME'       => '',
        'MAIL_PASSWORD'       => '',
        'MAIL_FROM_NAME'      => 'phpVMS Admin',
        'MAIL_FROM_ADDRESS'   => 'no-reply@phpvms.net',
    ];

    /**
     * Create the .env file. This is called by an initial install
     *
     *
     * @throws FileException
     */
    public function createConfigFiles($attrs): bool
    {
        $opts = array_merge(static::$defaultValues, $attrs);
        if (empty($opts['APP_KEY'])) {
            $opts['APP_KEY'] = $this->createAppKey();
        }

        $opts = $this->determinePdoOptions($opts);
        $opts = $this->configCacheDriver($opts);
        $opts = $this->configQueueDriver($opts);

        $this->writeConfigFiles($opts);

        return true;
    }

    /**
     * Rewrite the config files - this means mapping the values that are currently
     * loaded in the config and rewriting them into the env.php file, and then renaming
     * the config.php files to config.bak.php
     *
     * This is called from the migrations which removes the old config.php file
     */
    public function rewriteConfigFiles()
    {
        /*$cfg_file = App::environmentPath().'/config.php';
        if (!file_exists($cfg_file)) {
            Log::info('Main config.php file is missing, migration already completed');
            return;
        }*/

        $db_opts = config('database.connections.mysql.options');
        $emulate_prepares = $db_opts[PDO::ATTR_EMULATE_PREPARES] ? 'true' : 'false';

        $opts = array_merge(static::$defaultValues, [
            'APP_ENV'             => config('app.env'),
            'APP_KEY'             => config('app.key'),
            'APP_DEBUG'           => config('app.debug') ? 'true' : 'false',
            'APP_LOCALE'          => config('app.locale'),
            'DEBUG_TOOLBAR'       => config('app.debug_toolbar') ? 'true' : 'false',
            'SITE_NAME'           => config('app.name'),
            'APP_URL'             => config('app.url'),
            'DB_CONNECTION'       => config('database.default'),
            'DB_HOST'             => config('database.connections.mysql.host'),
            'DB_PORT'             => config('database.connections.mysql.port'),
            'DB_DATABASE'         => config('database.connections.mysql.database'),
            'DB_USERNAME'         => config('database.connections.mysql.username'),
            'DB_PASSWORD'         => config('database.connections.mysql.password'),
            'DB_PREFIX'           => config('database.connections.mysql.prefix'),
            'DB_EMULATE_PREPARES' => $emulate_prepares,
            'CACHE_DRIVER'        => config('cache.default'),
            'CACHE_PREFIX'        => config('cache.prefix'),
            'MAIL_DRIVER'         => config('mail.default'),
            'MAIL_HOST'           => config('mail.mailers.smtp.host'),
            'MAIL_PORT'           => config('mail.mailers.smtp.port'),
            'MAIL_ENCRYPTION'     => config('mail.mailers.smtp.encryption'),
            'MAIL_USERNAME'       => config('mail.mailers.smtp.username'),
            'MAIL_PASSWORD'       => config('mail.mailers.smtp.password'),
            'MAIL_FROM_NAME'      => config('mail.from.name'),
            'MAIL_FROM_ADDRESS'   => config('mail.from.address'),
        ]);

        $this->writeConfigFiles($opts);

        // Rename the old config file
        $cfg_file = App::environmentPath().'/config.php';
        if (file_exists($cfg_file)) {
            rename($cfg_file, App::environmentPath().'/config.bak.php');
        }
    }

    /**
     * Update the environment file and update certain keys/values
     *
     *
     * @return void
     */
    public function updateKeysInEnv(array $kvp)
    {
        $app = app();

        $env_file = file_get_contents($app->environmentFilePath());
        foreach ($kvp as $key => $value) {
            $key = strtoupper($key);

            // cast for any boolean values
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }

            // surround by quotes if there are any spaces in the value
            if (strpos($value, ' ') !== false) {
                $value = '"'.$value.'"';
            }

            Log::info('Replacing "'.$key.'" with '.$value);

            $env_file = preg_replace(
                '/^'.$key.'(.*)?/m',
                $key.'='.$value,
                $env_file
            );
        }

        file_put_contents($app->environmentFilePath(), $env_file);
    }

    /**
     * Generate a fresh new APP_KEY
     */
    protected function createAppKey(): string
    {
        return 'base64:'.base64_encode(Encrypter::generateKey(config('app.cipher')));
    }

    /**
     * Change a few options within the PDO driver, depending on the version
     * of mysql/maria, etc used. ATM, only make a change for MariaDB
     *
     *
     * @return mixed
     */
    protected function determinePdoOptions($opts)
    {
        if ($opts['DB_CONNECTION'] !== 'mysql') {
            return $opts;
        }

        $dsn = "mysql:host=$opts[DB_HOST];port=$opts[DB_PORT];";
        Log::info('Connection string: '.$dsn);

        $conn = new PDO($dsn, $opts['DB_USERNAME'], $opts['DB_PASSWORD']);
        $version = strtolower($conn->getAttribute(PDO::ATTR_SERVER_VERSION));
        Log::info('Detected DB Version: '.$version);

        // If it's mariadb, enable the emulation for prepared statements
        // seems to be throwing a problem on 000webhost
        // https://github.com/nabeelio/phpvms/issues/132
        /*if (strpos($version, 'mariadb') !== false) {
            Log::info('Detected MariaDB, setting DB_EMULATE_PREPARES to true');
            $opts['DB_EMULATE_PREPARES'] = true;
        }*/

        return $opts;
    }

    /**
     * Determine is APC is installed, if so, then use it as a cache driver
     *
     *
     * @return mixed
     */
    protected function configCacheDriver($opts)
    {
        // Set the cache prefix
        $prefix = substr(\Illuminate\Support\Str::slug($opts['SITE_NAME'], '_'), 0, 8);
        $opts['CACHE_PREFIX'] = strtolower(uniqid($prefix.'_'));

        // Figure out what cache driver to initially use, depending on
        // what is installed. It won't detect redis or anything, though
        foreach (config('installer.cache.drivers') as $ext => $driver) {
            if (extension_loaded($ext)) {
                Log::info('Detected extension "'.$ext.'", setting driver to "'.$driver.'"');
                $opts['CACHE_DRIVER'] = $driver;

                return $opts;
            }
        }

        Log::info('No extension detected, using file cache');
        $opts['CACHE_DRIVER'] = config('installer.cache.default');

        return $opts;
    }

    /**
     * Setup a queue driver that's not the default "sync"
     * driver, if a database is being used
     *
     *
     * @return mixed
     */
    protected function configQueueDriver($opts)
    {
        // If we're setting up a database, then also setup
        // the default queue driver to use the database
        if ($opts['DB_CONNECTION'] === 'mysql' || $opts['DB_CONNECTION'] === 'postgres') {
            $opts['QUEUE_DRIVER'] = 'database';
        } else {
            $opts['QUEUE_DRIVER'] = 'sync';
        }

        return $opts;
    }

    /**
     * Remove the config files
     */
    public function removeConfigFiles()
    {
        $env_file = App::environmentFilePath();
        $config_file = App::environmentPath().'/config.php';

        if (file_exists($env_file)) {
            try {
                unlink($env_file);
            } catch (Exception $e) {
                Log::error($e->getMessage());
            }
        }

        if (file_exists($config_file)) {
            try {
                unlink($config_file);
            } catch (Exception $e) {
                Log::error($e->getMessage());
            }
        }
    }

    /**
     * Get the template file name and write it out
     *
     *
     * @throws FileException
     */
    protected function writeConfigFiles($opts)
    {
        Stub::setBasePath(resource_path('/stubs/installer'));

        $env_file = App::environmentFilePath();
        if (file_exists($env_file) && !is_writable($env_file)) {
            Log::error('Permissions on existing .env is not writable');

            throw new FileException('Can\'t write to the .env file! Check the permissions');
        }

        /*
         * First write out the env file
         */
        try {
            $stub = new Stub('/env.stub', $opts);
            $stub->render();
            $stub->saveTo(App::environmentPath(), App::environmentFile());
        } catch (Exception $e) {
            throw new FileException('Couldn\'t write .env file. ('.$e.')');
        }

        /*
         * Next write out the config file. If there's an error here,
         * then throw an exception but delete the env file first
         */
        /*try {
            $stub = new Stub('/config.stub', $opts);
            $stub->render();
            $stub->saveTo(App::environmentPath(), 'config.php');
        } catch (Exception $e) {
            unlink(App::environmentPath().'/'.App::environmentFile());

            throw new FileException('Couldn\'t write config.php. ('.$e.')');
        }*/
    }
}
