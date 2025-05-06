<?php

namespace App\Repositories;

use App\Contracts\Repository;
use App\Models\Journal;
use App\Models\JournalTransaction;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Prettus\Repository\Contracts\CacheableInterface;
use Prettus\Repository\Traits\CacheableRepository;
use Prettus\Validator\Exceptions\ValidatorException;

/**
 * Class JournalRepository
 */
class JournalRepository extends Repository implements CacheableInterface
{
    use CacheableRepository;

    /**
     * @return string
     */
    public function model()
    {
        return JournalTransaction::class;
    }

    /**
     * Return a Y-m-d string for the post date
     *
     *
     * @return string
     */
    public function formatPostDate(?Carbon $date = null)
    {
        if (!$date instanceof \Carbon\Carbon) {
            return null;
        }

        return $date->setTimezone('UTC')->toDateString();
    }

    /**
     * Recalculate the balance of the given journal
     *
     *
     * @return Journal
     *
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    public function recalculateBalance(Journal $journal)
    {
        $where = [
            'journal_id' => $journal->id,
        ];

        $credits = Money::create($this->where($where)->sum('credit') ?: 0);
        $debits = Money::create($this->where($where)->sum('debit') ?: 0);
        $balance = $credits->subtract($debits);

        $journal->balance = $balance->getAmount();
        $journal->save();

        return $journal;
    }

    /**
     * Post a new transaction to a journal, and also adjust the balance
     * on the transaction itself. A cron will run to reconcile the journal
     * balance nightly, since they're not atomic operations
     *
     * @param  Money|null        $credit            Amount to credit
     * @param  Money|null        $debit             Amount to debit
     * @param  Model|null        $reference         The object this is a reference to
     * @param  string|null       $memo              Memo for this transaction
     * @param  string|null       $post_date         Date of the posting
     * @param  string|null       $transaction_group Grouping name for the summaries
     * @param  array|string|null $tags              Tag used for grouping/finding items
     * @return mixed
     *
     * @throws ValidatorException
     */
    public function post(
        Journal &$journal,
        ?Money $credit = null,
        ?Money $debit = null,
        $reference = null,
        $memo = null,
        $post_date = null,
        $transaction_group = null,
        $tags = null
    ) {
        // tags can be passed in a list
        if ($tags && \is_array($tags)) {
            $tags = implode(',', $tags);
        }

        if (!$post_date) {
            $post_date = Carbon::now('UTC');
        }

        $attrs = [
            'journal_id'        => $journal->id,
            'credit'            => $credit instanceof \App\Support\Money ? $credit->getAmount() : null,
            'debit'             => $debit instanceof \App\Support\Money ? $debit->getAmount() : null,
            'currency'          => setting('units.currency', 'USD'),
            'memo'              => $memo,
            'post_date'         => $post_date,
            'transaction_group' => $transaction_group,
            'tags'              => $tags,
        ];

        if ($reference !== null) {
            $attrs['ref_model'] = \get_class($reference);
            $attrs['ref_model_id'] = $reference->id;
        }

        try {
            $transaction = $this->create($attrs);
        } catch (ValidatorException $e) {
            throw $e;
        }

        $journal->refresh();

        return $transaction;
    }

    /**
     * @return Money
     *
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    public function getBalance(?Journal $journal = null, ?Carbon $date = null)
    {
        $journal->refresh();

        if (!$date instanceof \Carbon\Carbon) {
            $date = Carbon::now('UTC');
        }

        $credit = $this->getCreditBalanceBetween($date, $journal);
        $debit = $this->getDebitBalanceBetween($date, $journal);

        return $credit->subtract($debit);
    }

    /**
     * Get the credit only balance of the journal based on a given date.
     *
     * @param null $transaction_group
     *
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    public function getCreditBalanceBetween(
        Carbon $date,
        ?Journal $journal = null,
        ?Carbon $start_date = null,
        $transaction_group = null
    ): Money {
        $where = [];

        if ($journal instanceof \App\Models\Journal) {
            $where['journal_id'] = $journal->id;
        }

        if ($transaction_group) {
            $where['transaction_group'] = $transaction_group;
        }

        $query = JournalTransaction::where($where);
        $query = $query->whereDate('post_date', '<=', $date->toDateString());

        if ($start_date instanceof \Carbon\Carbon) {
            $query = $query->whereDate('post_date', '>=', $start_date->toDateString());
        }

        $balance = $query->sum('credit') ?: 0;

        return new Money($balance);
    }

    /**
     * @param null $transaction_group
     *
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    public function getDebitBalanceBetween(
        Carbon $date,
        ?Journal $journal = null,
        ?Carbon $start_date = null,
        $transaction_group = null
    ): Money {
        $where = [];

        if ($journal instanceof \App\Models\Journal) {
            $where['journal_id'] = $journal->id;
        }

        if ($transaction_group) {
            $where['transaction_group'] = $transaction_group;
        }

        $query = JournalTransaction::where($where);
        $query = $query->whereDate('post_date', '<=', $date->toDateString());

        if ($start_date instanceof \Carbon\Carbon) {
            $query = $query->whereDate('post_date', '>=', $start_date->toDateString());
        }

        $balance = $query->sum('debit') ?: 0;

        return new Money($balance);
    }

    /**
     * Return all transactions for a given object
     *
     * @param  null  $journal
     * @return array
     *
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    public function getAllForObject($object, $journal = null, ?Carbon $date = null)
    {
        $where = [
            'ref_model'    => \get_class($object),
            'ref_model_id' => $object->id,
        ];

        if ($journal) {
            $where['journal_id'] = $journal->id;
        }

        if ($date instanceof \Carbon\Carbon) {
            $date = $this->formatPostDate($date);
            $where[] = ['post_date', '=', $date];
        }

        $transactions = $this->whereOrder($where, [
            'credit' => 'desc',
            'debit'  => 'desc',
        ])->get();

        return [
            'credits'      => new Money($transactions->sum('credit')),
            'debits'       => new Money($transactions->sum('debit')),
            'transactions' => $transactions,
        ];
    }

    /**
     * Delete all transactions for a given object
     *
     * @param  null $journal
     * @return void
     */
    public function deleteAllForObject($object, $journal = null)
    {
        $where = [
            'ref_model'    => \get_class($object),
            'ref_model_id' => $object->id,
        ];

        if ($journal) {
            $where['journal_id'] = $journal->id;
        }

        $this->deleteWhere($where);
    }
}
