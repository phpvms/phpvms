<?php

namespace App\Models;

use App\Contracts\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Override;

/**
 * @property int         $id
 * @property int         $user_field_id
 * @property int         $user_id
 * @property string|null $value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read UserField|null $field
 * @property-read mixed $name
 * @property-read User|null $user
 *
 * @method static Builder<static>|UserFieldValue newModelQuery()
 * @method static Builder<static>|UserFieldValue newQuery()
 * @method static Builder<static>|UserFieldValue query()
 * @method static Builder<static>|UserFieldValue whereCreatedAt($value)
 * @method static Builder<static>|UserFieldValue whereId($value)
 * @method static Builder<static>|UserFieldValue whereUpdatedAt($value)
 * @method static Builder<static>|UserFieldValue whereUserFieldId($value)
 * @method static Builder<static>|UserFieldValue whereUserId($value)
 * @method static Builder<static>|UserFieldValue whereValue($value)
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

    public static array $rules = [];

    #[Override]
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
        ];
    }

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
