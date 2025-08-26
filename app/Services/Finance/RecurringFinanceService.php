<?php

namespace App\Services\Finance;

use App\Contracts\Service;
use App\Models\Aircraft;
use App\Models\Airline;
use App\Models\Enums\ExpenseType;
use App\Models\Expense;
use App\Models\Journal;
use App\Models\JournalTransaction;
use App\Models\Subfleet;
use App\Services\FinanceService;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Prettus\Validator\Exceptions\ValidatorException;

/**
 * Process all of the daily expenses and charge them
 */
class RecurringFinanceService extends Service
{
    public function __construct(
        private readonly FinanceService $financeSvc
    ) {}

    /**
     * Determine the journal to charge to, otherwise, it's charged
     * to every airline journal
     *
     *
     * @return Journal[]
     */
    protected function findJournals(Expense $expense)
    {
        if ($expense->airline_id) {
            return Journal::where([
                'morphed_type' => Airline::class,
                'morphed_id'   => $expense->airline_id,
            ])
                ->get();
        }

        $airline_ids = Airline::get(['id'])->pluck('id')->all();

        return Journal::where(['morphed_type' => Airline::class])
            ->whereIn('morphed_id', $airline_ids)
            ->get();
    }

    /**
     * Get the name of the transaction group from the expense
     */
    protected function getMemoAndGroup(Expense $expense): array
    {
        $klass = 'Expense';
        if ($expense->ref_model && $expense->ref_model !== Expense::class) {
            $ref = explode('\\', $expense->ref_model);
            $klass = end($ref);
            $obj = $expense->getReferencedObject();
        }

        if ($expense->ref_model && $expense->ref_model !== Expense::class && isset($obj) && $obj === null) {
            return [null, null];
        }

        if ($klass === 'Airport') {
            $memo = "Airport Expense: {$expense->name} ({$expense->ref_model_id})";
            $transaction_group = "Airport: {$expense->ref_model_id}";
        } elseif ($klass === 'Subfleet') {
            $memo = "Subfleet Expense: {$expense->name}";
            $transaction_group = "Subfleet: {$expense->name}";
        } elseif ($klass === 'Aircraft') {
            /** @var Aircraft $obj */
            $memo = "Aircraft Expense: {$expense->name} ({$obj->name})";
            $transaction_group = "Aircraft: {$expense->name} ({$obj->name}-{$obj->registration})";
        } else {
            $memo = "Expense: {$expense->name}";
            $transaction_group = "Expense: {$expense->name}";
        }

        return [$memo, $transaction_group];
    }

    /**
     * Run all of the daily expense/financials
     *
     *
     * @throws ValidatorException
     */
    public function processExpenses(string $type = ExpenseType::DAILY): void
    {
        $expenses = Expense::where(['type' => $type])->get();

        $tag = 'expense_recurring';
        if ($type === ExpenseType::DAILY) {
            $tag = 'expenses_daily';
        } elseif ($type === ExpenseType::MONTHLY) {
            $tag = 'expenses_monthly';
        }

        /**
         * @var Collection<int, Expense> $expenses
         */
        foreach ($expenses as $expense) {
            // Apply the expenses to the appropriate journals
            $journals = $this->findJournals($expense);
            foreach ($journals as $journal) {
                $amount = $expense->amount;

                // Has this expense already been charged? Check
                // against this specific journal, on today
                $w = [
                    'journal_id'   => $journal->id,
                    'ref_model'    => Expense::class,
                    'ref_model_id' => $expense->id,
                ];

                $ref = explode('\\', $expense->ref_model);
                $type = end($ref);

                $found = JournalTransaction::where($w)
                    ->whereDate('post_date', '=', Carbon::now('UTC')->toDateString())
                    ->count(['id']);

                if ($found > 0) {
                    Log::info('Expense "'.$expense->name.'" already charged for today, skipping');

                    continue;
                }

                [$memo, $ta_group] = $this->getMemoAndGroup($expense);
                if (empty($memo) || empty($ta_group)) {
                    continue;
                }

                // Determine if this object actually belongs to this airline or not
                if ($type === 'Subfleet' || $type === 'Aircraft') {
                    /** @var Aircraft|Subfleet $ref_model */
                    $ref_model = $expense->ref_model()->with('airline')->first();
                    if ($ref_model?->airline?->id !== $journal->morphed_id) {
                        Log::info(
                            $type.' id '.$expense->ref_model_id.' does not belong to airline id '.$expense->airline_id.', skipping expense "'.$expense->name.'"'
                        );

                        continue;
                    }
                }

                $this->financeSvc->debitFromJournal(
                    $journal,
                    Money::createFromAmount($amount),
                    $expense,
                    $memo,
                    $ta_group,
                    $tag
                );

                Log::info('Expense memo: "'.$memo.'"; group: "'.$ta_group.'" charged!');
            }
        }
    }
}
