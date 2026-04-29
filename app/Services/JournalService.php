<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Service;
use App\Models\Journal;
use App\Models\JournalTransaction;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use UnexpectedValueException;

/**
 * Owns writes against journals: posting transactions and bulk-deleting
 * the transactions tied to a referenced object. Read paths live on the
 * Journal model (balance math) and JournalTransactionQuery (lookups).
 *
 * Replaces JournalRepository::post() and JournalRepository::deleteAllForObject().
 */
class JournalService extends Service
{
    /**
     * Post a new transaction to a journal.
     *
     * Mirrors the legacy JournalRepository::post signature so callers
     * migrate one-for-one. The JournalTransactionObserver keeps the
     * cached journal balance in sync; the nightly cron reconciles.
     *
     * @param array<int, string>|string|null $tags Tag(s) used for grouping/finding items
     *
     * @throws UnexpectedValueException
     * @throws InvalidArgumentException
     */
    public function post(
        Journal $journal,
        ?Money $credit = null,
        ?Money $debit = null,
        ?Model $reference = null,
        ?string $memo = null,
        ?Carbon $post_date = null,
        ?string $transaction_group = null,
        array|string|null $tags = null
    ): JournalTransaction {
        if (\is_array($tags)) {
            $tags = implode(',', $tags);
        }

        if (!$post_date instanceof Carbon) {
            $post_date = Carbon::now('UTC');
        }

        $attrs = [
            'journal_id'        => $journal->id,
            'credit'            => $credit instanceof Money ? $credit->getAmount() : null,
            'debit'             => $debit instanceof Money ? $debit->getAmount() : null,
            'currency'          => $journal->currency ?? setting('units.currency', 'USD'),
            'memo'              => $memo,
            'post_date'         => $post_date,
            'transaction_group' => $transaction_group,
            'tags'              => $tags,
        ];

        if ($reference instanceof Model) {
            $attrs['ref_model_type'] = \get_class($reference);
            $attrs['ref_model_id'] = $reference->getKey();
        }

        $transaction = JournalTransaction::create($attrs);

        $journal->refresh();

        return $transaction;
    }

    /**
     * Delete every transaction tied to $object (optionally narrowed to
     * one journal). Triggers JournalTransactionObserver::deleted on each
     * row so cached balances stay correct.
     *
     * Replaces JournalRepository::deleteAllForObject.
     */
    public function deleteAllForObject(Model $object, ?Journal $journal = null): void
    {
        $query = JournalTransaction::query()
            ->where('ref_model_type', \get_class($object))
            ->where('ref_model_id', $object->getKey());

        if ($journal instanceof Journal) {
            $query->where('journal_id', $journal->id);
        }

        // Iterate to fire observer events (mass delete would skip them,
        // and the observer is what keeps Journal->balance current).
        // lazyById streams in chunks so callers with thousands of rows
        // (e.g. cleaning up an old airline) don't load everything at once.
        $query->lazyById()->each(function (JournalTransaction $transaction): void {
            $transaction->delete();
        });
    }
}
