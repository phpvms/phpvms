<?php

namespace App\Cron\Nightly;

use App\Contracts\Listener;
use App\Events\CronNightly;
use App\Models\Enums\ExpenseType;
use App\Services\Finance\RecurringFinanceService;
use Illuminate\Support\Facades\Log;

/**
 * Go through and apply any finances that are daily
 */
class ApplyExpenses extends Listener
{
    private RecurringFinanceService $financeSvc;

    /**
     * ApplyExpenses constructor.
     */
    public function __construct(RecurringFinanceService $financeSvc)
    {
        $this->financeSvc = $financeSvc;
    }

    /**
     * Apply all of the expenses for a day
     *
     *
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \Prettus\Validator\Exceptions\ValidatorException
     */
    public function handle(CronNightly $event): void
    {
        Log::info('Nightly: Applying daily expenses');
        $this->financeSvc->processExpenses(ExpenseType::DAILY);
    }
}
