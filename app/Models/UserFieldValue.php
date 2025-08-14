<?php

namespace App\Models;

use App\Contracts\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int                             $id
 * @property int                             $user_field_id
 * @property string                          $user_id
 * @property string|null                     $value
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\UserField|null $field
 * @property-read mixed $name
 * @property-read \App\Models\User|null $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserFieldValue newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserFieldValue newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserFieldValue query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserFieldValue whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserFieldValue whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserFieldValue whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserFieldValue whereUserFieldId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserFieldValue whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserFieldValue whereValue($value)
 *
 * @mixin \Eloquent
 */
class UserFieldValue extends Model
{
    public $table = 'user_field_values';

    protected $fillable = [
        'user_field_id',
        'user_id',
        'value',
    ];

    public static $rules = [];

    /**
     * Return related field's name along with field values
     */
    public function name(): Attribute
    {
        return Attribute::make(
            get: fn ($_, $attrs) => optional($this->field)->name
        );
    }

    /**
     * Relationships
     */
    public function field(): BelongsTo
    {
        return $this->belongsTo(UserField::class, 'user_field_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
