<?php

declare(strict_types=1);

/**
 * Based on https://github.com/scottlaurent/accounting
 * With modifications for phpVMS
 */

namespace App\Models;

use App\Contracts\Model;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use UnexpectedValueException;

/**
 * Class Ledger
 *
 * @property int         $id
 * @property string      $name
 * @property string      $type
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, JournalTransaction> $journal_transctions
 * @property-read int|null $journal_transctions_count
 * @property-read Collection<int, Journal> $journals
 * @property-read int|null $journals_count
 *
 * @method static Builder<static>|Ledger newModelQuery()
 * @method static Builder<static>|Ledger newQuery()
 * @method static Builder<static>|Ledger query()
 * @method static Builder<static>|Ledger whereCreatedAt($value)
 * @method static Builder<static>|Ledger whereId($value)
 * @method static Builder<static>|Ledger whereName($value)
 * @method static Builder<static>|Ledger whereType($value)
 * @method static Builder<static>|Ledger whereUpdatedAt($value)
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
    public function getCurrentBalanceInDollars(): float
    {
        return $this->getCurrentBalance()->getValue();
    }
}
