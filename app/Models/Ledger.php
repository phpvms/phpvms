<?php

/**
 * Based on https://github.com/scottlaurent/accounting
 * With modifications for phpVMS.
 */

namespace App\Models;

use App\Contracts\Model;
use App\Support\Money;
use Carbon\Carbon;

/**
 * Class Ledger.
 *
 * @property Money  $balance
 * @property string $currency
 * @property Carbon $updated_at
 * @property Carbon $post_date
 * @property Carbon $created_at
 */
class Ledger extends Model
{
    public $table = 'ledgers';

    public function journals()
    {
        return $this->hasMany(Journal::class);
    }

    /**
     * Get all of the posts for the country.
     */
    public function journal_transctions()
    {
        return $this->hasManyThrough(JournalTransaction::class, Journal::class);
    }

    /**
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    public function getCurrentBalance(): Money
    {
        if ($this->type === 'asset' || $this->type === 'expense') {
            $balance = $this->journal_transctions->sum('debit') - $this->journal_transctions->sum('credit');
        } else {
            $balance = $this->journal_transctions->sum('credit') - $this->journal_transctions->sum('debit');
        }

        return new Money($balance);
    }

    /**
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    public function getCurrentBalanceInDollars()
    {
        return $this->getCurrentBalance()->getValue();
    }
}
