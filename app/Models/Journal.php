<?php

/**
 * Based on https://github.com/scottlaurent/accounting
 * With modifications for phpVMS
 */

namespace App\Models;

use App\Casts\MoneyCast;
use App\Contracts\Model;
use App\Observers\JournalObserver;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Collection;
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
 * @property-read Ledger|null $ledger
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent|null $morphed
 * @property-read Collection<int, JournalTransaction> $transactions
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
#[ObservedBy(JournalObserver::class)]
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

    protected function casts(): array
    {
        return [
            'balance'    => MoneyCast::class,
            'deleted_at' => 'datetime',
        ];
    }

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

    /**
     * Sum credits posted on this journal up to $date (and optionally
     * on/after $startDate, optionally narrowed to a transaction_group).
     *
     * Replaces JournalRepository::getCreditBalanceBetween. Date filters
     * use whereDate so the boundary days are inclusive (matches the
     * legacy repository behavior used by FinanceService balance views).
     *
     * Note: this is day-precision (whereDate). getCreditBalanceOn uses
     * datetime-precision (where '<=' $date). They will return different
     * sums for transactions posted on the boundary day at non-midnight
     * times.
     *
     * @throws UnexpectedValueException
     * @throws InvalidArgumentException
     */
    public function getCreditBalanceBetween(
        Carbon $date,
        ?Carbon $startDate = null,
        ?string $transactionGroup = null
    ): Money {
        return new Money(
            $this->balanceBetweenQuery($date, $startDate, $transactionGroup)->sum('credit') ?: 0
        );
    }

    /**
     * Sum debits posted on this journal up to $date (and optionally
     * on/after $startDate, optionally narrowed to a transaction_group).
     *
     * Replaces JournalRepository::getDebitBalanceBetween. Same date
     * boundary semantics as getCreditBalanceBetween.
     *
     * @throws UnexpectedValueException
     * @throws InvalidArgumentException
     */
    public function getDebitBalanceBetween(
        Carbon $date,
        ?Carbon $startDate = null,
        ?string $transactionGroup = null
    ): Money {
        return new Money(
            $this->balanceBetweenQuery($date, $startDate, $transactionGroup)->sum('debit') ?: 0
        );
    }

    /**
     * Recalculate this journal's stored balance from its transactions.
     *
     * Sums credits and debits across all transactions on this journal,
     * persists the resulting balance, and returns $this so callers can
     * fluent-chain. Replaces JournalRepository::recalculateBalance.
     *
     * Note: this sum is unfiltered by post_date — future-dated rows are
     * included. getCurrentBalance() filters by Carbon::now() and will
     * therefore disagree with the stored balance whenever there are
     * future-dated transactions on the journal.
     *
     * @throws UnexpectedValueException
     * @throws InvalidArgumentException
     */
    public function recalculateBalance(): self
    {
        $credits = Money::create($this->transactions()->sum('credit') ?: 0);
        $debits = Money::create($this->transactions()->sum('debit') ?: 0);

        $this->balance = $credits->subtract($debits)->getAmount();
        $this->save();

        return $this;
    }

    /**
     * Build the shared query used by getCreditBalanceBetween and
     * getDebitBalanceBetween. Scoped to this journal's transactions.
     *
     * @return HasMany<JournalTransaction, $this>
     */
    private function balanceBetweenQuery(
        Carbon $date,
        ?Carbon $startDate,
        ?string $transactionGroup
    ): HasMany {
        $query = $this->transactions()
            ->whereDate('post_date', '<=', $date->toDateString());

        if ($startDate instanceof Carbon) {
            $query->whereDate('post_date', '>=', $startDate->toDateString());
        }

        if ($transactionGroup !== null && $transactionGroup !== '') {
            $query->where('transaction_group', $transactionGroup);
        }

        return $query;
    }
}
