<?php

declare(strict_types=1);

namespace App\Queries;

use App\Contracts\Model;
use App\Models\Journal;
use App\Models\JournalTransaction;
use App\Models\User;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

/**
 * Looks up journal transactions referencing a specific object (and
 * optionally narrowed by journal and post_date).
 *
 * Replaces JournalRepository::getAllForObject. Plain query class — no
 * HTTP request, no pagination — because the only callers are services
 * and the PIREP finances API endpoint which always wants the full set.
 */
class JournalTransactionQuery
{
    /**
     * @return array{credits: Money, debits: Money, transactions: Collection<int, JournalTransaction>}
     */
    public function build(
        Model|User $refModel,
        ?Journal $journal = null,
        ?Carbon $date = null
    ): array {
        $query = JournalTransaction::query()
            ->where('ref_model_type', \get_class($refModel))
            ->where('ref_model_id', $refModel->getKey());

        if ($journal instanceof Journal) {
            $query->where('journal_id', $journal->id);
        }

        if ($date instanceof Carbon) {
            // post_date is a DATE column (no time portion). A direct equals
            // against the Y-m-d string keeps the index seek; whereDate()
            // would wrap DATE() and disable it. Carbon::copy() prevents
            // mutating the caller's instance.
            $query->where('post_date', '=', $date->copy()->setTimezone('UTC')->toDateString());
        }

        $transactions = $query
            ->orderBy('credit', 'desc')
            ->orderBy('debit', 'desc')
            ->get();

        return [
            'credits'      => new Money($transactions->sum('credit')),
            'debits'       => new Money($transactions->sum('debit')),
            'transactions' => $transactions,
        ];
    }
}
