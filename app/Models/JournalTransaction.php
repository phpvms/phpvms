<?php

/**
 * Based on https://github.com/scottlaurent/accounting
 * With modifications for phpVMS
 */

namespace App\Models;

use App\Contracts\Model;
use App\Models\Traits\ReferenceTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string                          $id
 * @property string|null                     $transaction_group
 * @property int                             $journal_id
 * @property int|null                        $credit
 * @property int|null                        $debit
 * @property string                          $currency
 * @property string|null                     $memo
 * @property array<array-key, mixed>|null    $tags
 * @property string|null                     $ref_model
 * @property string|null                     $ref_model_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon      $post_date
 * @property-read \App\Models\Journal|null $journal
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JournalTransaction whereRefModel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JournalTransaction whereRefModelId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JournalTransaction whereTags($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JournalTransaction whereTransactionGroup($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JournalTransaction whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class JournalTransaction extends Model
{
    use HasFactory;
    use ReferenceTrait;

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
        'ref_model',
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
            'credits'   => 'integer',
            'debit'     => 'integer',
            'post_date' => 'datetime',
            'tags'      => 'array',
        ];
    }
}
