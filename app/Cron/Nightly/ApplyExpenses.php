<?php

declare(strict_types=1);

namespace App\Cron\Nightly;

use App\Contracts\Listener;
use App\Enums\ExpenseType;
use App\Events\CronNightly;
use App\Services\Finance\RecurringFinanceService;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use UnexpectedValueException;

/**
 * Go through and apply any finances that are daily
 */
class ApplyExpenses extends Listener
{
    /**
     * ApplyExpenses constructor.
     */
    public function __construct(private readonly RecurringFinanceService $financeSvc) {}

    /**
     * Apply all of the expenses for a day
     *
     * @throws UnexpectedValueException
     * @throws InvalidArgumentException
     */
    public function handle(CronNightly $event): void
    {
        Log::info('Nightly: Applying daily expenses');
        $this->financeSvc->processExpenses(ExpenseType::DAILY);
    }
}
