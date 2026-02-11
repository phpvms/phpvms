<?php

/**
 * Based on https://github.com/scottlaurent/accounting
 * With modifications for phpVMS
 */

namespace App\Models;

use App\Contracts\Model;
use App\Models\Casts\MoneyCast;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use InvalidArgumentException;
use UnexpectedValueException;

/**
 * Holds various journals, depending on the morphed_type and morphed_id columns
 *
 * @property int                             $id
 * @property int|null                        $ledger_id
 * @property int                             $type
 * @property mixed                           $balance
 * @property string                          $currency
 * @property string|null                     $morphed_type
 * @property int|null                        $morphed_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Ledger|null $ledger
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent|null $morphed
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\JournalTransaction> $transactions
 * @property-read int|null $transactions_count
 *
 * @method static \Database\Factories\JournalFactory                    factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Journal newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Journal newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Journal query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Journal whereBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Journal whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Journal whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Journal whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Journal whereLedgerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Journal whereMorphedId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Journal whereMorphedType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Journal whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Journal whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Journal extends Model
{
    use HasFactory;

    protected $table = 'journals';

    protected $fillable = [
        'ledger_id',
        'journal_type',
        'balance',
        'currency',
        'morphed_type',
        'morphed_id',
    ];

    public $casts = [
        'balance'    => MoneyCast::class,
        'deleted_at' => 'datetime',
    ];

    /**
     * Relationships
     */
    public function morphed(): MorphTo
    {
        // Get all of the morphed models
        return $this->morphTo();
    }

    public function ledger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(JournalTransaction::class);
    }

    /**
     * @param string $currency
     */
    public function setCurrency($currency)
    {
        $this->currency = $currency;
    }

    /**
     * @return Journal
     */
    public function assignToLedger(Ledger $ledger)
    {
        $ledger->journals()->save($this);

        return $this;
    }

    /**
     * @throws UnexpectedValueException
     * @throws InvalidArgumentException
     */
    public function resetCurrentBalances()
    {
        $this->balance = $this->getBalance();
        $this->save();
    }

    /**
     * @param  Journal $object
     * @return HasMany
     */
    public function transactionsReferencingObjectQuery($object)
    {
        return $this
            ->transactions()
            ->where('ref_model_type', \get_class($object))
            ->where('ref_model_id', $object->id);
    }

    /**
     * Get the credit only balance of the journal based on a given date.
     *
     *
     * @return Money
     *
     * @throws UnexpectedValueException
     * @throws InvalidArgumentException
     */
    public function getCreditBalanceOn(Carbon $date)
    {
        $balance = $this->transactions()
            ->where('post_date', '<=', $date)
            ->sum('credit') ?: 0;

        return new Money($balance);
    }

    public function getDebitBalanceOn(Carbon $date): Money
    {
        $balance = $this->transactions()
            ->where('post_date', '<=', $date)
            ->sum('debit') ?: 0;

        return new Money($balance);
    }

    /**
     * Get the balance of the journal based on a given date.
     *
     *
     * @return Money
     *
     * @throws UnexpectedValueException
     * @throws InvalidArgumentException
     */
    public function getBalanceOn(Carbon $date)
    {
        return $this->getCreditBalanceOn($date)
            ->subtract($this->getDebitBalanceOn($date));
    }

    /**
     * Get the balance of the journal as of right now, excluding future transactions.
     *
     * @return Money
     *
     * @throws UnexpectedValueException
     * @throws InvalidArgumentException
     */
    public function getCurrentBalance()
    {
        return $this->getBalanceOn(Carbon::now('UTC'));
    }

    /**
     * Get the balance of the journal.  This "could" include future dates.
     *
     * @return Money
     *
     * @throws UnexpectedValueException
     * @throws InvalidArgumentException
     */
    public function getBalance()
    {
        $balance = $this
            ->transactions()
            ->sum('credit') - $this->transactions()->sum('debit');

        return new Money($balance);
    }
}
