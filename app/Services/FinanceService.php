<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Model;
use App\Contracts\Service;
use App\Models\Airline;
use App\Models\Expense;
use App\Models\Journal;
use App\Models\JournalTransaction;
use App\Models\User;
use App\Repositories\AirlineRepository;
use App\Repositories\JournalRepository;
use App\Support\Dates;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Prettus\Validator\Exceptions\ValidatorException;

class FinanceService extends Service
{
    public function __construct(
        private readonly AirlineRepository $airlineRepo,
        private readonly JournalRepository $journalRepo
    ) {}

    /**
     * Add an expense, and properly tie it to a model, and know which
     * airline this needs to be charged to
     *
     * @param array      $attrs      Array of attributes
     * @param Model|null $model      The model this expense is tied to
     * @param int|null   $airline_id The airline this expense should get charged to
     */
    public function addExpense(array $attrs, ?Model $model = null, ?int $airline_id = null): Expense
    {
        $expense = new Expense($attrs);

        if ($model instanceof Model) {
            $expense->ref_model_type = get_class($model);
            $expense->ref_model_id = $model->id;
        } else {
            $expense->ref_model_type = Expense::class;
        }

        if ($airline_id !== null && $airline_id !== 0) {
            $expense->airline_id = $airline_id;
        }

        $expense->save();

        return $expense;
    }

    /**
     * Get all active expenses for a given expense type. The result combines:
     *   - "Global" expenses (airline_id IS NULL)
     *   - Expenses for the given $airline_id (when one is supplied)
     *
     * Optionally filtered by ref_model_type (class string OR object — its class
     * is used) and ref_model_id.
     *
     * Replaces the deleted ExpenseRepository::getAllForType().
     *
     * Note: $ref_model_id narrows only the global lane; the airline-specific lane
     * filters on $ref_model type alone (preserves legacy behavior).
     *
     * @param  string                   $type         An ExpenseType constant (FLIGHT/DAILY/MONTHLY).
     * @param  int|null                 $airline_id   When set, also include this airline's expenses.
     * @param  object|string|null       $ref_model    Either a model instance or a class string.
     * @param  int|string|null          $ref_model_id Optional id to narrow the ref_model match (global lane only).
     * @return Collection<int, Expense>
     */
    public function getExpensesForType(
        string $type,
        ?int $airline_id = null,
        object|string|null $ref_model = null,
        int|string|null $ref_model_id = null,
    ): Collection {
        $ref_model_type = null;
        if ($ref_model !== null) {
            $ref_model_type = is_object($ref_model) ? get_class($ref_model) : $ref_model;
        }

        // Global lane: airline_id IS NULL
        $globalQuery = Expense::with('ref_model')
            ->active()
            ->ofType($type)
            ->forGlobalAirline();

        if ($ref_model_type !== null) {
            $globalQuery->forRefModel($ref_model_type, $ref_model_id);
        }

        $expenses = $globalQuery->get();

        // Airline-specific lane: airline_id = $airline_id
        if ($airline_id !== null) {
            $airlineQuery = Expense::with('ref_model')
                ->active()
                ->ofType($type)
                ->forAirline($airline_id);

            if ($ref_model_type !== null) {
                $airlineQuery->forRefModel($ref_model_type);
            }

            $expenses = $expenses->concat($airlineQuery->get());
        }

        return $expenses;
    }

    /**
     * Credit some amount to a given journal
     * E.g, some amount for expenses or ground handling fees, etc. Example, to pay a user a dollar
     * for a pirep:
     *
     * creditToJournal($user->journal, new Money(1000), $pirep, 'Payment', 'pirep', 'payment');
     *
     * @return mixed
     *
     * @throws ValidatorException
     */
    public function creditToJournal(
        Journal $journal,
        Money $amount,
        Model|User|null $reference,
        string $memo,
        string $transaction_group,
        string|array $tag,
        Carbon|string|null $post_date = null
    ) {
        return $this->journalRepo->post(
            $journal,
            $amount,
            null,
            $reference,
            $memo,
            $post_date,
            $transaction_group,
            $tag
        );
    }

    /**
     * Charge some expense for a given PIREP to the airline its file against
     * E.g, some amount for expenses or ground handling fees, etc.
     *
     * @return mixed
     *
     * @throws ValidatorException
     */
    public function debitFromJournal(
        Journal $journal,
        Money $amount,
        Model|User|null $reference,
        string $memo,
        string $transaction_group,
        string|array $tag,
        string|Carbon|null $post_date = null
    ) {
        return $this->journalRepo->post(
            $journal,
            null,
            $amount,
            $reference,
            $memo,
            $post_date,
            $transaction_group,
            $tag
        );
    }

    /**
     * Get all of the transactions for every airline in a given month
     *
     * @param string $month In Y-m format
     */
    public function getAllAirlineTransactionsBetween($month): array
    {
        $between = Dates::getMonthBoundary($month);

        $transaction_groups = [];
        $airlines = $this->airlineRepo->orderBy('icao')->all();

        // group by the airline
        foreach ($airlines as $airline) {
            $transaction_groups[] = $this->getAirlineTransactionsBetween(
                $airline,
                $between[0],
                $between[1]
            );
        }

        return $transaction_groups;
    }

    /**
     * Get all of the transactions for an airline between two given dates. Returns an array
     * with `credits`, `debits` and `transactions` fields, where transactions contains the
     * grouped transactions (e.g, "Fares" and "Ground Handling", etc)
     *
     * @param  Airline $airline
     * @param  string  $start_date YYYY-MM-DD
     * @param  string  $end_date   YYYY-MM-DD
     * @return array
     */
    public function getAirlineTransactionsBetween($airline, $start_date, $end_date)
    {
        // Return all the transactions, grouped by the transaction group
        $transactions = JournalTransaction::groupBy('transaction_group', 'currency')
            ->selectRaw(
                'transaction_group, 
                         currency, 
                         SUM(credit) as sum_credits, 
                         SUM(debit) as sum_debits'
            )
            ->where(['journal_id' => $airline->journal->id])
            ->whereBetween('post_date', [$start_date, $end_date], 'AND')
            ->orderBy('sum_credits', 'desc')
            ->orderBy('sum_debits', 'desc')
            ->orderBy('transaction_group', 'asc')
            ->get();

        // Summate it so we can show it on the footer of the table
        $sum_all_credits = 0;
        $sum_all_debits = 0;
        foreach ($transactions as $ta) {
            $sum_all_credits += $ta->sum_credits ?? 0;
            $sum_all_debits += $ta->sum_debits ?? 0;
        }

        return [
            'airline'      => $airline,
            'credits'      => new Money($sum_all_credits),
            'debits'       => new Money($sum_all_debits),
            'transactions' => $transactions,
        ];
    }

    /**
     * Change the currencies on the journals and transactions to the current currency value
     */
    public function changeJournalCurrencies(): void
    {
        $currency = setting('units.currency', 'USD');
        $update = ['currency' => $currency];

        Journal::query()->update($update);
        JournalTransaction::query()->update($update);
    }
}
