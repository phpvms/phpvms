<?php

namespace App\Support\Modules;

use Illuminate\Config\Repository as Config;
use Illuminate\Container\Container;
use Nwidart\Modules\Contracts\ActivatorInterface;
use Nwidart\Modules\Module;

class DatabaseActivator implements ActivatorInterface
{
    /**
     * Laravel config instance
     *
     * @var Config
     */
    private $config;

    /**
     * Array of modules activation statuses
     *
     * @var array
     */
    private $modulesStatuses;

    public function __construct(Container $app)
    {
        $this->config = $app['config'];
        $this->modulesStatuses = $this->getModulesStatuses();
    }

    /**
     * Get modules statuses, from the database
     *
     * @return array
     */
    private function getModulesStatuses(): array
    {
        $modules = \App\Models\Module::all();
        $retVal = [];
        foreach ($modules as $i) {
            $retVal[$i->name] = $i->enabled;
        }
        return $retVal;
    }

    /**
     * {@inheritdoc}
     */
    public function reset(): void
    {
        (new \App\Models\Module())->truncate();
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
     * {@inheritdoc}
     */
    public function hasStatus(Module $module, bool $status): bool
    {
        if (!isset($this->modulesStatuses[$module->getName()])) {
            return $status === false;
        }

        return $this->modulesStatuses[$module->getName()] === $status;
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
    public function setActiveByName(string $name, bool $status): void
    {
        $this->modulesStatuses[$name] = $status;
        $this->writeDB($name, $status);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(Module $module): void
    {
        $module_name = $module->getName();
        if (!isset($this->modulesStatuses[$module_name])) {
            return;
        }
        unset($this->modulesStatuses[$module_name]);
        $this->writeDB($module_name, false, 1);
    }

    /**
     * Writes the activation statuses in a file, as json
     *
     * @param $name
     * @param $status
     * @param string $delete
     */
    private function writeDB($name, $status, $delete = ''): void
    {
        if (!empty($delete)) {
            (new \App\Models\Module())->where([
                'name' => $name,
            ])->delete();
        } else {
            (new \App\Models\Module())->where([
                'name' => $name,
            ])->update([
                'status' => $status,
            ]);
        }
    }
}
