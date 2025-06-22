<?php

namespace App\Support\Modules;

use Exception;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Nwidart\Modules\Contracts\ActivatorInterface;
use Nwidart\Modules\Module;

class DatabaseActivator implements ActivatorInterface
{
    /**
     * The scanned paths.
     *
     * @var array
     */
    protected $paths = [];

    /**
     * @param string|null $path
     */
    public function __construct(
        Container $app,
        /**
         * The module path.
         */
        protected $path = null
    ) {}

    public function getModuleByName(string $name): ?\App\Models\Module
    {
        try {
            if (app()->environment('production')) {
                $cache = config('cache.keys.MODULES');

                return Cache::remember($cache['key'].'.'.$name, $cache['time'], fn () => \App\Models\Module::where(['name' => $name])->first());
            }

            return \App\Models\Module::where(['name' => $name])->first();
        } catch (Exception) { // Catch any database/connection errors
            return null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function reset(): void
    {
        (new \App\Models\Module())->truncate();
    }

    /**
     * {@inheritdoc}
     */
    public function enable(Module $module): void
    {
        $this->setActive($module, true);
    }

    /**
     * {@inheritdoc}
     */
    public function disable(Module $module): void
    {
        $this->setActive($module, false);
    }

    /**
     * \Nwidart\Modules\Module instance passed
     * {@inheritdoc}
     */
    public function hasStatus(Module $module, bool $status): bool
    {
        $module = $this->getModuleByName($module->getName());
        if (!$module instanceof \App\Models\Module) {
            return false;
        }

        return $module->enabled;
    }

    /**
     * {@inheritdoc}
     */
    public function setActive(Module $module, bool $active): void
    {
        $module = $this->getModuleByName($module->getName());
        if (!$module instanceof \App\Models\Module) {
            $module = \App\Models\Module::create([
                'name' => $module->name,
            ]);
        }

        $module->enabled = $active;
        $module->save();
    }

    /**
     * {@inheritdoc}
     */
    public function setActiveByName(string $name, bool $status): void
    {
        $module = $this->getModuleByName($name);
        if (!$module instanceof \App\Models\Module) {
            return;
        }

        $module->enabled = $status;
        $module->save();
    }

    /**
     * {@inheritdoc}
     */
    public function delete(Module $module): void
    {
        $name = $module->getName();

        try {
            (new \App\Models\Module())->where([
                'name' => $name,
            ])->delete();
        } catch (Exception $e) {
            Log::error('Module '.$module.' Delete failed! Exception : '.$e->getMessage());

            return;
        }
    }
}
