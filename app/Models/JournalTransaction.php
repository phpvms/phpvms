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
 * @property string  id  UUID type
 * @property string  currency
 * @property string  memo
 * @property string  transaction_group
 * @property string  post_date
 * @property int credit
 * @property int debit
 * @property string  ref_model
 * @property int ref_model_id
 * @property Journal journal
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
