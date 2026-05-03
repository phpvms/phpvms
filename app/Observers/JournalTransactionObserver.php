<?php

namespace App\Observers;

use App\Models\JournalTransaction;
use Ramsey\Uuid\Uuid;

/**
 * Class JournalTransactionObserver
 */
class JournalTransactionObserver
{
    /**
     * Set the ID to a UUID
     */
    public function creating(JournalTransaction $transaction): void
    {
        if (!$transaction->id) {
            $transaction->id = Uuid::uuid4()->toString();
        }
    }

    /**
     * After transaction is created, adjust the journal balance.
     *
     * Hooks `created` (not `saved`) so that subsequent updates to a
     * transaction do not double-count its credit/debit. Eloquent's
     * `saved` event fires on both insert and update; `created` fires
     * only on insert, which matches the intended semantics.
     */
    public function created(JournalTransaction $transaction): void
    {
        $journal = $transaction->journal;
        if ($transaction['credit']) {
            $balance = $journal->balance->toAmount();
            $journal->balance = (int) $balance + $transaction->credit;
        }

        if ($transaction['debit']) {
            $balance = $journal->balance->toAmount();
            $journal->balance = (int) $balance - $transaction->debit;
        }

        $journal->save();
    }

    /**
     * After transaction is deleted, adjust the balance on the journal
     */
    public function deleted(JournalTransaction $transaction): void
    {
        $journal = $transaction->journal;
        if ($transaction['credit']) {
            $balance = $journal->balance->toAmount();
            $journal->balance = $balance - $transaction['credit'];
        }

        if ($transaction['debit']) {
            $balance = $journal->balance->toAmount();
            $journal->balance = $balance + $transaction['debit'];
        }

        $journal->save();
    }
}
