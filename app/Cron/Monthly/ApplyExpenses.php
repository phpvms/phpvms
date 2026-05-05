<?php

declare(strict_types=1);

namespace App\Cron\Monthly;

use App\Contracts\Listener;
use App\Events\CronMonthly;
use App\Models\Enums\ExpenseType;
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
     * Apply all of the expenses for a month
     *
     * @throws UnexpectedValueException
     * @throws InvalidArgumentException
     */
    public function handle(CronMonthly $event): void
    {
        Log::info('Monthly: Applying monthly expenses');
        $this->financeSvc->processExpenses(ExpenseType::MONTHLY);
    }
}
