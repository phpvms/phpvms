<?php

declare(strict_types=1);

namespace App\Services\Installer;

use App\Contracts\Service;
use App\Models\Setting;
use App\Services\DatabaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Yaml\Yaml;

use function trim;

class SeederService extends Service
{
    private array $counters = [];

    private array $offsets = [];

    public function __construct(
        private readonly DatabaseService $databaseSvc
    ) {}

    /**
     * See if there are any seeds that are out of sync
     */
    public function seedsPending(): bool
    {
        return $this->settingsSeedsPending();
    }

    /**
     * Syncronize all the seed files, run this after the migrations
     * and on first install.
     *
     * @throws \Exception
     */
    public function syncAllSeeds(): void
    {
        $this->syncAllSettings();

        // Seed base
        $this->databaseSvc->seedFromYamlFile(database_path('seeders/base/base.yml'));
    }

    public function syncAllSettings(): void
    {
        $data = file_get_contents(database_path('/seeders/base/settings.yml'));
        $yml = Yaml::parse($data);
        foreach ($yml as $setting) {
            if (trim((string) $setting['key']) === '') {
                continue;
            }

            $this->addSetting($setting['key'], $setting);
        }
    }

    public function addSetting($key, $attrs): void
    {
        $id = Setting::formatKey($key);
        $group = $attrs['group'];
        $order = $this->getNextOrderNumber($group);

        $attrs = array_merge(
            [
                'id'          => $id,
                'key'         => $key,
                'offset'      => $this->offsets[$group],
                'order'       => $order,
                'name'        => '',
                'group'       => $group,
                'value'       => $attrs['value'],
                'default'     => $attrs['value'],
                'options'     => '',
                'type'        => 'hidden',
                'description' => '',
            ],
            $attrs
        );

        $count = DB::table('settings')->where('id', $id)->count('id');
        if ($count === 0) {
            DB::table('settings')->insert($attrs);
        } else {
            unset($attrs['value']);  // Don't overwrite this
            DB::table('settings')
                ->where('id', $id)
                ->update($attrs);
        }
    }

    /**
     * Dynamically figure out the offset and the start number for a group.
     * This way we don't need to mess with how to order things
     * When calling getNextOrderNumber(users) 31, will be returned, then 32, and so on
     */
    private function addCounterGroup(string $name, ?int $offset = null, int $start_offset = 0): void
    {
        if ($offset === null) {
            $group = DB::table('settings')
                ->where('group', $name)
                ->first();

            if ($group === null) {
                $offset = DB::table('settings')->max('offset');
                if ($offset === null) {
                    $offset = 0;
                    $start_offset = 1;
                } else {
                    $offset = (int) $offset;
                    $offset += 100;
                    $start_offset = $offset + 1;
                }
            } else {
                // Now find the number to start from
                $start_offset = DB::table('settings')->where('group', $name)->max('order');
                if ($start_offset === null) {
                    $start_offset = $offset + 1;
                } else {
                    $start_offset = (int) $start_offset;
                    $start_offset++;
                }

                $offset = $group->offset;
            }
        }

        $this->counters[$name] = $start_offset;
        $this->offsets[$name] = $offset;
    }

    /**
     * Get the next increment number from a group
     */
    private function getNextOrderNumber($group): int
    {
        if (!\in_array($group, $this->counters, true)) {
            $this->addCounterGroup($group);
        }

        $idx = $this->counters[$group];
        $this->counters[$group]++;

        return $idx;
    }

    /**
     * See if there are seeds pending for the settings
     */
    private function settingsSeedsPending(): bool
    {
        $all_settings = DB::table('settings')->get();
        $data = file_get_contents(database_path('/seeders/settings.yml'));
        $yml = Yaml::parse($data);

        // See if any are missing from the DB
        foreach ($yml as $setting) {
            if (trim((string) $setting['key']) === '') {
                continue;
            }

            $id = Setting::formatKey($setting['key']);
            $row = $all_settings->firstWhere('id', $id);

            // Doesn't exist in the table, quit early and say there is stuff pending
            if (!$row) {
                Log::info('Setting '.$id.' missing, update available');

                return true;
            }

            // See if any of these column values have changed
            foreach (['name', 'description'] as $column) {
                $currVal = $row->{$column};
                $newVal = $setting[$column];
                if ($currVal !== $newVal) {
                    return true;
                }
            }

            // See if any of the options have changed
            if ($row->type === 'select' && (!empty($row->options) && $row->options !== $setting['options'])) {
                Log::info('Options for '.$id.' changed, update available');

                return true;
            }
        }

        return false;
    }
}
