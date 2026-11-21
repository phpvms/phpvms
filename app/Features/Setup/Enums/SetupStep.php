<?php

declare(strict_types=1);

namespace App\Features\Setup\Enums;

/**
 * The steps that bring the database current, in run order.
 */
enum SetupStep: string
{
    case Preflight = 'preflight';
    case MaintenanceDown = 'maintenance_down';
    case Innodb = 'innodb';
    case CacheTables = 'cache_tables';
    case LinkAddons = 'link_addons';
    case StorageLink = 'storage_link';
    case Migrate = 'migrate';
    case Seed = 'seed';
    case PassportKeys = 'passport_keys';
    case DataMigrate = 'data_migrate';
    case DataTasks = 'data_tasks';
    case Marker = 'marker';
    case AddonCache = 'addon_cache';
    case Optimize = 'optimize';
    case OctaneReload = 'octane_reload';
    case MaintenanceUp = 'maintenance_up';

    public function label(): string
    {
        return __('installer.updater.step_labels.'.$this->value);
    }
}
