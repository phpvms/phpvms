<?php

namespace App\Services\LegacyImporter;

use App\Repositories\SettingRepository;

class SettingsImporter extends BaseImporter
{
    protected $table = 'settings';

    public function run($start = 0): void
    {
        $this->comment('--- SETTINGS IMPORT ---');

        /** @var SettingRepository $settingsRepo */
        $settingsRepo = app(SettingRepository::class);

        $count = 0;
        $rows = $this->db->readRows($this->table, $this->idField, $start);
        foreach ($rows as $row) {
            if ($row->name === 'ADMIN_EMAIL') {
                $settingsRepo->store('general.admin_email', $row->value);
            }
        }

        $this->info('Imported '.$count.' settings');
    }
}
