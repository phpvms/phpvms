<?php

namespace App\Services\LegacyImporter;

use App\Models\PirepComment;

class PirepCommentImporter extends BaseImporter
{
    protected $table = 'pirepcomments';

    protected $idField = 'id';

    public function run($start = 0)
    {
        $this->comment('--- PIREPCOMMENT IMPORT ---');

        $fields = [
            'pirepid',
            'pilotid',
            'comment',
            'postdate',
        ];

        $count = 0;
        $rows = $this->db->readRows($this->table, $this->idField, $start, $fields);
        foreach ($rows as $row) {
            $pirep_id = $row->pirepid;
            $user_id = $this->idMapper->getMapping('users', $row->pilotid);

            $attrs = [
                'pirep_id'   => $pirep_id,
                'user_id'    => $user_id,
                'comment'    => $row->comment,
                'created_at' => $this->parseDate($row->postdate),
                'updated_at' => $this->parseDate($row->postdate),
            ];

            $w = ['id' => $pirep_id];

            $pirepcomment = PirepComment::updateOrCreate($w, $attrs);

            if ($pirepcomment->wasRecentlyCreated) {
                $count++;
            }
        }

        $this->info('Imported '.$count.' pirepcomments');
    }
}
