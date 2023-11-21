<?php

namespace App\Services\LegacyImporter;

use App\Models\Pirep;
use App\Services\FinanceService;
use App\Support\Money;

class LedgerImporter extends BaseImporter
{
    protected $table = 'ledger';

    /**
     * Legacy ID to the current ledger ref_model mapping
     *
     * @var array
     */
    private static $legacy_paysource = [
        1 => Pirep::class,
    ];

    /**
     * {@inheritdoc}
     *
     * @throws \Prettus\Validator\Exceptions\ValidatorException
     */
    public function run($start = 0)
    {
        if (!$this->db->tableExists('ledger')) {
            return;
        }

        $this->comment('--- LEDGER IMPORT ---');

        /** @var FinanceService $financeSvc */
        $financeSvc = app(FinanceService::class);

        $count = 0;
        $rows = $this->db->readRows($this->table, $this->idField, $start);
        foreach ($rows as $row) {
            $pirep = Pirep::find($this->idMapper->getMapping('pireps', $row->pirepid));
            if (!$pirep) {
                continue;
            }

            $pilot_pay = Money::createFromAmount($row->amount);
            $memo = 'Pilot payment';

            $financeSvc->debitFromJournal(
                $pirep->airline->journal,
                $pilot_pay,
                $pirep,
                $memo,
                'Pilot Pay',
                'pilot_pay',
                $row->submitdate
            );

            $financeSvc->creditToJournal(
                $pirep->user->journal,
                $pilot_pay,
                $pirep,
                $memo,
                'Pilot Pay',
                'pilot_pay',
                $row->submitdate
            );

            $count++;
        }

        $this->info('Imported '.$count.' ledger entries');
    }
}
