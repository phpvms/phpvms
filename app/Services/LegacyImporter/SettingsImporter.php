<?php

declare(strict_types=1);

namespace App\Services\LegacyImporter;

use App\Services\SettingService;

class SettingsImporter extends BaseImporter
{
    protected $table = 'settings';

    public function run($start = 0)
    {
        $this->comment('--- SETTINGS IMPORT ---');

        /** @var SettingService $settingService */
        $settingService = app(SettingService::class);

        $count = 0;
        $rows = $this->db->readRows($this->table, $this->idField, $start);
        foreach ($rows as $row) {
            if ($row->name === 'ADMIN_EMAIL') {
                $settingService->store('general.admin_email', $row->value);
                $count++;
            }
        }

        $this->info('Imported '.$count.' settings');
    }
}
