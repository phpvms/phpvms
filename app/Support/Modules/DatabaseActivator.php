<?php

namespace App\Support\Modules;

use Exception;
use Illuminate\Config\Repository as Config;
use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Nwidart\Modules\Contracts\ActivatorInterface;
use Nwidart\Modules\Module;

class DatabaseActivator implements ActivatorInterface
{
    /**
     * Laravel Filesystem instance
     *
     * @var Filesystem
     */
    private Filesystem $files;

    /**
     * Laravel config instance
     *
     * @var Config
     */
    private Config $config;

    /**
     * Array of modules activation statuses
     *
     * @var array
     */
    private array $modulesStatuses;

    public function __construct(Container $app)
    {
        $this->config = $app['config'];
        $this->files = $app['files'];
        $this->modulesStatuses = $this->getModulesStatuses();
    }

    /**
     * Get modules statuses, from the database
     */
    private function getModulesStatuses(): array
    {
        try {
            if (app()->environment('production')) {
                $cache = config('cache.keys.MODULES');
                $modules = Cache::remember($cache['key'], $cache['time'], function () {
                    return \App\Models\Module::select('name', 'enabled')->get()->mapWithKeys(function ($item) {
                        return [$item->name => $item->enabled];
                    });
                });
            } else {
                $modules = \App\Models\Module::select('name', 'enabled')->get()->mapWithKeys(function ($item) {
                    return [$item->name => $item->enabled];
                });
            }

            return $modules->toArray();
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function reset(): void
    {
        DB::table('modules')->truncate();
        $this->modulesStatuses = [];
    }

    /**
     * {@inheritdoc}
     */
    public function enable(Module $module): void
    {
        $this->setActiveByName($module->getName(), true);
    }

    /**
     * {@inheritdoc}
     */
    public function disable(Module $module): void
    {
        $this->setActiveByName($module->getName(), false);
    }

    /**
     * {@inheritDoc}
     */
    public function hasStatus(Module|string $module, bool $status): bool
    {
        $name = $module instanceof Module ? $module->getName() : $module;

        if (! isset($this->modulesStatuses[$name])) {
            return $status === false;
        }

        return $this->modulesStatuses[$name] === $status;
    }

    /**
     * {@inheritdoc}
     */
    public function setActive(Module $module, bool $active): void
    {
        $this->setActiveByName($module->getName(), $active);
    }

    /**
     * {@inheritdoc}
     */
    public function setActiveByName(string $name, bool $active): void
    {
        $this->modulesStatuses[$name] = $active;

        \App\Models\Module::updateOrCreate([
            'name' => $name,
        ], [
            'enabled' => $active,
        ]);

        // Update the cache accordingly if in production
        if (app()->environment('production')) {
            $cache = config('cache.keys.MODULES');
            Cache::forget($cache['key']);
            Cache::remember($cache['key'], $cache['time'], function () {
                return \App\Models\Module::select('name', 'enabled')->get()->mapWithKeys(function ($item) {
                    return [$item->name => $item->enabled];
                });
            });
        }

    }

    /**
     * {@inheritdoc}
     */
    public function delete(Module $module): void
    {
        $name = $module->getName();

        if (! isset($this->modulesStatuses[$module->getName()])) {
            return;
        }
        unset($this->modulesStatuses[$module->getName()]);

        try {
            \App\Models\Module::where([
                'name' => $name,
            ])->delete();
        } catch (Exception $e) {
            Log::error('Module '.$module.' Delete failed! Exception : '.$e->getMessage());

            return;
        }
    }
}
