<?php

namespace App\Models\Observers;

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
     * After transaction is saved, adjust the journal balance
     */
    public function saved(JournalTransaction $transaction): void
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
