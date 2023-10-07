<?php

namespace App\Services\LegacyImporter;

use App\Repositories\SettingRepository;

class SettingsImporter extends BaseImporter
{
    protected $table = 'settings';

    public function run($start = 0)
    {
        $this->comment('--- SETTINGS IMPORT ---');

        /** @var SettingRepository $settingsRepo */
        $settingsRepo = app(SettingRepository::class);

        $count = 0;
        $rows = $this->db->readRows($this->table, $this->idField, $start);
        foreach ($rows as $row) {
            switch ($row->name) {
                case 'ADMIN_EMAIL':
                    $settingsRepo->store('general.admin_email', $row->value);
                    break;
            }
        }

        $this->info('Imported '.$count.' settings');
    }
}
