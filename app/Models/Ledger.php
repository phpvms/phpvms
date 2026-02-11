<?php

/**
 * Based on https://github.com/scottlaurent/accounting
 * With modifications for phpVMS
 */

namespace App\Models;

use App\Contracts\Model;
use App\Support\Money;
use InvalidArgumentException;
use UnexpectedValueException;

/**
 * Class Ledger
 *
 * @property int                             $id
 * @property string                          $name
 * @property string                          $type
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\JournalTransaction> $journal_transctions
 * @property-read int|null $journal_transctions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Journal> $journals
 * @property-read int|null $journals_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ledger newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ledger newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ledger query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ledger whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ledger whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ledger whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ledger whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ledger whereUpdatedAt($value)
 *
 * @mixin \Eloquent
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
     * @throws UnexpectedValueException
     * @throws InvalidArgumentException
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
     * @throws UnexpectedValueException
     * @throws InvalidArgumentException
     */
    public function getCurrentBalanceInDollars()
    {
        return $this->getCurrentBalance()->getValue();
    }
}
