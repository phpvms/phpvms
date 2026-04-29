<?php

/**
 * Based on https://github.com/scottlaurent/accounting
 * With modifications for phpVMS
 */

namespace App\Models;

use App\Contracts\Model;
use App\Models\Observers\JournalTransactionObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string                       $id
 * @property string|null                  $transaction_group
 * @property int                          $journal_id
 * @property int|null                     $credit
 * @property int|null                     $debit
 * @property string                       $currency
 * @property string|null                  $memo
 * @property array<array-key, mixed>|null $tags
 * @property string|null                  $ref_model_type
 * @property string|null                  $ref_model_id
 * @property Carbon|null                  $created_at
 * @property Carbon|null                  $updated_at
 * @property Carbon                       $post_date
 * @property-read Journal|null $journal
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JournalTransaction newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JournalTransaction newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JournalTransaction query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JournalTransaction whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JournalTransaction whereCredit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JournalTransaction whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JournalTransaction whereDebit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JournalTransaction whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JournalTransaction whereJournalId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JournalTransaction whereMemo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JournalTransaction wherePostDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JournalTransaction whereRefModelId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JournalTransaction whereRefModelType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JournalTransaction whereTags($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JournalTransaction whereTransactionGroup($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JournalTransaction whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
#[ObservedBy(JournalTransactionObserver::class)]
class JournalTransaction extends Model
{
    use HasFactory;

    protected $table = 'journal_transactions';

    public $incrementing = false;

    protected $fillable = [
        'transaction_group',
        'journal_id',
        'credit',
        'debit',
        'currency',
        'memo',
        'tags',
        'ref_model_type',
        'ref_model_id',
        'post_date',
    ];

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    /**
     * @param string $currency
     */
    public function setCurrency($currency)
    {
        $this->currency = $currency;
    }

    protected function casts(): array
    {
        return [
            'credit'    => 'integer',
            'debit'     => 'integer',
            'post_date' => 'datetime',
            // tags is stored as a comma-separated string (post() implodes
            // arrays before insert). The previous 'array' cast tried to
            // JSON-decode that CSV and silently returned null on read.
            'tags' => 'string',
        ];
    }
}
